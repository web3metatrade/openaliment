<?php
declare(strict_types=1);

namespace App\Core;

final class ProgramRoutes
{
    public static function all(): array
    {
        static $routes;
        return $routes ??= json_decode(file_get_contents(ROOT . '/contracts/program/routes.json'), true, 32, JSON_THROW_ON_ERROR);
    }
}
