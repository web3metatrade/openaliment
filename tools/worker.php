<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(PHP_VERSION_ID<80400){fwrite(STDERR,"OpenAliment requires PHP 8.4 or newer.\n");exit(1);}
require __DIR__.'/../app/bootstrap.php';
try{
    if(in_array('--diagnostics',$argv??[],true)){App\Domain\Operations::record('cron.probe','verified',['php_version'=>PHP_VERSION,'php_binary'=>PHP_BINARY,'sapi'=>PHP_SAPI]);echo json_encode(App\Domain\Operations::status(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;exit;}
    $result=App\Domain\Operations::worker(!in_array('--cleanup-only',$argv??[],true));
    if(!in_array('--quiet',$argv??[],true))echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"OpenAliment worker failed. Check the private operations panel.\n");exit(1);}
