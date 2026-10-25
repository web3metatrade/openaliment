<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(PHP_VERSION_ID<80400){fwrite(STDERR,"OpenAliment requires PHP 8.4 or newer.\n");exit(1);}
require __DIR__.'/../../app/bootstrap.php';
try{
    $options=require ROOT.'/config/restore.local.php';if(!is_array($options)||($options['confirm_empty_isolated_destination']??false)!==true)throw new RuntimeException('Isolated restore configuration required.');
    $target=new PDO($options['db']['dsn'],$options['db']['user'],$options['db']['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $identity='SELECT DATABASE() AS db,@@hostname AS host,@@port AS port';if($target->query($identity)->fetch()===App\Core\Db::pdo()->query($identity)->fetch())throw new RuntimeException('Live database reconciliation is forbidden.');
    $key=file_get_contents($options['destination'].'/storage/private/keys/application.key');if(!is_string($key))throw new RuntimeException('Restored application key unavailable.');
    $result=App\Domain\ErasureRestore::apply($target,$options['latest_erasure_ledger'],$key,$options['destination']);echo App\Core\Db::json($result).PHP_EOL;if(!$result['completed'])exit(1);
}catch(Throwable){fwrite(STDERR,"OpenAliment erasure reconciliation failed. Keep the isolated restoration closed and inspect its private report.\n");exit(1);}
