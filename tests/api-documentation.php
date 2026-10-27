<?php
declare(strict_types=1);
require __DIR__.'/../app/autoload.php';
use App\Core\{Config,Problem};
use App\Domain\ApiDocumentation;
Config::load(['url'=>'https://openaliment.ro']);$checks=[];
function documentationCheck(string $name,bool $ok):void{global$checks;if(!$ok)throw new RuntimeException($name);$checks[]=['name'=>$name,'passed'=>true];echo 'PASS '.$name.PHP_EOL;}
$catalog=ApiDocumentation::catalog();$spec=json_decode(file_get_contents(ROOT.'/contracts/platform-openapi.json'),true);
$expected=array_sum(array_map(static fn($methods)=>count($methods),$spec['paths']));
documentationCheck('Portal covers every real operation exactly once',$catalog['operation_count']===$expected&&count(array_filter($catalog['pages'],static fn($p)=>$p['kind']==='operation'))===$expected&&count($catalog['navigation'])===count($catalog['pages']));
documentationCheck('Guide source split is complete',$catalog['guides']===20&&isset($catalog['pages']['protectia-contului'],$catalog['pages']['marketplace'],$catalog['pages']['surse-externe']));
documentationCheck('Overview AI index remains a real link',str_contains(ApiDocumentation::page('/api/docs')['html'],'href="/llms.txt"'));
documentationCheck('Session-only key rotation explicitly requires CSRF',array_any($spec['paths']['/api/v2/manage/access-tokens/{id}/rotations']['post']['parameters'],static fn($p)=>$p['name']==='X-CSRF-Token'&&$p['required']===true));
foreach($catalog['pages']as$page){
    documentationCheck('Canonical lookup and markdown '.$page['id'],ApiDocumentation::page($page['path'])['id']===$page['id']&&ApiDocumentation::page($page['path'].'.md')['id']===$page['id']&&str_contains(ApiDocumentation::markdown($page),'https://openaliment.ro'.$page['path'].'.md'));
    if($page['kind']==='operation')documentationCheck('Reference matches actual contract '.$page['id'],isset($spec['paths'][$page['endpoint']][strtolower($page['method'])])&&$spec['paths'][$page['endpoint']][strtolower($page['method'])]['operationId']===$page['id']&&str_contains(ApiDocumentation::markdown($page),$page['method'].' https://openaliment.ro'.$page['endpoint']));
}
foreach(['/api/docs/guides/manageRotateAccessToken','/api/docs/reference/chei','/api/docs/reference/unknown','/api/docs/guides/../../config/local.php']as$path){try{ApiDocumentation::page($path);throw new RuntimeException('Unexpected document');}catch(Problem $error){documentationCheck('Unknown or mismatched route refused '.$path,$error->status===404);}}
$index=ApiDocumentation::llms();documentationCheck('AI index includes all public markdown pages',substr_count($index,'.md)')===count($catalog['pages'])&&!str_contains($index,'config/local.php'));
$full=ApiDocumentation::llms(true);documentationCheck('Full corpus contains every operation',array_all(array_filter($catalog['pages'],static fn($p)=>$p['kind']==='operation'),static fn($p)=>str_contains($full,'`'.$p['id'].'`')));
$report=['passed'=>count($checks),'failed'=>0,'checks'=>$checks];if(!is_dir(ROOT.'/tests/runtime/reports'))mkdir(ROOT.'/tests/runtime/reports',0700,true);file_put_contents(ROOT.'/tests/runtime/reports/api-documentation-domain.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo json_encode(['passed'=>count($checks),'failed'=>0]).PHP_EOL;
