<?php
declare(strict_types=1);
namespace App\Core;
final class Auth {
    public static function user(): ?array {
        Session::start();
        if(($_SESSION['last_activity']??0)<time()-7200||($_SESSION['session_started_at']??0)<time()-28800){unset($_SESSION['user_id']);}
        if(empty($_SESSION['user_id']))return null;
        $u=Db::one('SELECT id,email,display_name,is_admin,email_verified,password_hash FROM users WHERE id=? AND active=1',[$_SESSION['user_id']]);
        if(!$u||!is_string($_SESSION['credential_version']??null)||!hash_equals($_SESSION['credential_version'],hash('sha256',$u['password_hash']))){unset($_SESSION['user_id']);return null;}
        if(!\App\Domain\ProducerSessions::valid($u['id'])){unset($_SESSION['user_id']);return null;}
        unset($u['password_hash']);$_SESSION['last_activity']=time();return $u;
    }
    public static function principal(string $scope='read'): array {
        $secret=Api::bearer();
        if($secret!==null){
            if(str_starts_with($secret,'oa_at_'))return \App\Domain\OAuth::producer($secret,$scope);
            $row=Db::one('SELECT t.*,m.role,u.is_admin FROM api_tokens t JOIN memberships m ON m.user_id=t.user_id AND m.tenant_id=t.tenant_id JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>? AND u.active=1', [hash('sha256',$secret),Db::now()]);
            if(!$row){header('WWW-Authenticate: Bearer realm="OpenAliment", error="invalid_token"');throw new Problem(401,'invalid_token','Cheia a expirat, a fost revocată sau nu este validă.');}
            $scopes=json_decode($row['scopes'],true);
            if($scope!==''&&!in_array($scope,$scopes,true))throw new Problem(403,'scope','Tokenul nu permite această operațiune.');
            if(Migrations::api())Db::run('UPDATE api_token_metadata SET last_used_at=? WHERE token_id=? AND (last_used_at IS NULL OR last_used_at<?)',[Db::now(),$row['id'],gmdate('Y-m-d H:i:s',time()-900)]);
            return ['tenant'=>$row['tenant_id'],'user'=>$row['user_id'],'role'=>$row['role'],'admin'=>false,'token'=>$row['id'],'scopes'=>$scopes,'auth_type'=>'api_key'];
        }
        $u=self::user();if(!$u)throw new Problem(401,'authentication','Autentificarea este necesară.');
        \App\Domain\Passkeys::full($u);
        $tenant=$_SESSION['tenant_id']??null;
        $m=$tenant?Db::one('SELECT * FROM memberships WHERE user_id=? AND tenant_id=?',[$u['id'],$tenant]):Db::one('SELECT * FROM memberships WHERE user_id=? ORDER BY tenant_id LIMIT 1',[$u['id']]);
        if(!$m)throw new Problem(403,'membership','Nu ai acces la un producător.');
        return ['tenant'=>$m['tenant_id'],'user'=>$u['id'],'role'=>$m['role'],'admin'=>(bool)$u['is_admin'],'token'=>null];
    }
    public static function browser(): array {
        self::browserOnly();$p=self::principal();Api::limit(($_SERVER['REQUEST_METHOD']??'GET')==='POST'?'private-write':'private-read',1,$p['user']);return $p;
    }
    public static function browserOnly(): void {if(Api::bearer()!==null)throw new Problem(403,'browser_session','Folosește sesiunea contului pentru această pagină. Integrările folosesc API-ul.');}
    public static function scope(array $p,string $scope): void {if(($p['token']??null)!==null&&!in_array($scope,$p['scopes']??[],true))throw new Problem(403,'scope','Tokenul nu permite această operațiune.');}
    public static function write(array $p): void {self::scope($p,'write');if(!in_array($p['role'],['owner','editor','quality'],true))throw new Problem(403,'role','Nu ai drept de modificare.');}
    public static function approve(array $p,string $scope='publish'): void {self::scope($p,$scope);if(!in_array($p['role'],['owner','quality'],true))throw new Problem(403,'role','Este necesar un responsabil autorizat.');}
    public static function publicWrite(array $p): void {
        self::approve($p);if(($p['token']??null)!==null&&!in_array('publish',$p['scopes']??[],true))throw new Problem(403,'scope','Publicarea necesită și permisiunea publish.');
    }
    public static function admin(): array {self::browserOnly();$u=self::user();if(!$u||!$u['is_admin'])throw new Problem(403,'role','Acces restricționat.');\App\Domain\Passkeys::full($u);return $u;}
    public static function rate(string $key,int $limit,int $seconds=60): void {
        $bucket=hash('sha256',$key.'|'.intdiv(time(),$seconds));
        Db::run('INSERT INTO rate_limits (id,hits,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1',[$bucket,gmdate('Y-m-d H:i:s',time()+$seconds)]);
        $r=Db::one('SELECT hits FROM rate_limits WHERE id=?',[$bucket]);if((int)$r['hits']>$limit)throw new Problem(429,'rate_limit','Prea multe cereri. Încearcă din nou mai târziu.');
    }
}
