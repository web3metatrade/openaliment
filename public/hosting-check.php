<?php
declare(strict_types=1);
ini_set('display_errors','0');header_remove('X-Powered-By');
require __DIR__.'/../app/bootstrap.php';
use App\Core\{Config,Db,Session};
header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header("Content-Security-Policy: default-src 'none'; form-action 'self'; frame-ancestors 'none'");
if(!Config::get('setup_token_hash')||is_file(Config::get('storage').'/installed.lock')){http_response_code(404);exit;}
Session::start();
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{Session::verify();}catch(Throwable){http_response_code(403);exit;}
    if(!hash_equals(Config::get('setup_token_hash'),hash('sha256',(string)($_POST['token']??'')))){http_response_code(403);exit;}
    $extensions=[];foreach(['pdo_mysql','mbstring','fileinfo','openssl','curl','zip']as$ext)$extensions[$ext]=extension_loaded($ext);
    $result=['php_version'=>PHP_VERSION,'required_php'=>'8.4+','runtime_version_ok'=>PHP_VERSION_ID>=80400,'timezone'=>date_default_timezone_get(),'extensions'=>$extensions,'storage_writable'=>is_writable(Config::get('storage')),'database'=>['connected'=>false]];
    try{$pdo=Db::pdo();$result['database']=['connected'=>true,'driver'=>$pdo->getAttribute(PDO::ATTR_DRIVER_NAME),'version'=>$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),'charset'=>$pdo->query('SELECT @@character_set_connection')->fetchColumn()];}catch(Throwable){$result['database']['error']='connection_failed';}
    header('Content-Type: application/json; charset=utf-8');echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
}
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="ro"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verificare găzduire OpenAliment</title><h1>Verificare găzduire</h1><form method="post"><?=csrf()?><label>Token de instalare <input name="token" type="password" required autocomplete="off"></label><button>Verifică mediul</button></form></html>
