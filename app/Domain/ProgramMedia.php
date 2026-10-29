<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Config, Db, Problem};

final class ProgramMedia
{
    public static function owned(array $user, string $id, ?string $purpose = null): array
    {
        $row = Db::one("SELECT * FROM program_assets WHERE id=? AND user_id=? AND state<>'withdrawn'", [Api::id($id), $user['id']]);
        if (!$row || ($purpose !== null && $row['purpose'] !== $purpose)) {
            throw new Problem(404, 'program_asset', 'Fișierul nu a fost găsit.');
        }
        return $row;
    }

    public static function put(array $user, array $data): array
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
            throw new Problem(503, 'image_processing', 'Procesarea sigură a imaginilor nu este disponibilă momentan.');
        }
        $purpose = ProgramAccess::text($data, 'purpose', 30);
        if (!in_array($purpose, ['avatar', 'logo', 'lab_evidence', 'lesson'], true) || ($data['rights_confirmed'] ?? false) !== true) {
            throw new Problem(422, 'program_asset', 'Alege utilizarea imaginii și confirmă drepturile.');
        }
        if ($purpose === 'lesson') {
            ProgramAccess::staff($user, 'course_editor');
        }
        $encoded = $data['content_base64'] ?? null;
        $bytes = is_string($encoded) && strlen($encoded) <= 2800000 ? base64_decode($encoded, true) : false;
        $info = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        $mime = is_string($bytes) ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : '';
        if (!is_string($bytes) || strlen($bytes) > 2097152 || !$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 4000000
            || !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new Problem(422, 'program_image', 'Folosește PNG, JPEG sau WebP, cel mult 2 MB și 4 megapixeli.');
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source) {
            throw new Problem(422, 'program_image', 'Imaginea nu poate fi decodată.');
        }
        $max = $purpose === 'lab_evidence' ? 1600 : 640;
        $scale = min(1, $max / max($info[0], $info[1]));
        $width = max(1, (int) floor($info[0] * $scale)); $height = max(1, (int) floor($info[1] * $scale));
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false); imagesavealpha($image, true);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        ob_start(); imagepng($image, null, 8); $clean = ob_get_clean();
        imagedestroy($image); imagedestroy($source);
        if (!is_string($clean) || strlen($clean) > 2097152) {
            throw new Problem(422, 'program_image_size', 'Imaginea procesată depășește limita.');
        }
        $sha = hash('sha256', $clean);
        $existing = Db::one("SELECT * FROM program_assets WHERE user_id=? AND sha256=? AND purpose=? AND state<>'withdrawn'", [$user['id'], $sha, $purpose]);
        if ($existing) {
            return self::metadata($existing);
        }
        return Db::atomic(static function () use ($user, $purpose, $width, $height, $clean, $sha): array {
            if (!Db::one("SELECT GET_LOCK('oa_program_media_quota',0) AS acquired")['acquired']) {
                throw new Problem(429, 'media_busy', 'O altă imagine este procesată. Reîncearcă.');
            }
            try {
                $own = (int) Db::one('SELECT COALESCE(SUM(size_bytes),0) AS n FROM program_assets WHERE user_id=?', [$user['id']])['n'];
                $total = (int) Db::one('SELECT COALESCE(SUM(size_bytes),0) AS n FROM program_assets')['n'];
                if ($own + strlen($clean) > 30 * 1024 * 1024 || $total + strlen($clean) > 2 * 1024 * 1024 * 1024) {
                    throw new Problem(413, 'media_quota', 'Limita de stocare a imaginilor a fost atinsă.');
                }
                $id = Db::id(); $directory = rtrim((string) Config::get('storage'), '/\\') . '/private/program-assets';
                if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                    throw new \RuntimeException('Program media storage unavailable');
                }
                $path = $directory . '/' . $id . '.png';
                if (is_link($directory)) { throw new \RuntimeException('Program media directory cannot be a symlink'); }
                if (file_put_contents($path, $clean, LOCK_EX) !== strlen($clean)) {
                    throw new \RuntimeException('Program media write failed');
                }
                @chmod($path, 0600);
                Db::onRollback(static function () use ($path): void { if (is_file($path)) { unlink($path); } });
                Db::insert('program_assets', ['id' => $id, 'user_id' => $user['id'], 'purpose' => $purpose, 'file_key' => $id . '.png',
                    'mime' => 'image/png', 'size_bytes' => strlen($clean), 'width' => $width, 'height' => $height,
                    'sha256' => $sha, 'rights_confirmed' => 1, 'created_at' => Db::now()]);
                ProgramAccess::event($user, 'asset', $id, 'uploaded', ['purpose' => $purpose, 'sha256' => $sha]);
                return self::metadata(Db::one('SELECT * FROM program_assets WHERE id=?', [$id]));
            } finally {
                Db::run("SELECT RELEASE_LOCK('oa_program_media_quota')");
            }
        });
    }

    public static function metadata(array $row): array
    {
        return ['id' => $row['id'], 'purpose' => $row['purpose'], 'state' => $row['state'], 'mime' => $row['mime'],
            'bytes' => (int) $row['size_bytes'], 'width' => (int) $row['width'], 'height' => (int) $row['height'],
            'url' => '/program/imagini/' . $row['id'], 'sha256' => $row['sha256']];
    }

    public static function approve(string $id): void
    {
        if (!Db::one("SELECT id FROM program_assets WHERE id=? AND state<>'withdrawn' AND rights_confirmed=1", [Api::id($id)])) {
            throw new Problem(422, 'program_asset', 'Imagine indisponibilă sau drepturi neconfirmate.');
        }
        Db::run("UPDATE program_assets SET state='published' WHERE id=?", [$id]);
    }

    public static function withdraw(array $user, string $id): array
    {
        self::owned($user, $id);
        Db::run("UPDATE program_assets SET state='withdrawn',withdrawn_at=? WHERE id=?", [Db::now(), $id]);
        return ['id' => $id, 'state' => 'withdrawn'];
    }

    public static function publishLessonResource(string $url): void
    {
        $prefix = rtrim((string) Config::get('url'), '/') . '/program/imagini/';
        if (!str_starts_with($url, $prefix)) { return; }
        $id = substr($url, strlen($prefix));
        if (!preg_match('/\A[a-f0-9]{32}\z/D', $id) || !Db::one("SELECT id FROM program_assets WHERE id=? AND purpose='lesson' AND state<>'withdrawn' AND rights_confirmed=1", [$id])) {
            throw new Problem(422, 'lesson_asset', 'Resursa lecției nu este o imagine aprobată pentru curs.');
        }
        self::approve($id);
    }

    public static function read(?array $user, string $id): array
    {
        $row = Db::one("SELECT * FROM program_assets WHERE id=? AND state<>'withdrawn'", [Api::id($id)])
            ?? throw new Problem(404, 'program_asset', 'Imagine indisponibilă.');
        $role = match ($row['purpose']) { 'avatar' => 'community_editor', 'logo' => 'partner_reviewer', 'lab_evidence' => 'exam_reviewer', default => 'course_editor' };
        $private = $user && ($row['user_id'] === $user['id'] || $user['is_admin'] || Db::one('SELECT id FROM program_staff_grants WHERE user_id=? AND role=? AND revoked_at IS NULL LIMIT 1', [$user['id'], $role]));
        if ($user && $row['purpose'] === 'lesson') {
            $private = $private || (bool) Db::one("SELECT id FROM program_staff_grants WHERE user_id=? AND role='course_publisher' AND revoked_at IS NULL LIMIT 1", [$user['id']]);
        }
        $lesson = $row['purpose'] === 'lesson' && Db::one("SELECT id FROM learning_releases WHERE state='published' AND JSON_SEARCH(manifest_json,'one',?,NULL,'$.modules[*].lessons[*].resources[*].url') IS NOT NULL LIMIT 1", [rtrim((string) Config::get('url'), '/') . '/program/imagini/' . $id]);
        $public = $row['state'] === 'published' && ($lesson || Db::one("SELECT id FROM contributor_profiles WHERE JSON_UNQUOTE(JSON_EXTRACT(approved_json,'$.asset_id'))=? AND consent_at IS NOT NULL AND state NOT IN ('withdrawn','suspended') LIMIT 1", [$id]) || ProgramPartners::assetPublic($id));
        if (!$private && !$public) {
            throw new Problem(404, 'program_asset', 'Imagine indisponibilă.');
        }
        $path = rtrim((string) Config::get('storage'), '/\\') . '/private/program-assets/' . $row['id'] . '.png';
        if (!is_file($path) || is_link($path) || is_link(dirname($path)) || !hash_equals($row['sha256'], hash_file('sha256', $path))) {
            throw new Problem(503, 'program_asset_integrity', 'Imaginea nu poate fi verificată.');
        }
        return ['path' => $path, 'mime' => 'image/png', 'private' => (bool) $private];
    }
}
