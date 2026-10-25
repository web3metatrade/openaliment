<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use App\Core\{Config,Db,Problem};
use App\Domain\{Catalog,DemoBread,DigitalLink,Portability,Resolver,ResolverRegistry,ResolverUri};
Config::load(oa_test_config());
if(Db::pdo()->query('SELECT DATABASE()')->fetchColumn()!=='openaliment_tests')throw new RuntimeException('Only isolated test database permitted.');
$f=json_decode(file_get_contents(__DIR__.'/runtime/http-fixtures.json'),true,32,JSON_THROW_ON_ERROR);$checks=[];
function demoCheck(string $name,bool $value):void{global $checks;if(!$value)throw new RuntimeException($name);$checks[]=['name'=>$name,'passed'=>true];}
function demoReject(string $name,callable $fn):void{try{$fn();}catch(Problem $e){demoCheck($name,$e->status===422);return;}throw new RuntimeException($name);}
demoCheck('952 demo identifier has a valid check digit and normalizes to 14 digits',DigitalLink::gtin(DemoBread::GTIN13)===DemoBread::GTIN14&&str_starts_with(DemoBread::GTIN13,'952'));
demoReject('Arbitrary repeated digit example fails check digit',fn()=>DigitalLink::gtin('1111111111111'));
demoCheck('Demo is absent from commercial registry',Db::one('SELECT gtin14 FROM gtin_registry WHERE gtin14=?',[DemoBread::GTIN14])===null);
foreach([false,true]as$batch){
    $u=ResolverUri::parse(DigitalLink::path(DemoBread::GTIN14,$batch?DemoBread::LOT:null),'custom=A%2BB');$set=DemoBread::links($u);
    demoCheck('Demo selects explicit '.($batch?'batch':'product').' page',$set['default']['href']===url(DemoBread::page($batch)));
    demoCheck('Default has no negotiation-only attributes',!array_intersect(array_keys($set['default']),['type','context','hreflang']));
    demoCheck('Resolver chooses demo via normal pipeline',Resolver::choose($u,$set,'text/html','ro')['status']===307);
    demoCheck('JSON snapshot is explicitly simulated',DemoBread::snapshot($batch)['simulation']===true&&DemoBread::snapshot($batch)['commercial_use']===false);
    demoCheck('No recipe payload is disclosed',!isset(DemoBread::snapshot($batch)['ingredients'])&&DemoBread::snapshot($batch)['recipe_published']===false);
}
$unknown=DemoBread::links(ResolverUri::parse(DigitalLink::path(DemoBread::GTIN14,'A/B+00%?')));
demoCheck('Unknown lot never masquerades as known demo lot',str_ends_with($unknown['default']['href'],'/lot-necunoscut'));
demoCheck('No other demo or commercial GTIN is intercepted',DemoBread::links(ResolverUri::parse('/01/09521234000006'))===null);
demoReject('Demo GTIN cannot be registered by a tenant',fn()=>Catalog::trade($f['tenant_a'],$f['product_id'],['name'=>'Reserved demo','gtin'=>DemoBread::GTIN13]));
$transfer=['schema_version'=>'openaliment.transfer.v1','data'=>array_fill_keys(['products','trade_items','product_info_versions','recipe_versions','batches','alerts','documents'],[])];$transfer['data']['trade_items'][]=['gtin14'=>DemoBread::GTIN14];$transfer['data_sha256']=hash('sha256',Db::json($transfer['data']));
demoReject('Import dry run rejects reserved demo identifier',fn()=>Portability::import($f['tenant_a'],$transfer,true));
demoReject('Import cannot insert reserved demo identifier',fn()=>Portability::import($f['tenant_a'],$transfer,false));
file_put_contents(ROOT.'/tests/runtime/reports/demo-bread-domain-report.json',Db::json(['passed'=>count($checks),'failed'=>0,'checks'=>$checks]));echo Db::json(['passed'=>count($checks),'failed'=>0]).PHP_EOL;
