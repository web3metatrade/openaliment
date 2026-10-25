<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Config,Db,Mailer,Problem};

/** Consumer identities never confer producer/administrator permissions. */
final class HomeIdentity {
    private const COOKIE='oa_home';
    public static function request(string $email): array {
        if(!Mailer::ready())throw new Problem(503,'email_unavailable','Conectarea prin email nu este disponibilă momentan. Alimentele rămân pe dispozitiv.');
        if(strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new Problem(422,'email','Verifică adresa de email.');$email=strtolower($email);
        Auth::rate('home-login-ip|'.\App\Core\Api::clientIp(),20,3600);Auth::rate('home-login-email|'.hash('sha256',$email),5,3600);
        \App\Core\SecurityBudget::check('login_emails_hour',300);
        $token=bin2hex(random_bytes(32));Db::insert('home_login_tokens',['token_hash'=>hash('sha256',$token),'email'=>$email,'expires_at'=>gmdate('Y-m-d H:i:s',time()+900),'created_at'=>Db::now()]);
        Mailer::loginLink($email,$token);return ['message'=>'Deschide legătura trimisă pe email. Este valabilă 15 minute.'];
    }
    public static function preview(string $token): array {
        $hash=self::tokenHash($token);$r=Db::one('SELECT email,expires_at FROM home_login_tokens WHERE token_hash=? AND expires_at>? AND used_at IS NULL',[$hash,Db::now()]);if(!$r)throw new Problem(410,'login_link','Legătura a expirat sau a fost folosită. Cere una nouă.');return $r;
    }
    public static function confirm(string $token,string $name='Dispozitivul meu'): array {
        $hash=self::tokenHash($token);$name=Catalog::text(['name'=>$name],'name',120);
        return Db::atomic(function()use($hash,$name){
            $challenge=Db::one('SELECT * FROM home_login_tokens WHERE token_hash=? FOR UPDATE',[$hash]);if(!$challenge||$challenge['used_at']!==null||$challenge['expires_at']<=Db::now())throw new Problem(410,'login_link','Legătura a expirat sau a fost folosită. Cere una nouă.');
            $newId=Db::id();Db::run('INSERT IGNORE INTO home_accounts (id,email,verified_at,created_at) VALUES (?,?,?,?)',[$newId,$challenge['email'],Db::now(),Db::now()]);
            $account=Db::one('SELECT * FROM home_accounts WHERE email=? FOR UPDATE',[$challenge['email']]);
            self::deviceQuota($account['id']);
            $household=Db::one('SELECT d.household_id AS id FROM home_devices d JOIN home_members m ON m.household_id=d.household_id AND m.account_id=d.account_id WHERE d.account_id=? AND d.revoked_at IS NULL ORDER BY d.last_seen_at DESC,d.created_at DESC,d.id LIMIT 1',[$account['id']]);
            $household??=Db::one('SELECT id FROM home_households WHERE owner_account_id=? ORDER BY created_at,id LIMIT 1',[$account['id']]);
            if(!$household){$household=['id'=>Db::id()];Db::insert('home_households',['id'=>$household['id'],'owner_account_id'=>$account['id'],'name'=>'Acasă','created_at'=>Db::now()]);Db::insert('home_members',['household_id'=>$household['id'],'account_id'=>$account['id'],'role'=>'owner','created_at'=>Db::now()]);}
            Db::run('UPDATE home_login_tokens SET used_at=? WHERE token_hash=?',[Db::now(),$hash]);
            $secret=bin2hex(random_bytes(32));$device=Db::id();Db::insert('home_devices',['id'=>$device,'account_id'=>$account['id'],'household_id'=>$household['id'],'name'=>$name,'token_hash'=>hash('sha256',$secret),'expires_at'=>gmdate('Y-m-d H:i:s',time()+90*86400),'last_seen_at'=>Db::now(),'created_at'=>Db::now()]);
            HomeAudit::record(['account_id'=>$account['id'],'household_id'=>$household['id']],'device.connected',$device);
            return ['secret'=>$secret,'device_id'=>$device,'account_id'=>$account['id'],'household_id'=>$household['id'],'email'=>$account['email']];
        });
    }
    public static function cookie(string $secret): void {setcookie(self::COOKIE,$secret,['expires'=>$secret===''?time()-3600:time()+90*86400,'path'=>'/api/v2/home/','secure'=>(bool)Config::get('session_secure',true),'httponly'=>true,'samesite'=>'Lax']);}
    public static function deviceQuota(string $account): void {
        Db::one('SELECT id FROM home_accounts WHERE id=? FOR UPDATE',[$account]);
        if((int)Db::one('SELECT COUNT(*) AS n FROM home_devices WHERE account_id=? AND revoked_at IS NULL AND expires_at>?',[$account,Db::now()])['n']>=100)throw new Problem(422,'device_quota','Deconectează un dispozitiv nefolosit înainte de a conecta altul.');
    }
    public static function principal(bool $required=true): ?array {
        $bearer=\App\Core\Api::bearer();
        if($bearer!==null){
            $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$scope=($_SERVER['REQUEST_METHOD']??'GET')==='POST'?'home:write':'home:read';
            if(in_array($path,['/api/v2/home/invites','/api/v2/home/rename'],true)||str_ends_with($path,'/remove'))$scope='home:manage';
            if($path==='/api/v2/home/logout'||preg_match('~\A/api/v2/home/devices/[a-f0-9]{32}/revoke\z~D',$path))$scope='';
            return OAuth::home($bearer,$scope);
        }
        $secret=$_COOKIE[self::COOKIE]??'';if(!is_string($secret)||!preg_match('/\A[a-f0-9]{64}\z/D',$secret)){$r=null;}else $r=Db::one('SELECT d.id AS device_id,d.account_id,d.household_id,d.last_seen_at,a.email,m.role,h.name AS household_name FROM home_devices d JOIN home_accounts a ON a.id=d.account_id JOIN home_members m ON m.account_id=d.account_id AND m.household_id=d.household_id JOIN home_households h ON h.id=d.household_id WHERE d.token_hash=? AND d.expires_at>? AND d.revoked_at IS NULL',[hash('sha256',$secret),Db::now()]);
        if(!$r){if($required)throw new Problem(401,'home_authentication','Conectează acest dispozitiv prin email.');return null;}
        $r['csrf']=hash_hmac('sha256','openaliment-home-csrf-v1',$secret);
        if($r['last_seen_at']<gmdate('Y-m-d H:i:s',time()-900)){Db::run('UPDATE home_devices SET last_seen_at=?,expires_at=? WHERE id=?',[Db::now(),gmdate('Y-m-d H:i:s',time()+90*86400),$r['device_id']]);self::cookie($secret);}
        return $r;
    }
    public static function csrf(array $p): void {if(isset($p['grant_id']))return;if(!is_string($_SERVER['HTTP_X_HOME_CSRF']??null)||!hash_equals($p['csrf'],$_SERVER['HTTP_X_HOME_CSRF']))throw new Problem(419,'home_csrf','Redeschide aplicația și încearcă din nou.');}
    public static function write(array $p): void {if(!in_array($p['role'],['owner','editor'],true))throw new Problem(403,'home_role','Nu ai drept de modificare în această familie.');}
    public static function devices(array $p): array {return Db::all('SELECT id,name,last_seen_at,created_at FROM home_devices WHERE account_id=? AND revoked_at IS NULL AND expires_at>? ORDER BY last_seen_at DESC LIMIT 100',[$p['account_id'],Db::now()]);}
    public static function revoke(array $p,string $id): array {return Db::atomic(function()use($p,$id){if(!Db::one('SELECT id FROM home_devices WHERE id=? AND account_id=?',[$id,$p['account_id']]))throw new Problem(404,'home_device','Dispozitivul nu a fost găsit.');Db::run('UPDATE home_devices SET revoked_at=? WHERE id=? AND account_id=?',[Db::now(),$id,$p['account_id']]);
        if(\App\Core\Migrations::api())foreach(Db::all('SELECT id FROM api_oauth_grants WHERE home_device_id=? AND home_account_id=?',[$id,$p['account_id']])as$grant)OAuth::revokeGrant($grant['id']);
        HomeAudit::record($p,'device.revoked',$id);if($p['device_id']===$id)self::cookie('');return ['revoked'=>true];});}
    private static function tokenHash(string $token): string {if(!preg_match('/\A[a-f0-9]{64}\z/D',$token))throw new Problem(410,'login_link','Legătura nu este validă.');return hash('sha256',$token);}
}
