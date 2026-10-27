<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header_remove('X-Powered-By');
try{require_once __DIR__.'/../app/bootstrap.php';}catch(\Throwable){http_response_code(503);header('Cache-Control: no-store');header('Content-Type: text/plain; charset=utf-8');header('X-Content-Type-Options: nosniff');header('Retry-After: 60');error_log('OpenAliment initialization unavailable.');echo 'Serviciul este temporar indisponibil.';exit;}
use App\Core\{Config,Http,Problem};
use App\Controllers\{ApiController,ManageController,PublicController,SetupController,AdminController,ResolverController};
$requestId=bin2hex(random_bytes(8));$method=$_SERVER['REQUEST_METHOD']??'GET';$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$_SERVER['OA_REQUEST_ID']=$requestId;
if(Config::get('environment')==='production'&&in_array(strtolower((string)($_SERVER['HTTPS']??'')),['on','1'],true))header('Strict-Transport-Security: max-age=86400');
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin');header('X-Frame-Options: DENY');header(str_starts_with($path,'/acasa/')?'Permissions-Policy: camera=(self), microphone=(), geolocation=()':'Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
if(str_starts_with($path,'/acasa/'))header("Content-Security-Policy: default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; worker-src 'self'; style-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store, max-age=0');header('X-Request-ID: '.$requestId);
if(Config::get('environment')!=='production')header('X-Robots-Tag: noindex, nofollow');
if($method==='HEAD')ob_start();
try {
    if(strlen($path)>4096)throw new Problem(414,'uri','Adresa este prea lungă.');
    \App\Core\ApiGateway::begin($method,$path);
    if(\App\Controllers\ExternalSourcesController::matches($path))\App\Controllers\ExternalSourcesController::web($method,$path);
    if(in_array($path,['/standarde/gs1','/admin/standards'],true)){\App\Controllers\Gs1StandardsController::web($method,$path);}
    elseif(\App\Controllers\MarketplaceController::matches($path)){\App\Controllers\MarketplaceController::web($method,$path);}
    elseif(\App\Controllers\AccountAccessController::matches($path)){\App\Controllers\AccountAccessController::handle($method,$path);}
    elseif(\App\Controllers\ApiDocumentationController::matches($path)){\App\Controllers\ApiDocumentationController::handle($method,$path);}
    elseif(in_array($path,['/oauth/authorize','/oauth/token','/oauth/revoke','/.well-known/oauth-authorization-server','/api/v2/home/oauth/authorize'],true)){\App\Controllers\OAuthController::handle($method,$path);}
    elseif(\App\Controllers\DiscoveryController::matches($path)){\App\Controllers\DiscoveryController::handle($method,$path);}
    elseif(\App\Core\ApiRoutes::find($method==='HEAD'?'GET':$method,$path)){\App\Controllers\PlatformApiController::handle($method,$path);}
    elseif(ResolverController::matches($path))ResolverController::handle($method,$path);
    elseif(str_starts_with($path,'/api/v2/manage/'))\App\Controllers\IntegrationController::handle($method==='HEAD'?'GET':$method,$path);
    elseif($path==='/api/v2/home/receipts/preview')\App\Controllers\ReceiptController::handle($method);
    elseif(str_starts_with($path,'/api/v2/home/'))\App\Controllers\HomeController::handle($method,$path);
    elseif($path==='/acasa'||str_starts_with($path,'/acasa/')||in_array($path,['/api/v2/public/food','/api/v2/public/capabilities'],true))\App\Controllers\ConsumerController::handle($method,$path);
    elseif(str_starts_with($path,'/api/v1/'))ApiController::handle($method==='HEAD'?'GET':$method,$path);
    elseif($path==='/setup')SetupController::handle($method);
    elseif(str_starts_with($path,'/admin'))AdminController::handle($method,$path);
    elseif(str_starts_with($path,'/manage')&&!str_starts_with($path,'/manage/documents/'))ManageController::handle($method,$path);
    else PublicController::handle($method,$path);
}catch(\Throwable $e){
    $status=$e instanceof Problem?$e->status:503;$code=$e instanceof Problem?$e->errorCode:'service_unavailable';$message=$e instanceof Problem?$e->getMessage():'Serviciul este temporar indisponibil. Încearcă din nou mai târziu.';
    if(in_array($status,[401,403,419,429],true))\App\Core\SecurityLog::record('request.denied',['code'=>$code,'status'=>$status]);
    if(!$e instanceof Problem){$log=Config::get('storage').'/logs';if(is_dir($log))error_log(gmdate(DATE_ATOM).' '.$requestId.' '.get_class($e).' code='.$e->getCode().PHP_EOL,3,$log.'/application.log');}
    if($status===405&&!array_filter(headers_list(),static fn($h)=>str_starts_with(strtolower($h),'allow:')))header('Allow: GET, HEAD, POST');if($status===429&&!array_filter(headers_list(),static fn($h)=>str_starts_with(strtolower($h),'retry-after:')))header('Retry-After: 60');
    if(str_starts_with($path,'/oauth/')&&$path!=='/oauth/authorize')Http::json(['error'=>$code,'error_description'=>$message,'request_id'=>$requestId],$status);
    if(str_starts_with($path,'/api/'))Http::json(['error_code'=>$code,'message'=>$message,'request_id'=>$requestId],$status);
    if($code==='mfa_required')Http::redirect('/account/verify');
    if($status===401){Http::redirect('/login');}
    Http::view('message',['title'=>$status===404?'Pagina nu a fost găsită':'Nu am putut finaliza cererea','message'=>$message,'requestId'=>$requestId],$status);
}
if($method==='HEAD')ob_end_clean();
