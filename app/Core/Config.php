<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $values = [];
    private static bool $initialized = false;

    public static function initialize(): void
    {
        if (!self::$initialized) {
            self::load();
        }
    }

    public static function load(?array $override = null): void
    {
        if ($override !== null) {
            self::$values = $override;
            self::$initialized = true;
            return;
        }
        $path=ROOT.'/config/local.php';
        if(!is_file($path))throw new \RuntimeException('Private application configuration missing.');
        $values=require $path;
        if(!is_array($values)||!is_array($values['db']??null)||!is_string($values['db']['dsn']??null)||!is_string($values['db']['user']??null)||!is_string($values['db']['password']??null)||!is_string($values['storage']??null)||!is_dir($values['storage']))throw new \RuntimeException('Invalid private application configuration.');
        $public=realpath(ROOT.'/public');$storage=realpath($values['storage']);
        if(!$storage||$storage===$public||str_starts_with($storage,$public.DIRECTORY_SEPARATOR))throw new \RuntimeException('Storage must remain outside public.');
        if(($values['environment']??'production')==='production'&&(!filter_var($values['url']??'',FILTER_VALIDATE_URL)||parse_url($values['url'],PHP_URL_SCHEME)!=='https'||($values['session_secure']??true)!==true))throw new \RuntimeException('Invalid production transport configuration.');
        self::$values=$values;
        self::$initialized = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$values[$key] ?? $default;
    }
}
