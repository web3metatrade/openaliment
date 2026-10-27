<?php
declare(strict_types=1);

if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
if (PHP_VERSION_ID<80400) {fwrite(STDERR,"PHP 8.4 or newer is required.\n");exit(1);}
require __DIR__.'/../app/bootstrap.php';
try {
    if(!App\Core\Migrations::external()){echo "External catalog is not installed.\n";exit;}
    $result=App\Domain\ExternalJobs::run(12);
    foreach(App\Core\Db::all("SELECT id FROM external_dataset_runs WHERE state IN ('queued','running') ORDER BY started_at LIMIT 1")as$run)$result['dataset']=App\Domain\ExternalDatasets::step($run['id']);
    $result['maintenance']=App\Domain\ExternalDatasets::maintain();
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){fwrite(STDERR,"External source worker failed. Check the private source administration panel.\n");exit(1);}
