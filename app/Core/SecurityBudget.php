<?php
declare(strict_types=1);
namespace App\Core;

final class SecurityBudget {
    public static function limit(string $name,int $default): int {$c=Config::get('security_limits',[]);return is_int($c[$name]??null)?max(1,min(100000,$c[$name])):$default;}
    public static function check(string $name,int $default,int $seconds=3600,string $identity='global'): void {RateLimit::check('security|'.$name.'|'.$identity,self::limit($name,$default),$seconds);}
}
