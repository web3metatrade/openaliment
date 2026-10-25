<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Config, Problem};

/** Official PDFs are immutable private reference copies; public reading links point to GS1. */
final class Gs1Standards
{
    public static function catalog(): array
    {
        $raw = file_get_contents(ROOT . '/contracts/gs1/standards-catalog.json');
        return json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    }

    private static function document(string $id): array
    {
        foreach (self::catalog()['documents'] as $document) {
            if ($document['id'] === $id) {
                return $document;
            }
        }
        throw new Problem(404, 'gs1_document', 'Ediția standardului nu a fost găsită.');
    }

    private static function path(array $document): string
    {
        $directory = Config::get('storage') . '/private/standards/gs1';
        if (!preg_match('/\A[a-z0-9.-]+\.pdf\z/D', $document['filename'])) {
            throw new \RuntimeException('Invalid standards manifest filename.');
        }
        $current = rtrim(Config::get('storage'), '/\\');
        foreach (['private', 'standards', 'gs1'] as $component) {
            $current .= '/' . $component;
            if (is_link($current)) {
                throw new \RuntimeException('Standards directory cannot be a symlink.');
            }
        }
        if (is_link($directory . '/' . $document['filename'])) {
            throw new \RuntimeException('Standards file cannot be a symlink.');
        }
        return $directory . '/' . $document['filename'];
    }

    public static function status(): array
    {
        $items = [];
        foreach (self::catalog()['documents'] as $document) {
            $path = self::path($document);
            $items[] = ['id' => $document['id'], 'version' => $document['version'],
                'verified' => is_file($path) && filesize($path) === $document['size_bytes']
                    && hash_equals($document['sha256'], hash_file('sha256', $path))];
        }
        return ['items' => $items];
    }

    public static function preserve(string $id): array
    {
        $document = self::document($id);
        $target = self::path($document);
        if (is_file($target) && filesize($target) === $document['size_bytes'] && hash_equals($document['sha256'], hash_file('sha256', $target))) {
            return ['id' => $id, 'version' => $document['version'], 'verified' => true];
        }
        if (!function_exists('curl_init')) {
            throw new Problem(503, 'gs1_download', 'Descărcarea securizată nu este disponibilă.');
        }
        $parts = parse_url($document['pdf_url']);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'ref.gs1.org'
            || !str_starts_with($parts['path'] ?? '', '/standards/') || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || isset($parts['port'])
            || !preg_match('/\A[a-f0-9]{64}\z/D', $document['sha256']) || $document['size_bytes'] > 33554432) {
            throw new \RuntimeException('Invalid standards source manifest.');
        }
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Standards storage unavailable.');
        }
        $temporary = tempnam($directory, '.gs1-');
        if ($temporary === false) {
            throw new \RuntimeException('Standards temporary file unavailable.');
        }
        @chmod($temporary, 0600);
        $file = fopen($temporary, 'wb');
        $curl = curl_init($document['pdf_url']);
        $size = 0;
        try {
            curl_setopt_array($curl, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => ['Accept: application/pdf'],
                CURLOPT_USERAGENT => 'OpenAliment-Standards-Preservation/1.0',
                CURLOPT_WRITEFUNCTION => static function ($connection, string $bytes) use ($file, &$size, $document): int {
                    $size += strlen($bytes);
                    if ($size > $document['size_bytes'] || !is_resource($file)) {
                        return 0;
                    }
                    $written = fwrite($file, $bytes);
                    return $written === false ? 0 : $written;
                }]);
            $result = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            fclose($file);
            $file = null;
            if ($result === false || $status !== 200 || $size !== $document['size_bytes']
                || !hash_equals($document['sha256'], hash_file('sha256', $temporary))
                || file_get_contents($temporary, false, null, 0, 5) !== '%PDF-') {
                throw new Problem(503, 'gs1_integrity', 'Copia nu corespunde ediției verificate. Niciun fișier existent nu a fost înlocuit.');
            }
            if (!rename($temporary, $target)) {
                throw new \RuntimeException('Standards copy unavailable.');
            }
            return ['id' => $id, 'version' => $document['version'], 'verified' => true];
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
            curl_close($curl);
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
