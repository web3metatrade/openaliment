<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\Db;
use PDO;

/** Offline reconciliation against a separately retained, trusted latest erasure ledger. */
final class ErasureRestore {
    public static function apply(PDO $pdo,string $ledger,string $key,string $destination): array {
        if(PHP_SAPI!=='cli'||strlen($key)!==32||!is_file($ledger)||$pdo->inTransaction())throw new \RuntimeException('Offline erasure reconciliation requires a trusted ledger and application key.');
        $file=fopen($ledger,'rb');if(!$file)throw new \RuntimeException('Latest ledger unavailable.');$applied=0;$skipped=0;$conflicts=[];$seen=[];
        $one=static function(string $sql,array $params=[])use($pdo):?array{$s=$pdo->prepare($sql);$s->execute($params);return $s->fetch()?:null;};
        $run=static function(string $sql,array $params=[])use($pdo):void{$s=$pdo->prepare($sql);$s->execute($params);};
        $all=static function(string $sql,array $params=[])use($pdo):array{$s=$pdo->prepare($sql);$s->execute($params);return $s->fetchAll();};
        try{while(($line=fgets($file,1000001))!==false){if(strlen($line)>1000000||!str_ends_with($line,"\n"))throw new \RuntimeException('Incomplete erasure ledger.');$receipt=json_decode($line,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($receipt)||!preg_match('/\A[a-f0-9]{32}\z/D',$receipt['id']??'')||!preg_match('/\A[a-f0-9]{64}\z/D',$receipt['subject_sha256']??''))throw new \RuntimeException('Invalid erasure receipt.');
            if(isset($seen[$receipt['id']]))continue;$seen[$receipt['id']]=true;$account=$receipt['details']['account_anchor']??null;
            if(!is_string($account)||!preg_match('/\A[a-f0-9]{32}\z/D',$account)){$conflicts[]=['receipt_id'=>$receipt['id'],'reason'=>'account_anchor_missing'];continue;}
            $pdo->beginTransaction();try{
                $row=$one('SELECT email FROM home_accounts WHERE id=? FOR UPDATE',[$account]);if(!$row||str_ends_with($row['email'],'@deleted.invalid')){$pdo->rollBack();$skipped++;continue;}
                if(!hash_equals($receipt['subject_sha256'],hash_hmac('sha256',strtolower($row['email']),$key)))throw new \RuntimeException('Erasure subject does not match the restored application key.');
                foreach($receipt['details']['shared_households']??[]as$household){$id=$household['id']??'';$owner=$household['owner_account_id']??'';if(!preg_match('/\A[a-f0-9]{32}\z/D',$id)||!preg_match('/\A[a-f0-9]{32}\z/D',$owner)||$owner===$account)throw new \RuntimeException('Invalid ownership evidence.');
                    $current=$one('SELECT owner_account_id FROM home_households WHERE id=? FOR UPDATE',[$id]);if(!$current||$current['owner_account_id']!==$account)continue;
                    if(!$one('SELECT account_id FROM home_members WHERE household_id=? AND account_id=?',[$id,$owner]))throw new \DomainException('documented_owner_unavailable');
                    $run("UPDATE home_members SET role='editor' WHERE household_id=? AND account_id=?",[$id,$account]);$run("UPDATE home_members SET role='owner' WHERE household_id=? AND account_id=?",[$id,$owner]);$run('UPDATE home_households SET owner_account_id=? WHERE id=?',[$owner,$id]);
                }
                $owned=$all('SELECT id FROM home_households WHERE owner_account_id=? FOR UPDATE',[$account]);
                foreach($owned as$household)if($one('SELECT account_id FROM home_members WHERE household_id=? AND account_id<>? LIMIT 1',[$household['id'],$account]))throw new \DomainException('shared_household_requires_documented_owner');
                foreach($owned as$household){$id=$household['id'];$run('UPDATE api_oauth_grants SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()),home_device_id=NULL,household_id=NULL WHERE household_id=?',[$id]);
                    foreach($all('SELECT storage_key FROM home_photos WHERE household_id=?',[$id])as$photo)$run('INSERT IGNORE INTO private_file_deletions (id,storage_key,created_at) VALUES (?,?,UTC_TIMESTAMP())',[Db::id(),$photo['storage_key']]);
                    foreach(['home_changes','home_history','home_items','home_photos','home_invites','home_access_events','home_members','home_devices']as$table)$run('DELETE FROM '.$table.' WHERE household_id=?',[$id]);$run('DELETE FROM home_households WHERE id=?',[$id]);
                }
                $run('DELETE FROM home_invites WHERE created_by=? OR used_by=?',[$account,$account]);$run('DELETE FROM home_members WHERE account_id=?',[$account]);$run('DELETE FROM home_access_events WHERE account_id=?',[$account]);$run('DELETE FROM home_login_tokens WHERE email=?',[$row['email']]);$run('DELETE FROM account_challenges WHERE home_account_id=?',[$account]);
                $run('UPDATE api_oauth_grants SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()),home_account_id=NULL,home_device_id=NULL,household_id=NULL WHERE home_account_id=?',[$account]);
                $run("UPDATE home_devices SET name='Dispozitiv deconectat',revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()),token_hash=SHA2(CONCAT(id,?),256) WHERE account_id=?",[bin2hex(random_bytes(32)),$account]);
                $run('UPDATE home_accounts SET email=? WHERE id=?',['deleted-'.Db::id().'@deleted.invalid',$account]);$run('INSERT IGNORE INTO home_erasure_requests (id,subject_sha256,completed_at,details_json) VALUES (?,?,?,?)',[$receipt['id'],$receipt['subject_sha256'],$receipt['completed_at'],Db::json($receipt['details'])]);$pdo->commit();$applied++;
            }catch(\DomainException $error){$pdo->rollBack();$conflicts[]=['receipt_id'=>$receipt['id'],'reason'=>$error->getMessage()];}catch(\Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
        }}finally{fclose($file);}
        $private=realpath($destination.'/storage/private');$deleted=0;
        if($private)foreach($all('SELECT id,storage_key FROM private_file_deletions')as$row){if(!preg_match('~\Ahome/[a-f0-9]{32}/[a-f0-9]{32}\.jpg\z~D',$row['storage_key']))continue;$path=$private.'/'.$row['storage_key'];$real=realpath($path);if($real&&(!str_starts_with($real,$private.DIRECTORY_SEPARATOR)||is_link($path)))continue;if(!$real||@unlink($real)){$run('DELETE FROM private_file_deletions WHERE id=?',[$row['id']]);$deleted++;}}
        $result=['applied'=>$applied,'already_private'=>$skipped,'conflicts'=>$conflicts,'private_files_deleted'=>$deleted,'completed'=>$conflicts===[]];
        $reportPath=$destination.'/RESTORE_REPORT.json';$report=is_file($reportPath)?json_decode(file_get_contents($reportPath),true,32,JSON_THROW_ON_ERROR):[];$report['erasure_reconciliation']=$result;$report['requires_latest_erasure_ledger']=!$result['completed'];file_put_contents($reportPath,Db::json($report),LOCK_EX);@chmod($reportPath,0600);return $result;
    }
}
