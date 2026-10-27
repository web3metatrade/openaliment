<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Problem};

/** Fixed, hash-pinned release files; never accepts uploaded code or arbitrary destinations. */
final class OperationsAssets {
    private const MANIFEST_SHA256='d3878049c2f370675a3e231a122f3312dec2ee0d7c070cec605260a4556d329e';
    private const PATHS=['tools/ops/backup.php','tools/ops/restore.php','tools/ops/reconcile-erasure.php','tools/ops/monitor.py','config/backup.local.example.php','config/migrations.local.example.php','config/restore.local.example.php','config/external.local.example.php','app/Vendor/WebAuthn/LICENSE','THIRD_PARTY_NOTICES.md','release.json','LICENSING.md','tools/standards/fetch.py','tools/external-worker.php'];
    public static function payload(): array {
        $raw=file_get_contents(ROOT.'/contracts/operations-assets.json');if(!is_string($raw)||!hash_equals(self::MANIFEST_SHA256,hash('sha256',$raw)))throw new Problem(503,'operations_assets','Resursele operaționale nu corespund versiunii instalate.');$value=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(($value['schema_version']??'')!=='openaliment.operations-assets.v1'||array_keys($value['files']??[])!==self::PATHS)throw new Problem(503,'operations_assets','Manifestul resurselor nu este acceptat.');
        foreach($value['files']as&$file){$bytes=base64_decode($file['content_base64'],true);if(!is_string($bytes)||strlen($bytes)>200000||!hash_equals($file['sha256'],hash('sha256',$bytes)))throw new Problem(503,'operations_assets','Integritatea unei resurse nu corespunde.');$file['bytes']=$bytes;}unset($file);return $value['files'];
    }
    public static function ready(): bool {try{foreach(self::payload()as$name=>$file)if(!is_file(ROOT.'/'.$name)||is_link(ROOT.'/'.$name)||!hash_equals($file['sha256'],hash_file('sha256',ROOT.'/'.$name)))return false;return true;}catch(\Throwable){return false;}}
    public static function install(): void {
        $files=self::payload();$root=realpath(ROOT);$base=Config::get('storage').'/private/operations-assets';if(!is_dir($base)&&!mkdir($base,0700,true)&&!is_dir($base))throw new \RuntimeException('Operations storage unavailable.');
        $lock=fopen($base.'/install.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new Problem(409,'operations_busy','Instalarea resurselor este deja în curs.');$backups=$base.'/'.Db::id();
        try{
            if(!mkdir($backups,0700))throw new \RuntimeException('Operations backup unavailable.');$saved=[];
            foreach($files as$name=>$file){$target=ROOT.'/'.$name;$current=ROOT;foreach(explode('/',$name)as$part){$current.='/'.$part;if(is_link($current))throw new \RuntimeException('Operations path cannot be a symlink.');}
                $directory=dirname($target);if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new \RuntimeException('Operations directory unavailable.');$parent=realpath($directory);if(!$root||!$parent||($parent!==$root&&!str_starts_with($parent,$root.DIRECTORY_SEPARATOR)))throw new \RuntimeException('Operations path escapes application.');
                if(is_file($target)&&hash_equals($file['sha256'],hash_file('sha256',$target)))continue;
                if(is_file($target)){$old=$backups.'/'.hash('sha256',$name);if(!copy($target,$old))throw new \RuntimeException('Operations previous copy failed.');@chmod($old,0600);$saved[$name]=['backup'=>basename($old),'sha256'=>hash_file('sha256',$old)];file_put_contents($backups.'/manifest.json',Db::json($saved),LOCK_EX);@chmod($backups.'/manifest.json',0600);}
                $temporary=$directory.'/.oa-'.Db::id().'.tmp';try{if(file_put_contents($temporary,$file['bytes'],LOCK_EX)!==strlen($file['bytes']))throw new \RuntimeException('Operations write failed.');@chmod($temporary,0644);if(!rename($temporary,$target))throw new \RuntimeException('Operations commit failed.');}finally{if(is_file($temporary))unlink($temporary);}
            }
            Operations::record('operations.assets','completed',['files'=>count($files)]);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
