<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Db,Migrations,Problem};

final class AccountChallenges {
    public static function ready(): void {if(!Migrations::readiness())throw new Problem(503,'schema_upgrade','Această operațiune nu este disponibilă momentan.');}
    public static function issue(string $purpose,?string $user,?string $home,array $payload=[],?string $session=null,int $seconds=900): string {
        self::ready();$token=bin2hex(random_bytes(32));
        Db::insert('account_challenges',['token_hash'=>hash('sha256',$token),'purpose'=>$purpose,'user_id'=>$user,'home_account_id'=>$home,'session_hash'=>$session,'payload_json'=>Db::json($payload),'expires_at'=>gmdate('Y-m-d H:i:s',time()+$seconds),'created_at'=>Db::now()]);return $token;
    }
    public static function read(string $token,string $purpose,bool $lock=false): array {
        self::ready();if(!preg_match('/\A[a-f0-9]{64}\z/D',$token))throw new Problem(410,'confirmation','Legătura nu mai este disponibilă.');
        $row=Db::one('SELECT * FROM account_challenges WHERE token_hash=?'.($lock?' FOR UPDATE':''),[hash('sha256',$token)]);
        if(!$row||$row['purpose']!==$purpose||$row['used_at']!==null||$row['expires_at']<=Db::now())throw new Problem(410,'confirmation','Legătura a expirat sau a fost folosită.');
        $row['payload']=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);return $row;
    }
    public static function consume(array $row): void {Db::run('UPDATE account_challenges SET used_at=? WHERE token_hash=? AND used_at IS NULL',[Db::now(),$row['token_hash']]);}
    public static function limit(string $purpose,string $identity): void {Auth::rate('challenge|'.$purpose.'|'.$identity,5,3600);}
}
