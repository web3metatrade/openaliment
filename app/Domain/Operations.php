<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Mailer,Migrations};

final class Operations {
    public static function record(string $key,string $state,array $details=[]): void {
        if(!Migrations::readiness())return;
        Db::run('INSERT INTO operation_checks (check_key,state,checked_at,details_json) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE state=VALUES(state),checked_at=VALUES(checked_at),details_json=VALUES(details_json)',[$key,$state,Db::now(),Db::json($details)]);
    }
    public static function status(): array {
        $checks=Migrations::readiness()?Db::all('SELECT check_key,state,checked_at,details_json FROM operation_checks ORDER BY check_key'):[];
        foreach($checks as&$check){$check['age_seconds']=max(0,time()-strtotime($check['checked_at'].' UTC'));$check['details']=json_decode($check['details_json'],true)?:new \stdClass();unset($check['details_json']);if($check['check_key']==='worker'&&$check['age_seconds']>180)$check['state']='stale';}unset($check);
        $ddl=null;try{$ddl=false;foreach(Db::all('SHOW GRANTS FOR CURRENT_USER()')as$grant)if(preg_match('/\b(?:ALL PRIVILEGES|CREATE|ALTER|DROP|GRANT OPTION)\b/i',implode(' ',$grant)))$ddl=true;}catch(\Throwable){}
        return ['php'=>['version'=>PHP_VERSION,'minimum'=>'8.4','supported_runtime'=>PHP_VERSION_ID>=80400],
            'extensions'=>array_combine(['pdo_mysql','mbstring','fileinfo','openssl','zip'],array_map('extension_loaded',['pdo_mysql','mbstring','fileinfo','openssl','zip'])),
            'filesystem_free_bytes'=>@disk_free_space((string)Config::get('storage'))?:null,'account_quota_observed'=>false,
            'runtime_database_ddl_privileges'=>$ddl,'migration_connection_configured'=>Db::migrationsConfigured(),
            'smtp_configured'=>Mailer::ready(),'document_scanner_configured'=>(Config::get('document_scanner',[])['transport']??'disabled')!=='disabled',
            'checks'=>$checks,'readiness_installed'=>Migrations::readiness(),'backup_external_verified'=>self::verified('backup.external'),
            'backup_configured'=>is_file(ROOT.'/config/backup.local.php'),
            'pending_private_file_deletions'=>Migrations::readiness()?(int)Db::one('SELECT COUNT(*) AS n FROM private_file_deletions')['n']:null];
    }
    private static function verified(string $key): bool {return Migrations::readiness()&&Db::one("SELECT check_key FROM operation_checks WHERE check_key=? AND state='verified'",[$key])!==null;}
    public static function worker(bool $withAi=true): array {
        if(!Db::one("SELECT GET_LOCK('oa_bounded_worker',0) AS acquired")['acquired'])return ['state'=>'busy'];
        try{self::record('worker','running');$maintenance=Maintenance::run();$mail=SecurityOutbox::work(1);$scans=DocumentScanner::work(1);$files=HomePrivacy::purgeFiles();HomePrivacy::exportLedger();$ai=$withAi?Ai::work():['state'=>'maintenance_only'];
            $counts=['maintenance'=>$maintenance['state'],'security_mail'=>$mail,'document_scans'=>$scans,'private_files'=>$files,'ai'=>$ai];self::record('worker','completed',$counts);return ['state'=>'completed']+$counts;
        }catch(\Throwable $error){self::record('worker','failed',['error'=>'worker_failed']);throw $error;}
        finally{Db::run("SELECT RELEASE_LOCK('oa_bounded_worker')");}
    }
}
