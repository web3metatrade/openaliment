<?php
declare(strict_types=1);

namespace App\Domain;

/** Small Markdown subset: escaped text, paragraphs, lists, headings and literal code. */
final class ProgramHtml
{
    public static function markdown(string $source): string
    {
        $out = ''; $list = false; $code = false;
        foreach (preg_split('/\r?\n/', $source) as $line) {
            if (str_starts_with($line, '```')) {
                if ($list) { $out .= '</ul>'; $list = false; }
                $out .= $code ? '</code></pre>' : '<pre><code>'; $code = !$code; continue;
            }
            if ($code) { $out .= e($line) . "\n"; continue; }
            if (trim($line) === '') { if ($list) { $out .= '</ul>'; $list = false; } continue; }
            if (str_starts_with($line, '- ')) {
                if (!$list) { $out .= '<ul>'; $list = true; }
                $out .= '<li>' . self::inline(substr($line, 2)) . '</li>'; continue;
            }
            if ($list) { $out .= '</ul>'; $list = false; }
            if (preg_match('/\A(#{1,3}) (.+)\z/D', $line, $m)) {
                $level = min(4, strlen($m[1]) + 1); $out .= '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . '>';
            } else { $out .= '<p>' . self::inline($line) . '</p>'; }
        }
        return $out . ($list ? '</ul>' : '') . ($code ? '</code></pre>' : '');
    }

    private static function inline(string $text): string
    {
        $safe = e($text);
        $safe = preg_replace('/`([^`]+)`/', '<code>$1</code>', $safe);
        return preg_replace_callback('~\[([^\]]+)\]\((https://[^\s)]+)\)~', static function (array $m): string {
            try { $url = Marketplace::webUrl(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8')); }
            catch (\Throwable) { return $m[1]; }
            return '<a href="' . e($url) . '" rel="noopener noreferrer">' . $m[1] . '</a>';
        }, $safe);
    }

    public static function track(string $key): string
    {
        return ['erp-integration' => 'Integrare ERP', 'printing-integration' => 'Tipărire și serializare', 'platform-operations' => 'Operarea platformei', 'product-data' => 'Date de produs'][$key] ?? $key;
    }

    public static function state(string $key): string
    {
        return ['draft' => 'Ciornă', 'submitted' => 'În verificare', 'published' => 'Publicat', 'approved' => 'Aprobat', 'active' => 'Activ',
            'expired' => 'Expirat', 'unavailable' => 'Indisponibil', 'revoked' => 'Revocat', 'suspended' => 'Suspendat', 'withdrawn' => 'Retras',
            'needs_changes' => 'Necesită corecturi', 'rejected' => 'Respins', 'graded' => 'Evaluat', 'in_progress' => 'În desfășurare', 'validated' => 'Validat', 'ended' => 'Încheiat'][$key] ?? $key;
    }

    public static function requirement(string $key): string
    {
        return ['approved_profile_required' => 'Prezentarea și reprezentarea firmei trebuie aprobate.', 'profile_inactive' => 'Profilul firmei nu este activ.',
            'company_inactive' => 'Situația fiscală a firmei nu permite autorizarea.', 'fiscal_refresh_required' => 'Datele fiscale trebuie reverificate.',
            'verified_company_email_required' => 'Confirmă adresa de email a firmei; apoi trimite prezentarea curentă pentru aprobare.',
            'qualified_affiliate_required' => 'Este necesară o persoană afiliată cu o calificare valabilă.'][$key] ?? $key;
    }
}
