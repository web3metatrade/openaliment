<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(PHP_VERSION_ID<80400){fwrite(STDERR,"OpenAliment requires PHP 8.4 or newer.\n");exit(1);}
require __DIR__.'/../../app/bootstrap.php';
try{
    $path=ROOT.'/config/backup.local.php';$options=is_file($path)?require $path:[];
    if(!is_array($options)||!is_string($options['directory']??null)||!is_string($options['key_file']??null))throw new RuntimeException('Private backup configuration required.');
    $keyFile=realpath($options['key_file']);$public=realpath(ROOT.'/public');$directory=realpath($options['directory']);
    if(!$keyFile||str_starts_with($keyFile,$public.DIRECTORY_SEPARATOR)||($directory&&str_starts_with($keyFile,$directory.DIRECTORY_SEPARATOR)))throw new RuntimeException('Backup key must stay outside public and backup storage.');
    $password=file_get_contents($keyFile);if(!is_string($password))throw new RuntimeException('Backup key unavailable.');
    if(in_array('--verify-latest',$argv,true)){$files=glob(rtrim($options['directory'],'/\\').'/openaliment-*.zip')?:[];sort($files);$file=end($files);if(!$file)throw new RuntimeException('No backup available.');$manifest=App\Domain\Backups::inspect($file,$password);$result=['verified'=>true,'file'=>basename($file),'tables'=>count($manifest['tables']),'external_copy_verified'=>false];App\Domain\Operations::record('backup.integrity','verified',$result);}
    else $result=App\Domain\Backups::create($options['directory'],$password);
    if(!in_array('--quiet',$argv,true))echo App\Core\Db::json($result).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"OpenAliment backup failed. Verify the private configuration, key, disk space and ZIP AES support.\n");exit(1);}
