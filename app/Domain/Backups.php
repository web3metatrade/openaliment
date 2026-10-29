<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Problem};
use ZipArchive;

/** Offline CLI backups. No archive, password or database dump is served over HTTP. */
final class Backups {
    public static function create(string $directory,string $password): array {
        self::password($password);\App\Core\PrivateKey::key();if(Db::pdo()->inTransaction())throw new \LogicException('Backup requires its own transaction.');
        $directory=self::privateDirectory($directory);$id=gmdate('Ymd-His').'-'.Db::id();$stage=$directory.'/.stage-'.$id;
        if(!mkdir($stage,0700))throw new \RuntimeException('Backup staging unavailable.');$target=$directory.'/openaliment-'.$id.'.zip';$temporary=$target.'.partial';$files=[];$manifest=['schema_version'=>'openaliment.backup.v1','created_at'=>gmdate(DATE_ATOM),'release'=>json_decode(file_get_contents(ROOT.'/release.json'),true),'tables'=>[],'entries'=>[],'access_restoration'=>'all_server_sessions_and_tokens_revoked','erasure_reconciliation'=>'requires_latest_erasure_ledger'];
        $pdo=Db::pdo();$archive=new ZipArchive();$locked=false;$schemaLocked=false;
        try{
            $locked=(bool)Db::one("SELECT GET_LOCK('oa_backup',0) AS acquired")['acquired'];if(!$locked)throw new Problem(409,'backup_busy','O copie de siguranță este deja în curs.');
            $schemaLocked=(bool)Db::one("SELECT GET_LOCK('openaliment_schema',0) AS acquired")['acquired'];if(!$schemaLocked)throw new Problem(409,'migration_busy','O actualizare este în curs.');
            if($archive->open($temporary,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new \RuntimeException('Backup archive unavailable.');
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $tables=Db::all('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=? ORDER BY TABLE_NAME',['BASE TABLE']);
            foreach($tables as$table){$name=$table['TABLE_NAME'];if(!preg_match('/\A[a-z][a-z0-9_]*\z/D',$name)||strtoupper((string)$table['ENGINE'])!=='INNODB')throw new \RuntimeException('Backup requires known InnoDB tables.');
                $schema=Db::one('SHOW CREATE TABLE `'.$name.'`');$ddl=array_values($schema)[1];$manifest['tables'][$name]=['ddl'=>$ddl,'rows'=>0];$file=$stage.'/'.$name.'.ndjson';$handle=fopen($file,'xb');if(!$handle)throw new \RuntimeException('Backup staging write failed.');@chmod($file,0600);
                try{
                    $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);$statement=$pdo->query('SELECT * FROM `'.$name.'`');
                    try{while($row=$statement->fetch()){$line=Db::json($row)."\n";if(fwrite($handle,$line)!==strlen($line))throw new \RuntimeException('Backup dump write failed.');$manifest['tables'][$name]['rows']++;
                        if(in_array($name,['documents','home_photos'],true)&&isset($row['storage_key'])){if(isset($files[$row['storage_key']])&&$files[$row['storage_key']]!==$row['sha256'])throw new \RuntimeException('Conflicting private file hashes.');if(!isset($files[$row['storage_key']])){$path=DocumentBlobs::path($row['storage_key']);if(!hash_equals($row['sha256'],hash_file('sha256',$path)))throw new \RuntimeException('Private file does not match database hash.');}$files[$row['storage_key']]=$row['sha256'];}
                        if($name==='external_assets'&&($row['state']??null)==='ready'&&$row['storage_key'])$files['external/'.$row['storage_key']]=$row['sha256'];
                        if($name==='program_assets'&&(int)$row['size_bytes']>0){if(!preg_match('/\A[a-f0-9]{32}\.png\z/D',$row['file_key']))throw new \RuntimeException('Invalid program media key.');$files['program-assets/'.$row['file_key']]=$row['sha256'];}
                        if($name==='external_product_revisions'&&$row['normalized_archive_key'])$files['external/'.$row['normalized_archive_key']]=$row['normalized_sha256'];
                        if($name==='external_dataset_runs'&&$row['artifact_key']&&in_array($row['state'],['queued','running','completed','failed','invalidated'],true)){$result=json_decode($row['result_json']??'null',true);$m=json_decode($row['manifest_json'],true);$files['external/'.$row['artifact_key']]=$row['kind']==='import'?($m['sha256']??null):($row['state']==='completed'?($result['sha256']??null):null);}
                    }}finally{$statement->closeCursor();$pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);}
                }finally{fclose($handle);}
                self::entry($archive,'database/'.$name.'.ndjson',$file,$password,$manifest);
            }
            $pdo->commit();
            $private=realpath(rtrim((string)Config::get('storage'),'/\\').'/private');
            foreach(['keys/application.key','erasure/ledger.ndjson']as$key)if(is_file($private.'/'.$key))$files[$key]=null;
            foreach($files as$key=>$expected){$file=$private?realpath($private.'/'.$key):false;if(!$file||!str_starts_with($file,$private.DIRECTORY_SEPARATOR)||is_link($private.'/'.$key)||!is_file($file))throw new \RuntimeException('Referenced private backup file is unavailable.');if($expected!==null&&!hash_equals($expected,hash_file('sha256',$file)))throw new \RuntimeException('Private file does not match database hash.');if(str_starts_with($key,'external/runs/')){$snapshot=$stage.'/external-'.hash('sha256',$key);$source=fopen($file,'rb');$copy=fopen($snapshot,'xb');if(!$source||!$copy)throw new \RuntimeException('External artifact snapshot unavailable.');try{if(!flock($source,LOCK_SH))throw new \RuntimeException('External artifact snapshot lock failed.');if(stream_copy_to_stream($source,$copy)===false)throw new \RuntimeException('External artifact snapshot failed.');}finally{flock($source,LOCK_UN);fclose($source);fclose($copy);}@chmod($snapshot,0600);$file=$snapshot;}if($key==='erasure/ledger.ndjson'){$snapshot=$stage.'/erasure-ledger';if(!copy($file,$snapshot))throw new \RuntimeException('Backup ledger snapshot failed.');@chmod($snapshot,0600);$file=$snapshot;}self::entry($archive,'storage/private/'.$key,$file,$password,$manifest);}
            $configRoot=Config::get('environment')==='test'?ROOT.'/tests/runtime/backup-config':ROOT.'/config';
            foreach(['local.php','mail.local.php','external.local.php']as$name)if(is_file($configRoot.'/'.$name)){$snapshot=$stage.'/config-'.$name;if(!copy($configRoot.'/'.$name,$snapshot))throw new \RuntimeException('Backup configuration snapshot failed.');@chmod($snapshot,0600);self::entry($archive,'config/'.$name,$snapshot,$password,$manifest);}
            $manifestFile=$stage.'/manifest.json';file_put_contents($manifestFile,Db::json($manifest),LOCK_EX);@chmod($manifestFile,0600);if(!$archive->addFile($manifestFile,'manifest.json')||!$archive->setEncryptionName('manifest.json',ZipArchive::EM_AES_256,$password))throw new \RuntimeException('Backup encryption failed.');
            if(!$archive->close())throw new \RuntimeException('Backup finalization failed.');@chmod($temporary,0600);if(!rename($temporary,$target))throw new \RuntimeException('Backup commit failed.');
            $result=['file'=>basename($target),'sha256'=>hash_file('sha256',$target),'bytes'=>filesize($target),'tables'=>count($manifest['tables']),'created_at'=>$manifest['created_at'],'encrypted'=>true,'external_copy_verified'=>false];Operations::record('backup.local','completed',$result);return $result;
        }catch(\Throwable $error){if($pdo->inTransaction())$pdo->rollBack();try{$archive->close();}catch(\Throwable){}if(is_file($temporary))unlink($temporary);throw $error;}
        finally{foreach(glob($stage.'/*')?:[]as$file)if(is_file($file))unlink($file);rmdir($stage);if($schemaLocked)Db::run("SELECT RELEASE_LOCK('openaliment_schema')");if($locked)Db::run("SELECT RELEASE_LOCK('oa_backup')");}
    }
    private static function entry(ZipArchive $zip,string $name,string $file,string $password,array &$manifest): void {
        if(!self::safeName($name))throw new \RuntimeException('Unsafe backup entry.');$manifest['entries'][$name]=['bytes'=>filesize($file),'sha256'=>hash_file('sha256',$file)];
        if(!$zip->addFile($file,$name)||!$zip->setEncryptionName($name,ZipArchive::EM_AES_256,$password))throw new \RuntimeException('Backup encryption failed.');
    }
    private static function password(string $password): void {if(!preg_match('/\A[A-Za-z0-9+\/_=-]{43,128}\z/D',$password)||!ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256))throw new \RuntimeException('An ASCII-encoded random backup key and ZIP AES-256 support are required.');}
    private static function safeName(string $name): bool {return !str_contains($name,'..')&&!str_contains($name,'\\')&&!str_starts_with($name,'/')&&(bool)preg_match('~\A(?:database/[a-z][a-z0-9_]*\.ndjson|storage/private/[A-Za-z0-9_./-]+|config/(?:local|mail\.local|external\.local)\.php)\z~D',$name);}
    private static function privateDirectory(string $path): string {
        if(!is_dir($path)&&!mkdir($path,0700,true)&&!is_dir($path))throw new \RuntimeException('Backup directory unavailable.');$resolved=realpath($path);$public=realpath(ROOT.'/public');
        if(!$resolved||is_link($path)||$resolved===$public||str_starts_with($resolved,$public.DIRECTORY_SEPARATOR))throw new \RuntimeException('Backup directory must remain outside public.');@chmod($resolved,0700);return $resolved;
    }
    public static function inspect(string $file,string $password): array {
        self::password($password);$zip=new ZipArchive();if($zip->open($file,ZipArchive::RDONLY)!==true)throw new \RuntimeException('Backup archive unavailable.');$zip->setPassword($password);
        try{
            $stat=$zip->statName('manifest.json');if(!$stat||$stat['size']>4000000||($stat['encryption_method']??0)!==ZipArchive::EM_AES_256)throw new \RuntimeException('Invalid encrypted backup manifest.');
            $json=$zip->getFromName('manifest.json');if(!is_string($json))throw new \RuntimeException('Backup key or archive is invalid.');$manifest=json_decode($json,true,64,JSON_THROW_ON_ERROR);
            if(($manifest['schema_version']??'')!=='openaliment.backup.v1'||!is_array($manifest['entries']??null)||!is_array($manifest['tables']??null))throw new \RuntimeException('Unknown backup format.');
            if($zip->numFiles!==count($manifest['entries'])+1)throw new \RuntimeException('Unexpected backup entries.');$seen=[];
            for($i=0;$i<$zip->numFiles;$i++){$entry=$zip->statIndex($i);$name=$entry['name'];if(isset($seen[$name]))throw new \RuntimeException('Duplicate backup entry.');$seen[$name]=true;if($name==='manifest.json')continue;
                if(!self::safeName($name)||!isset($manifest['entries'][$name])||($entry['encryption_method']??0)!==ZipArchive::EM_AES_256||$entry['size']!==$manifest['entries'][$name]['bytes'])throw new \RuntimeException('Invalid backup entry.');
                $stream=$zip->getStream($name);if(!$stream)throw new \RuntimeException('Backup decryption failed.');try{$hash=hash_init('sha256');$read=hash_update_stream($hash,$stream);if($read!==$entry['size']||!hash_equals($manifest['entries'][$name]['sha256'],hash_final($hash)))throw new \RuntimeException('Backup integrity failed.');}finally{fclose($stream);}
            }
            return $manifest;
        }finally{$zip->close();}
    }
    /** Restore only an empty isolated destination. Never overwrite a running database or configuration. */
    public static function restore(string $file,string $password,string $destination,\PDO $target): array {
        $manifest=self::inspect($file,$password);$destination=self::privateDirectory($destination);
        if((new \FilesystemIterator($destination))->valid())throw new \RuntimeException('Restore directory must be empty.');
        if($target->query('SHOW FULL TABLES')->fetch())throw new \RuntimeException('Restore database must be empty.');
        $zip=new ZipArchive();if($zip->open($file,ZipArchive::RDONLY)!==true)throw new \RuntimeException('Backup archive unavailable.');$zip->setPassword($password);$target->exec('SET FOREIGN_KEY_CHECKS=0');$tables=0;
        try{
            foreach($manifest['tables']as$name=>$meta){if(!preg_match('/\A[a-z][a-z0-9_]*\z/D',$name)||!isset($manifest['entries']['database/'.$name.'.ndjson'])||!preg_match('/\ACREATE TABLE `'.preg_quote($name,'/').'` \(/D',$meta['ddl']))throw new \RuntimeException('Invalid backup table.');
                // DDL is trusted application-generated material in an authenticated encrypted archive.
                $target->exec($meta['ddl']);$stream=$zip->getStream('database/'.$name.'.ndjson');if(!$stream)throw new \RuntimeException('Backup table unavailable.');$rows=0;$statement=null;$columns=null;$target->beginTransaction();
                try{while(($line=fgets($stream,25000000))!==false){if(!str_ends_with($line,"\n"))throw new \RuntimeException('Backup row limit exceeded.');$row=json_decode($line,true,64,JSON_THROW_ON_ERROR);if(!is_array($row)||!$row)throw new \RuntimeException('Invalid backup row.');
                    if($statement===null){$columns=array_keys($row);foreach($columns as$column)if(!preg_match('/\A[a-z][a-z0-9_]*\z/D',$column))throw new \RuntimeException('Invalid backup column.');$statement=$target->prepare('INSERT INTO `'.$name.'` (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');}
                    if(array_keys($row)!==$columns)throw new \RuntimeException('Backup row shape changed.');$statement->execute(array_values($row));$rows++;
                }if($rows!==$meta['rows'])throw new \RuntimeException('Backup row count differs.');$target->commit();}catch(\Throwable $error){if($target->inTransaction())$target->rollBack();throw $error;}finally{fclose($stream);}$tables++;
            }
            foreach($manifest['entries']as$name=>$meta){if(str_starts_with($name,'database/'))continue;$path=$destination.'/'.$name;$dir=dirname($path);if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('Restore directory failed.');$input=$zip->getStream($name);$output=fopen($path,'xb');if(!$input||!$output)throw new \RuntimeException('Restore file unavailable.');try{if(stream_copy_to_stream($input,$output)!==$meta['bytes'])throw new \RuntimeException('Restore file write failed.');}finally{fclose($input);fclose($output);}@chmod($path,0600);}
            foreach(['producer_sessions'=>'revoked_at','api_tokens'=>'revoked_at','home_devices'=>'revoked_at','api_oauth_grants'=>'revoked_at']as$table=>$column)if(isset($manifest['tables'][$table]))$target->exec('UPDATE `'.$table.'` SET `'.$column.'`=COALESCE(`'.$column.'`,UTC_TIMESTAMP())');
            foreach(['account_challenges'=>'used_at','home_login_tokens'=>'used_at']as$table=>$column)if(isset($manifest['tables'][$table]))$target->exec('UPDATE `'.$table.'` SET `'.$column.'`=COALESCE(`'.$column.'`,UTC_TIMESTAMP())');
            if(isset($manifest['tables']['home_invites']))$target->exec('UPDATE home_invites SET expires_at=LEAST(expires_at,UTC_TIMESTAMP())');
            if(isset($manifest['tables']['api_oauth_requests']))$target->exec('UPDATE api_oauth_requests SET consumed_at=COALESCE(consumed_at,UTC_TIMESTAMP())');
            if(isset($manifest['tables']['security_outbox']))$target->exec("UPDATE security_outbox SET sent_at=COALESCE(sent_at,UTC_TIMESTAMP()),message='[invalidated on restore]'");
            if(isset($manifest['tables']['external_providers']))$target->exec("UPDATE external_providers SET read_state='paused',outbound_state='disabled'");
            if(isset($manifest['tables']['external_connections']))$target->exec("UPDATE external_connections SET state='disconnected'");
            if(isset($manifest['tables']['external_publications']))$target->exec("UPDATE external_publications SET state='cancelled' WHERE state IN ('pending','retry','package_ready')");
            // A restored backup cannot prove that a later consent, mandate or credential revocation never happened.
            if(isset($manifest['tables']['program_verifications']))$target->exec("UPDATE program_verifications SET state='invalidated',token_hash=NULL WHERE state IN ('pending','active')");
            if(isset($manifest['tables']['program_staff_grants']))$target->exec('UPDATE program_staff_grants SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP())');
            if(isset($manifest['tables']['contributor_profiles']))$target->exec("UPDATE contributor_profiles SET state='withdrawn',consent_at=NULL,consent_version=NULL");
            if(isset($manifest['tables']['learning_credentials']))$target->exec("UPDATE learning_credentials SET state='suspended',reason='Restaurare: reverificare necesară' WHERE state='active'");
            if(isset($manifest['tables']['partner_profiles']))$target->exec("UPDATE partner_profiles SET state='suspended' WHERE state='active'");
            if(isset($manifest['tables']['partner_contacts']))$target->exec('UPDATE partner_contacts SET verified_at=NULL,expires_at=NULL');
            if(isset($manifest['tables']['program_assets']))$target->exec("UPDATE program_assets SET state='private' WHERE state='published'");
            file_put_contents($destination.'/RESTORE_REPORT.json',Db::json(['restored_at'=>gmdate(DATE_ATOM),'backup_sha256'=>hash_file('sha256',$file),'requires_latest_erasure_ledger'=>true,'sessions_and_tokens_revoked'=>true]),LOCK_EX);@chmod($destination.'/RESTORE_REPORT.json',0600);
            return ['tables'=>$tables,'files'=>count($manifest['entries'])-$tables,'access_revoked'=>true,'erasure_reconciliation_required'=>true];
        }finally{$target->exec('SET FOREIGN_KEY_CHECKS=1');$zip->close();}
    }
}
