<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Api,ApiInput,ApiRoutes,Auth,Http,Problem,Session};
use App\Domain\{AccountRecovery,Passkeys,ProducerSessions};

final class AccountAccessController {
    public static function matches(string $path): bool {return $path==='/recuperare'||$path==='/account/verify'||str_starts_with($path,'/api/v2/account/');}
    public static function handle(string $method,string $path): void {
        Auth::browserOnly();
        if($path==='/recuperare') {Api::method($method,['GET','HEAD']);Http::view('account-recovery',['title'=>'Recuperează accesul']);return;}
        if($path==='/account/verify') {Api::method($method,['GET','HEAD']);$user=Auth::user();if(!$user)Http::redirect('/login');Http::view('account-verify',['title'=>'Confirmă accesul','hasPasskeys'=>Passkeys::count($user['id'])>0]);return;}
        Api::method($method,['GET','HEAD','POST']);if(in_array($method,['GET','HEAD'],true)){
            if($path!=='/api/v2/account/security')throw new Problem(405,'method','Metodă nepermisă.');$user=Auth::user();if(!$user)throw new Problem(401,'authentication','Conectează-te la cont.');Passkeys::recent($user);
            Http::json(['sessions'=>ProducerSessions::list($user['id']),'current_session_id'=>$_SESSION['producer_session_id']??null,'passkeys'=>Passkeys::list($user['id']),'mfa_enabled'=>Passkeys::required($user)]);
        }
        Http::sameOrigin();$route=ApiRoutes::find('POST',$path);if(!$route)throw new Problem(404,'route','Adresa nu a fost găsită.');$data=Http::input(200000,['application/json']);ApiInput::validate($data,$route['body']);
        if($path==='/api/v2/account/password/request')Http::json(AccountRecovery::request(is_string($data['email']??null)?$data['email']:''));
        if(in_array($path,['/api/v2/account/password/preview','/api/v2/account/password/reset'],true)){
            if(!is_string($data['token']??null))throw new Problem(422,'confirmation','Legătură incorectă.');
            Http::json($path==='/api/v2/account/password/preview'?AccountRecovery::preview($data['token']):AccountRecovery::reset($data['token'],$data));
        }
        $user=Auth::user();if(!$user)throw new Problem(401,'authentication','Conectează-te la cont.');Session::verify();
        if(preg_match('~\A/api/v2/account/passkeys/(create|get)/(options|confirm)\z~D',$path,$m))Http::json($m[2]==='options'?Passkeys::begin($user,$m[1],$data):Passkeys::finish($user,$m[1],$data));
        if($path==='/api/v2/account/passkeys/recover')Http::json(Passkeys::recover($user,is_string($data['code']??null)?$data['code']:''));
        if($path==='/api/v2/account/passkeys/recovery-codes')Http::json(['recovery_codes'=>Passkeys::recoveryCodes($user)]);
        if(preg_match('~\A/api/v2/account/passkeys/([a-f0-9]{32})/remove\z~D',$path,$m))Http::json(Passkeys::remove($user,$m[1]));
        if(preg_match('~\A/api/v2/account/sessions/([a-f0-9]{32})/revoke\z~D',$path,$m)){Passkeys::recent($user);Http::json(ProducerSessions::revoke($user['id'],$m[1]));}
        throw new Problem(404,'route','Adresa nu a fost găsită.');
    }
}
