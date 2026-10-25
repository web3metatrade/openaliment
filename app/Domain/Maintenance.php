<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Migrations,RateLimit,SecurityLog};

/** Bounded housekeeping, independent of AI and of all public QR requests. */
final class Maintenance {
    public static function run(int $batch=200): array {
        $batch=max(1,min(500,$batch));$acquired=Db::one("SELECT GET_LOCK('openaliment_security_cleanup',0) AS acquired");
        if(!$acquired['acquired'])return ['state'=>'busy'];
        try {
            $counts=[];$old=gmdate('Y-m-d H:i:s',time()-7*86400);$now=Db::now();
            $counts['rate_limits']=Db::run('DELETE FROM rate_limits WHERE expires_at<? LIMIT '.$batch,[$now])->rowCount();
            if(Migrations::home()){
                $counts['home_login_tokens']=Db::run('DELETE FROM home_login_tokens WHERE expires_at<? LIMIT '.$batch,[$old])->rowCount();
                if(Migrations::security())$counts['home_invites']=Db::run('DELETE FROM home_invites WHERE expires_at<? LIMIT '.$batch,[$old])->rowCount();
            }
            if(Migrations::api()){
                // Retain rotated/used token verifiers for the ENTIRE live grant so
                // refresh replay continues to revoke the grant after housekeeping.
                $counts['oauth_tokens']=Db::run('DELETE FROM api_oauth_tokens WHERE grant_id IN (SELECT id FROM api_oauth_grants WHERE expires_at<?) LIMIT '.$batch,[$old])->rowCount();
                $counts['oauth_codes']=Db::run('DELETE FROM api_oauth_codes WHERE expires_at<? LIMIT '.$batch,[$old])->rowCount();
                $counts['oauth_requests']=Db::run('DELETE FROM api_oauth_requests WHERE expires_at<? AND NOT EXISTS (SELECT 1 FROM api_oauth_codes c WHERE c.request_id=api_oauth_requests.id) LIMIT '.$batch,[$old])->rowCount();
            }
            if(Migrations::security()){
                // No product/batch history, audit, imports, connectors or receipts are erased here.
                $counts['idempotency_keys']=Db::run('DELETE FROM idempotency_keys WHERE created_at<? LIMIT '.$batch,[gmdate('Y-m-d H:i:s',time()-90*86400)])->rowCount();
            }
            if(Migrations::readiness()){
                $counts['account_challenges']=Db::run('DELETE FROM account_challenges WHERE expires_at<? LIMIT '.$batch,[$old])->rowCount();
                $counts['producer_sessions']=Db::run('DELETE FROM producer_sessions WHERE expires_at<? LIMIT '.$batch,[$old])->rowCount();
                $counts['security_outbox']=Db::run('DELETE FROM security_outbox WHERE created_at<? LIMIT '.$batch,[$old])->rowCount();
                $sessionDirectory=rtrim((string)Config::get('storage'),'/\\').'/private/sessions';$removed=0;
                if(is_dir($sessionDirectory))foreach(new \DirectoryIterator($sessionDirectory)as$file){if($removed>=$batch)break;if($file->isFile()&&!$file->isLink()&&preg_match('/\Asess_[A-Za-z0-9,-]+\z/D',$file->getFilename())&&$file->getMTime()<time()-86400){$handle=@fopen($file->getPathname(),'r+');if($handle){if(flock($handle,LOCK_EX|LOCK_NB)){if(@unlink($file->getPathname()))$removed++;flock($handle,LOCK_UN);}fclose($handle);}}}
                $counts['private_sessions']=$removed;
            }
            $counts['file_rates']=RateLimit::cleanup();$counts['security_logs']=SecurityLog::cleanup();
            $counts['public_archives']=PublicArchive::cleanup();
            $result=['state'=>'completed','batch_limit'=>$batch,'deleted'=>$counts];
            if(Migrations::security())Db::run('INSERT INTO security_maintenance (id,completed_at,result_json) VALUES (1,?,?) ON DUPLICATE KEY UPDATE completed_at=VALUES(completed_at),result_json=VALUES(result_json)',[Db::now(),Db::json($result)]);
            SecurityLog::record('maintenance.completed');return $result;
        }finally{Db::run("SELECT RELEASE_LOCK('openaliment_security_cleanup')");}
    }
    public static function status(): ?array {return Migrations::security()?Db::one('SELECT completed_at,result_json FROM security_maintenance WHERE id=1'):null;}
}
