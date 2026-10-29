<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Config, Migrations, Problem};

/** Explicit private staging activation; does not change config/local.php. */
final class RegistrationSettings
{
    public static function enabled(): bool
    {
        $enabled = (bool) Config::get('registration_enabled', false);
        if (Config::get('environment') !== 'test' && is_file(ROOT . '/config/registration.local.php')) {
            $options = require ROOT . '/config/registration.local.php';
            if (!is_array($options) || !is_bool($options['enabled'] ?? null)) {
                throw new \RuntimeException('Invalid private registration settings.');
            }
            $enabled = $options['enabled'];
        }
        return $enabled;
    }

    public static function enableStaging(): void
    {
        if (rtrim((string) Config::get('url'), '/') !== 'https://dev.openaliment.ro') {
            throw new Problem(403, 'staging_only', 'Activarea este disponibilă numai pe staging.');
        }
        if (!Migrations::anaf()) {
            throw new Problem(503, 'anaf_setup', 'Instalează mai întâi profilul fiscal.');
        }
        $path = ROOT . '/config/registration.local.php';
        if (is_link($path)) {
            throw new \RuntimeException('Unsafe registration settings path.');
        }
        if (is_file($path)) {
            $options = require $path;
            if (($options['enabled'] ?? null) === true) {
                return;
            }
            throw new Problem(409, 'registration_settings', 'Configurația privată există deja. Actualizeaz-o prin administrarea hostingului.');
        }
        $handle = fopen($path, 'xb');
        if (!$handle) {
            throw new \RuntimeException('Registration settings unavailable.');
        }
        try {
            $bytes = "<?php\ndeclare(strict_types=1);\nreturn ['enabled' => true];\n";
            if (fwrite($handle, $bytes) !== strlen($bytes)) {
                throw new \RuntimeException('Registration settings write failed.');
            }
            fflush($handle);
            @chmod($path, 0600);
        } finally {
            fclose($handle);
        }
    }
}
