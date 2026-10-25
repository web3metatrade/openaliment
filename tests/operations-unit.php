<?php
declare(strict_types=1);
require __DIR__.'/../app/autoload.php';
use App\Domain\OperationsAssets;
$files=OperationsAssets::payload();$checks=[];
foreach($files as$path=>$file){if(!is_file(ROOT.'/'.$path)||!hash_equals($file['sha256'],hash_file('sha256',ROOT.'/'.$path)))throw new RuntimeException('Operations artifact differs: '.$path);$checks[]=['name'=>'Pinned operations asset '.$path,'passed'=>true];}
if(!OperationsAssets::ready())throw new RuntimeException('Operations assets not complete.');
echo json_encode(['passed'=>count($checks)+1,'failed'=>0]).PHP_EOL;
