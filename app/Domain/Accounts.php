<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Config,Db,Passwords,Problem,Session};
final class Accounts {
    public static function register(array $d,bool $admin=false): array {
        if(!$admin&&!RegistrationSettings::enabled())throw new Problem(403,'registration','Înscrierile sunt disponibile prin administrator.');
        if(!$admin)\App\Core\SecurityBudget::check('registrations_hour',100);
        $email=strtolower(Catalog::text($d,'email',190));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new Problem(422,'email','Adresa de email este incorectă.');
        $password=Passwords::validate($d['password']??null);
        if(Db::one('SELECT id FROM users WHERE email=?',[$email]))throw new Problem(409,'account','Contul nu poate fi creat cu aceste date.');
        $lookup=$admin?null:OrganizationRegistry::registration($d);
        $organization=$lookup?mb_substr(trim($lookup['record']['date_generale']['denumire']),0,180):Catalog::text($d,'organization');
        return Db::atomic(function()use($d,$admin,$email,$password,$lookup,$organization){$u=Db::id();$t=Db::id();Db::insert('organizations',['id'=>$t,'display_name'=>$organization,'created_at'=>Db::now()]);Db::insert('users',['id'=>$u,'email'=>$email,'display_name'=>Catalog::text($d,'display_name',120),'password_hash'=>Passwords::hash($password),'is_admin'=>$admin?1:0,'created_at'=>Db::now()]);Db::insert('memberships',['tenant_id'=>$t,'user_id'=>$u,'role'=>'owner']);$result=['id'=>$u,'tenant_id'=>$t];if($lookup)OrganizationRegistry::attachRegistration($result,$lookup);return $result;});
    }
    public static function login(array $d): void {
        Auth::rate('login-ip|'.\App\Core\Api::clientIp(),20,900);$email=strtolower(Catalog::text($d,'email',190));Auth::rate('login-email|'.$email,10,900);
        $u=Db::one('SELECT * FROM users WHERE email=? AND active=1',[$email]);$hash=$u['password_hash']??null;
        if(!Passwords::verify($d['password']??null,$hash)||!$u){\App\Core\SecurityLog::record('login.failed');throw new Problem(401,'login','Email sau parolă incorecte.');}
        if(Passwords::needsUpgrade($hash,$d['password']))Db::run('UPDATE users SET password_hash=? WHERE id=? AND password_hash=?',[Passwords::hash($d['password']),$u['id'],$hash]);Session::login($u['id']);
        $_SESSION['password_update_recommended']=str_starts_with($hash,'$2')&&strlen($d['password'])>72;\App\Core\SecurityLog::record('login.succeeded',['user_id'=>$u['id']]);
    }
    public static function token(array $p,array $d): array {
        if($p['role']!=='owner'||$p['token']!==null)throw new Problem(403,'role','Numai administratorul organizației poate crea tokenuri din cont.');
        $scopes=$d['scopes']??['read'];if(!is_array($scopes)||!array_is_list($scopes)||!$scopes)throw new Problem(422,'scopes','Permisiuni incorecte.');
        foreach($scopes as$scope)if(!is_string($scope)||!in_array($scope,['read','write','publish','alerts','export','import','community','sharing:manage','sharing:write'],true))throw new Problem(422,'scopes','Permisiuni incorecte.');
        return Db::atomic(function()use($p,$d,$scopes){
        Db::one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$p['tenant']]);
        if((int)Db::one('SELECT COUNT(*) AS n FROM api_tokens WHERE tenant_id=? AND revoked_at IS NULL AND expires_at>?',[$p['tenant'],Db::now()])['n']>=30)throw new Problem(422,'key_quota','Revocă o cheie nefolosită înainte de a crea alta. Limita este de 30 de chei active.');
        $days=(int)($d['days']??90);if($days<1||$days>365)throw new Problem(422,'expiry','Valabilitatea este între 1 și 365 de zile.');$token='oa_'.bin2hex(random_bytes(32));$id=Db::id();
        $expires=gmdate('Y-m-d H:i:s',time()+86400*$days);
        Db::insert('api_tokens',['id'=>$id,'tenant_id'=>$p['tenant'],'user_id'=>$p['user'],'name'=>Catalog::text($d,'name',120),'token_hash'=>hash('sha256',$token),'scopes'=>Db::json(array_values(array_unique($scopes))),'expires_at'=>$expires,'created_at'=>Db::now()]);
        if(\App\Core\Migrations::api())Db::insert('api_token_metadata',['token_id'=>$id,'prefix'=>substr($token,0,11)]);
        Catalog::audit($p,'token.created',$id,['scopes'=>$scopes]);return ['id'=>$id,'token'=>$token,'expires_at'=>$expires,'scopes'=>array_values(array_unique($scopes))];
        });
    }
    public static function secure(array $p,array $data): void {
        if(($p['token']??null)!==null)throw new Problem(403,'browser_session','Folosește sesiunea contului.');
        $identity=Db::one('SELECT id,is_admin FROM users WHERE id=? AND active=1',[$p['user']]);if($identity&&Passkeys::required($identity))Passkeys::recent($identity);
        Auth::rate('account-security|'.$p['user'],5,900);
        $action=$data['action']??'';if(!in_array($action,['password','revoke'],true))throw new Problem(422,'action','Operațiune incorectă.');
        Db::atomic(function()use($p,$data,$action){
            $u=Db::one('SELECT password_hash FROM users WHERE id=? AND active=1 FOR UPDATE',[$p['user']]);
            if(!$u||!Passwords::verify($data['current_password']??null,$u['password_hash']))throw new Problem(403,'reauthentication','Parola actuală nu este corectă.');
            $new=$action==='password'?Passwords::validate($data['new_password']??null):Passwords::validate($data['current_password']);
            if($action==='password'&&(!is_string($data['confirmation']??null)||!hash_equals($new,$data['confirmation'])))throw new Problem(422,'password_confirmation','Parolele noi nu coincid.');
            if($action==='revoke'&&str_starts_with($u['password_hash'],'$2')&&strlen($new)>72)throw new Problem(422,'password','Alege mai întâi o parolă nouă pentru acest cont.');
            Db::run('UPDATE users SET password_hash=? WHERE id=?',[Passwords::hash($new),$p['user']]);
            if(\App\Core\Migrations::readiness())Db::run('UPDATE producer_sessions SET revoked_at=COALESCE(revoked_at,?) WHERE user_id=?',[Db::now(),$p['user']]);
            Db::run('UPDATE api_tokens SET revoked_at=COALESCE(revoked_at,?) WHERE user_id=?',[Db::now(),$p['user']]);
            if(\App\Core\Migrations::api())foreach(Db::all('SELECT id FROM api_oauth_grants WHERE user_id=? AND revoked_at IS NULL',[$p['user']])as$g)OAuth::revokeGrant($g['id']);
            if($p['tenant']??null)Catalog::audit($p,$action==='password'?'account.password_changed':'account.access_revoked',$p['user']);
            elseif(\App\Core\Migrations::program())ProgramAccess::event(['id'=>$p['user']],'account',$p['user'],$action==='password'?'password_changed':'access_revoked');
        });
        $verified=!empty($_SESSION['mfa_verified_at']);Session::login($p['user'],$verified);SecurityOutbox::notice($p['user'],'access-revoked','Parola sau accesurile contului au fost actualizate.');\App\Core\SecurityLog::record('account.access_revoked',['user_id'=>$p['user']]);
    }
}
