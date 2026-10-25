<?php
// Local PHP development server only; not included in public/.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('~^/assets/[A-Za-z0-9/_.-]+$~',$path)&&!str_contains($path,'..')&&is_file(__DIR__.'/../public'.$path))return false;
require __DIR__.'/../public/index.php';
