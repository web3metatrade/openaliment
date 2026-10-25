<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Mailer,Migrations,PrivateKey};

final class SecurityOutbox {
    public static function queue(string $user,string $event,string $subject,string $message): void {
        if(!Migrations::readiness())return;
        if((int)Db::one('SELECT COUNT(*) AS n FROM security_outbox WHERE user_id=? AND created_at>?',[$user,gmdate('Y-m-d H:i:s',time()-3600)])['n']>=20)return;
        Db::run('INSERT IGNORE INTO security_outbox (id,user_id,event_key,subject,message,available_at,created_at) VALUES (?,?,?,?,?,?,?)',[Db::id(),$user,substr($event,0,100),$subject,PrivateKey::seal($message),Db::now(),Db::now()]);
    }
    public static function notice(string $user,string $event,string $text): void {self::queue($user,$event.'-'.Db::id(),'Securitatea contului · OpenAliment',$text."\n\nVerifică accesurile contului: ".rtrim((string)Config::get('url'),'/')."/manage/security\n");}
    public static function work(int $limit=5): array {
        if(!Migrations::readiness()||!Mailer::ready())return ['sent'=>0,'available'=>false];$sent=0;$failed=0;
        if(!Db::one("SELECT GET_LOCK('oa_security_mail',0) AS acquired")['acquired'])return ['sent'=>0,'busy'=>true];
        try{foreach(Db::all('SELECT o.*,u.email FROM security_outbox o JOIN users u ON u.id=o.user_id WHERE o.sent_at IS NULL AND o.attempts<4 AND o.available_at<=? AND o.created_at>? AND u.active=1 ORDER BY o.created_at LIMIT '.max(1,min(10,$limit)),[Db::now(),gmdate('Y-m-d H:i:s',time()-86400)])as$row){
            if(str_starts_with($row['event_key'],'reset-')){$challenge=Db::one('SELECT expires_at,used_at FROM account_challenges WHERE token_hash=? AND user_id=? AND purpose=?',[substr($row['event_key'],6),$row['user_id'],'password_reset']);if(!$challenge||$challenge['used_at']!==null||$challenge['expires_at']<=Db::now()){Db::run('UPDATE security_outbox SET sent_at=?,message=? WHERE id=?',[Db::now(),'[expired]',$row['id']]);continue;}}
            Db::run('UPDATE security_outbox SET attempts=attempts+1,available_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()+300),$row['id']]);
            try{Mailer::securityMessage($row['email'],$row['subject'],PrivateKey::open($row['message']));Db::run('UPDATE security_outbox SET sent_at=?,message=? WHERE id=?',[Db::now(),'[delivered]',$row['id']]);$sent++;}catch(\Throwable){$failed++;}
        }}finally{Db::run("SELECT RELEASE_LOCK('oa_security_mail')");}return ['sent'=>$sent,'failed'=>$failed,'available'=>true];
    }
}
