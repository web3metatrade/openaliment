<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Problem};

/** Prepare private paths without sending encryption material to the browser. */
final class BackupSettings {
    public static function prepare(): void {
        $config=ROOT.'/config/backup.local.php';if(is_file($config))throw new Problem(409,'backup_config','Configurația privată există deja.');
        $base=dirname(ROOT);$directory=$base.'/openaliment-backups';$key=$base.'/openaliment-backup.key';
        if(Config::get('environment')!=='production')throw new Problem(403,'environment','Configurarea este disponibilă numai pe instalarea de producție.');
        if(is_link($directory)||is_link($key)||is_link($config))throw new \RuntimeException('Unsafe backup settings path.');
        $lock=fopen(Config::get('storage').'/private/backup-setup.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Backup settings lock unavailable.');
        try{
            if(is_file($config))throw new Problem(409,'backup_config','Configurația privată există deja.');
            if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new \RuntimeException('Backup directory unavailable.');@chmod($directory,0700);
            if(!is_file($key)){$file=fopen($key,'xb');if(!$file)throw new \RuntimeException('Backup key creation failed.');try{$bytes=bin2hex(random_bytes(32));if(fwrite($file,$bytes)!==64)throw new \RuntimeException('Backup key write failed.');fflush($file);}finally{fclose($file);}@chmod($key,0600);}
            if(!preg_match('/\A[A-Za-z0-9+\/_=-]{43,128}\z/D',file_get_contents($key)))throw new \RuntimeException('Existing backup key is invalid.');
            $file=fopen($config,'xb');if(!$file)throw new \RuntimeException('Backup configuration creation failed.');$content="<?php\ndeclare(strict_types=1);\nreturn ".var_export(['directory'=>$directory,'key_file'=>$key],true).";\n";
            try{if(fwrite($file,$content)!==strlen($content))throw new \RuntimeException('Backup configuration write failed.');fflush($file);}finally{fclose($file);}@chmod($config,0600);Operations::record('backup.configuration','configured',['separate_key_file'=>true]);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
