<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../app/autoload.php';
echo json_encode(App\Core\ApiRoutes::all(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
