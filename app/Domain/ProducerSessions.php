<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Db,Migrations,Problem,Session};

final class ProducerSessions {
    public static function attach(string $user,bool $verified=false): void {
        if(!Migrations::readiness())return;$hash=hash('sha256',session_id());
        $id=Db::id();$agent=(string)($_SERVER['HTTP_USER_AGENT']??'');$name=str_contains($agent,'Android')?'Android':(str_contains($agent,'iPhone')?'iPhone':(str_contains($agent,'Windows')?'Windows':'Browser'));
        Db::insert('producer_sessions',['id'=>$id,'session_hash'=>$hash,'user_id'=>$user,'device_name'=>$name,'authenticated_at'=>Db::now(),'verified_at'=>$verified?Db::now():null,'last_seen_at'=>Db::now(),'expires_at'=>gmdate('Y-m-d H:i:s',time()+28800),'created_at'=>Db::now()]);$_SESSION['producer_session_id']=$id;
    }
    public static function valid(string $user): bool {
        if(!Migrations::readiness())return true;
        if(empty($_SESSION['producer_session_id']))self::attach($user,false);
        $row=Db::one('SELECT id,last_seen_at,verified_at FROM producer_sessions WHERE id=? AND session_hash=? AND user_id=? AND revoked_at IS NULL AND expires_at>?',[$_SESSION['producer_session_id'],hash('sha256',session_id()),$user,Db::now()]);
        if(!$row)return false;if($row['last_seen_at']<gmdate('Y-m-d H:i:s',time()-300))Db::run('UPDATE producer_sessions SET last_seen_at=? WHERE id=?',[Db::now(),$row['id']]);
        $_SESSION['mfa_verified_at']=$row['verified_at']===null?0:strtotime($row['verified_at'].' UTC');return true;
    }
    public static function list(string $user): array {if(!Migrations::readiness())return [];return Db::all('SELECT id,device_name,authenticated_at,verified_at,last_seen_at,created_at FROM producer_sessions WHERE user_id=? AND revoked_at IS NULL AND expires_at>? ORDER BY last_seen_at DESC LIMIT 100',[$user,Db::now()]);}
    public static function endCurrent(): void {if(Migrations::readiness()&&isset($_SESSION['producer_session_id']))Db::run('UPDATE producer_sessions SET revoked_at=COALESCE(revoked_at,?) WHERE id=? AND session_hash=?',[Db::now(),$_SESSION['producer_session_id'],hash('sha256',session_id())]);}
    public static function revoke(string $user,string $id): array {
        if(!preg_match('/\A[a-f0-9]{32}\z/D',$id)||!Db::one('SELECT id FROM producer_sessions WHERE user_id=? AND id=?',[$user,$id]))throw new Problem(404,'session','Sesiunea nu a fost găsită.');
        Db::run('UPDATE producer_sessions SET revoked_at=COALESCE(revoked_at,?) WHERE id=? AND user_id=?',[Db::now(),$id,$user]);return ['revoked'=>true];
    }
}
