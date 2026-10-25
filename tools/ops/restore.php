<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(PHP_VERSION_ID<80400){fwrite(STDERR,"OpenAliment requires PHP 8.4 or newer.\n");exit(1);}
require __DIR__.'/../../app/bootstrap.php';
try{
    // Separate, explicit configuration: destination must never be the live application's database.
    $configPath=ROOT.'/config/restore.local.php';$options=is_file($configPath)?require $configPath:[];
    if(!is_array($options)||($options['confirm_empty_isolated_destination']??false)!==true||!is_array($options['db']??null))throw new RuntimeException('Explicit isolated restore configuration required.');
    if($options['db']['dsn']===App\Core\Config::get('db')['dsn'])throw new RuntimeException('Live database restore is forbidden.');
    if(!is_string($options['latest_erasure_ledger']??null)||!is_file($options['latest_erasure_ledger']))throw new RuntimeException('The separately retained latest erasure ledger is required.');
    $target=new PDO($options['db']['dsn'],$options['db']['user'],$options['db']['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $password=file_get_contents($options['key_file']);if(!is_string($password))throw new RuntimeException('Backup key unavailable.');
    $result=App\Domain\Backups::restore($options['archive'],$password,$options['destination'],$target);
    $applicationKey=file_get_contents($options['destination'].'/storage/private/keys/application.key');if(!is_string($applicationKey))throw new RuntimeException('Restored application key unavailable.');
    $reconciliation=App\Domain\ErasureRestore::apply($target,$options['latest_erasure_ledger'],$applicationKey,$options['destination']);
    echo App\Core\Db::json(['restore'=>$result,'erasure_reconciliation'=>$reconciliation]).PHP_EOL;if(!$reconciliation['completed'])exit(1);
}catch(Throwable){fwrite(STDERR,"OpenAliment restore failed. The isolated destination remains unavailable; inspect it privately before retrying. Never point this tool at the live database.\n");exit(1);}
