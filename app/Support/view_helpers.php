<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { return '<input type="hidden" name="_csrf" value="' . e(App\Core\Session::csrf()) . '">'; }
function url(string $path = ''): string { return rtrim(App\Core\Config::get('url'), '/') . '/' . ltrim($path, '/'); }
function ro_time(?string $utc): string {
    return $utc === null ? '' : (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Bucharest'))->format('d.m.Y H:i');
}
function ro_state(string $state): string {
    return ['proposed'=>'Propus','tested'=>'Testat','planned'=>'Planificat','on_hold'=>'În verificare','recalled'=>'Rechemat','queued'=>'În așteptarea procesării','running'=>'În procesare','completed'=>'Finalizat','failed'=>'Procesare nereușită','accepted'=>'Acceptat','superseded'=>'Înlocuit','private'=>'Privat','public'=>'Public','unverified'=>'Neverificat','verified'=>'Verificat','disputed'=>'În dispută','withdrawn'=>'Retras','pending'=>'În așteptare','published'=>'Publicat','draft'=>'Ciornă','restricted'=>'Restricționat','rejected'=>'Respins','review'=>'Recenzie','question'=>'Întrebare','idea'=>'Idee','quality'=>'Sesizare de calitate','experimental'=>'Experimental','validated_for_declared_scope'=>'Validat pentru domeniul declarat','published_event'=>'Informații publicate','released'=>'Lot eliberat','alert'=>'Alertă înregistrată','alert_resolved'=>'Alertă închisă','imported_event'=>'Eveniment istoric importat'][$state] ?? $state;
}
function icon(string $name, string $class = ''): string {
    $paths = ['arrow'=>'M5 12h14m-6-6 6 6-6 6','check'=>'m5 12 4 4L19 6','box'=>'m3 7 9-4 9 4v10l-9 4-9-4V7Zm0 0 9 4 9-4M12 11v10', 'qr'=>'M3 3h6v6H3V3Zm12 0h6v6h-6V3ZM3 15h6v6H3v-6Zm12 0h3v3h3v3h-6v-6Zm6-3v3M12 3v3m0 6h3M3 12h3', 'file'=>'M14 3H5v18h14V8l-5-5Zm0 0v5h5M8 12h8M8 16h6','shield'=>'m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Zm-4 9 3 3 5-6','plus'=>'M12 5v14M5 12h14'];
    $paths+=['close'=>'m6 6 12 12M6 18 18 6','settings'=>'M4 6h7m4 0h5M4 12h1m4 0h11M4 18h10m4 0h2M11 3v6M5 9v6m9 0v6','camera'=>'M3 7h4l2-3h6l2 3h4v13H3V7Zm9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z','download'=>'M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4','speaker'=>'m3 9 5 0 5-5v16l-5-5H3V9Zm14-2a7 7 0 0 1 0 10m3-13a11 11 0 0 1 0 16'];
    $paths+=['copy'=>'M9 9h11v12H9V9Zm6-4V2H3v13h3','chevron'=>'m6 9 6 6 6-6','search'=>'M21 21l-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0','external'=>'M14 3h7v7m0-7L10 14M10 3H3v18h18v-7','menu'=>'M3 6h18M3 12h18M3 18h18'];
    return '<svg class="icon ' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($paths[$name] ?? $paths['file']) . '"/></svg>';
}
