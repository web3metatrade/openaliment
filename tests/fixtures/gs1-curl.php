<?php
declare(strict_types=1);
namespace App\Domain;

/** Process-local transport double; no fixture function is included in application releases. */
final class Gs1TestTransport {
    public static string $bytes = '';
    public static int $status = 200;
    public static array $options = [];
    public static int $requests = 0;
}
function curl_init(string $url): object { Gs1TestTransport::$requests++; return new \stdClass(); }
function curl_setopt_array(object $handle, array $options): bool { Gs1TestTransport::$options = $options; return true; }
function curl_exec(object $handle): bool {
    $write = Gs1TestTransport::$options[CURLOPT_WRITEFUNCTION];
    foreach (str_split(Gs1TestTransport::$bytes, 16384) as $bytes) {
        if ($write($handle, $bytes) !== strlen($bytes)) { return false; }
    }
    return true;
}
function curl_getinfo(object $handle, int $option): int { return Gs1TestTransport::$status; }
function curl_close(object $handle): void {}
