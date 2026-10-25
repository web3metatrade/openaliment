<?php
declare(strict_types=1);

const ROOT = __DIR__ . '/..';

spl_autoload_register(static function (string $class): void {
    if(str_starts_with($class,'lbuchs\\WebAuthn\\')){
        $path=__DIR__.'/Vendor/WebAuthn/src/'.str_replace('\\','/',substr($class,16)).'.php';
        if(is_file($path))require $path;return;
    }
    if (str_starts_with($class, 'App\\')) {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

require_once __DIR__ . '/Support/view_helpers.php';
