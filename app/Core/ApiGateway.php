<?php
declare(strict_types=1);
namespace App\Core;
use App\Domain\OAuth;

final class ApiGateway {
    public static function begin(string $method,string $path): void {
        if(!str_starts_with($path,'/api/'))return;
        foreach(['api_key','access_token','authorization','token']as$key)if(isset($_GET[$key]))throw new Problem(400,'credential_in_url','Credentialele se trimit în antet, nu în adresă.');
        if($method==='OPTIONS')Api::limit('public');
        $read=$method==='HEAD'?'GET':$method;$route=ApiRoutes::find($read,$path);
        $docs=in_array($path,['/api/manifest.json','/api/guide.md','/api/sdk/python','/api/sdk/javascript','/api/sdk/kotlin'],true)||str_ends_with($path,'openapi.json')||str_starts_with($path,'/api/schemas/')||\App\Controllers\ApiDocumentationController::matches($path);
        $legacyPublic=preg_match('~\A/api/v1/(?:products(?:/[a-f0-9]{32})?|trade-items/[a-f0-9]{32}|batches/[a-f0-9]{32}|recipe-versions/[a-f0-9]{32}|feedback(?:/[a-f0-9]{32}/status)?)\z~D',$path);
        $public=$docs||($route&&$route['auth']==='public')||$legacyPublic||in_array($path,['/api/v2/public/food','/api/v2/public/capabilities','/api/v2/public/status'],true);
        if($path==='/api/v2/identity/session')$public=false;
        if($public){
            Api::publicCors();$allowed=$route&&$route['auth']==='public'?implode(', ',ApiRoutes::methods($path)):(in_array($path,['/api/v1/feedback'],true)?'POST, OPTIONS':'GET, HEAD, OPTIONS');
            header('Access-Control-Allow-Methods: '.$allowed);header('Access-Control-Allow-Headers: Content-Type, X-Status-Token, Idempotency-Key');
            Api::method($method,explode(', ',$allowed));
        }else self::cors($method);
        if($method==='OPTIONS'){
            if(!$public&&!self::registeredOrigin($_SERVER['HTTP_ORIGIN']??''))throw new Problem(403,'origin','Originea nu este înregistrată.');
            header('Allow: '.($public?$allowed:'GET, HEAD, POST, PUT, DELETE, OPTIONS'));http_response_code(204);exit;
        }
        $methods=ApiRoutes::methods($path);if($methods)Api::method($method,$methods);
        Api::limit($docs?'docs':'public');
    }
    private static function registeredOrigin(string $origin): bool {
        if($origin===rtrim((string)Config::get('url'),'/'))return true;
        if(in_array($origin,Config::get('api_allowed_origins',[]),true))return true;
        return $origin!==''&&Migrations::api()&&Db::one('SELECT o.client_id FROM api_client_origins o JOIN api_clients c ON c.id=o.client_id AND c.active=1 WHERE o.origin=? LIMIT 1',[$origin])!==null;
    }
    private static function cors(string $method): void {
        $origin=$_SERVER['HTTP_ORIGIN']??null;if($origin===null||$origin===rtrim((string)Config::get('url'),'/'))return;
        if(!self::registeredOrigin($origin))throw new Problem(403,'origin','Originea aplicației nu este înregistrată.');
        if($method!=='OPTIONS'){
            $secret=Api::bearer();if($secret===null)throw new Problem(403,'origin','Accesul extern necesită un token Bearer delegat.');
            if(str_starts_with($secret,'oa_at_')){
                $row=Db::one('SELECT g.client_id FROM api_oauth_tokens t JOIN api_oauth_grants g ON g.id=t.grant_id WHERE t.access_hash=?',[hash('sha256',$secret)]);
                if(!$row||!Db::one('SELECT client_id FROM api_client_origins WHERE client_id=? AND origin=?',[$row['client_id'],$origin]))throw new Problem(403,'origin','Originea nu corespunde aplicației autorizate.');
            }elseif(!in_array($origin,Config::get('api_allowed_origins',[]),true))throw new Problem(403,'origin','Cheile ERP nu sunt permise acestui frontend.');
        }
        header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin',false);
        header('Access-Control-Allow-Methods: GET, HEAD, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Idempotency-Key');
        header('Access-Control-Expose-Headers: X-Request-ID, RateLimit-Limit, RateLimit-Remaining, RateLimit-Reset, Retry-After');
    }
}
