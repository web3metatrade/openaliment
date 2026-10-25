<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Api,Auth,Config,Db,Http,Problem,Session};
use App\Domain\{OAuth,HomeIdentity,HomeHouseholds};

final class OAuthController {
    public static function handle(string $method,string $path): void {
        header('Cache-Control: no-store');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');
        if($path==='/.well-known/oauth-authorization-server'){
            Api::publicCors();if($method==='OPTIONS'){http_response_code(204);return;}Api::method($method,['GET','HEAD','OPTIONS']);
            Http::json(['issuer'=>rtrim(Config::get('url'),'/'),'authorization_endpoint'=>url('/oauth/authorize'),'token_endpoint'=>url('/oauth/token'),'revocation_endpoint'=>url('/oauth/revoke'),'response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],'code_challenge_methods_supported'=>['S256'],'token_endpoint_auth_methods_supported'=>['none'],'scopes_supported'=>OAuth::SCOPES]);
        }
        Api::installed();
        if(in_array($path,['/oauth/token','/oauth/revoke'],true)){
            header('Access-Control-Allow-Origin: *');header('Access-Control-Allow-Methods: POST, OPTIONS');header('Access-Control-Allow-Headers: Content-Type');
            if($method==='OPTIONS'){http_response_code(204);return;}Api::method($method,['POST','OPTIONS']);Api::limit('auth');$d=Http::input(8192);
            if($path==='/oauth/token'){try{Http::json(OAuth::token($d));}catch(Problem $error){if($error->status===422)throw new Problem(400,'invalid_request',$error->getMessage());throw $error;}}
            $client=$d['client_id']??'';$token=$d['token']??'';
            if(!is_string($client)||!is_string($token)||strlen($token)>180)throw new Problem(400,'invalid_request','Cerere de revocare incorectă.');
            OAuth::revoke($client,$token);http_response_code(200);return;
        }
        Api::method($method,['GET','POST']);Api::limit('auth');Session::start();
        if($method==='POST'){Http::sameOrigin();Session::verify();$request=OAuth::pending(Api::id((string)($_POST['request_id']??'')));}
        else $request=isset($_GET['request'])?OAuth::pending(Api::id((string)$_GET['request'])):OAuth::request($_GET);
        if($request['realm']==='home'&&$path!=='/api/v2/home/oauth/authorize'){Http::redirect('/api/v2/home/oauth/authorize?request='.$request['id']);return;}
        if($request['realm']==='producer'&&$path!=='/oauth/authorize'){Http::redirect('/oauth/authorize?request='.$request['id']);return;}
        if($request['realm']==='home'){
            if(Api::bearer()!==null)throw new Problem(403,'consent_session','Confirmă accesul din contul personal în browser.');
            $principal=HomeIdentity::principal(false);$resources=$principal?HomeHouseholds::list($principal):[];
            if(!$principal)$_SESSION['home_oauth_return']=$request['id'];
            $login='/acasa/#oauth='.$request['id'];
        }else{
            $principal=Auth::user();if($principal)\App\Domain\Passkeys::full($principal);$resources=$principal?Db::all('SELECT o.id,o.display_name AS name,m.role FROM memberships m JOIN organizations o ON o.id=m.tenant_id WHERE m.user_id=? ORDER BY o.id',[$principal['id']]):[];
            $_SESSION['oauth_return']='/oauth/authorize?request='.$request['id'];$login='/login';
        }
        if($method==='POST'){
            if(!$principal)throw new Problem(401,'authentication','Conectează-te înainte de autorizare.');
            $resource=(string)($_POST['resource_id']??'');$approved=($_POST['decision']??'')==='approve';
            $location=OAuth::consent($request,$principal,$resource,$approved);header('Location: '.$location,true,303);return;
        }
        Http::view('oauth-consent',['title'=>'Autorizează aplicația','request'=>$request,'principal'=>$principal,'resources'=>$resources,'loginUrl'=>$login,'scopes'=>json_decode($request['scopes_json'],true)]);
    }
}
