<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use App\Core\{Config,Db,Migrations,Problem};
use App\Domain\{Accounts,Catalog,DigitalReceipt,Integrations,PartnerMapping,Portability};
$cfg=oa_test_config();
if(!str_contains($cfg['db']['dsn'],'dbname=openaliment_tests;'))throw new RuntimeException('Isolated database required.');Config::load($cfg);if(Db::one('SELECT DATABASE() AS n')['n']!=='openaliment_tests')throw new RuntimeException('Wrong database.');
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
$checks=[];function ok(string $name,bool $yes):void{global$checks;if(!$yes)throw new RuntimeException('FAIL '.$name);$checks[]=['name'=>$name,'passed'=>true];echo 'PASS '.$name.PHP_EOL;}
function bad(string $name,callable $fn,int $status=422):void{try{$fn();}catch(Problem $e){ok($name,$e->status===$status);return;}throw new RuntimeException('Missing rejection '.$name);}
Migrations::upgradeIntegrations();Migrations::upgradeIntegrations();ok('Additive integration migration is repeatable',Migrations::integrations());
$simple=['schema_version'=>PartnerMapping::VERSION,'fields'=>['/n'=>['from'=>'/n','op'=>'decimal'],'/date'=>['from'=>'/date','op'=>'date_dmy']]];
$mapped=PartnerMapping::apply($simple,['n'=>'4,2','date'=>'29.02.2028','unmapped_private'=>'do not copy']);ok('Decimal comma and explicit DMY converted without copying extra fields',$mapped===['n'=>4.2,'date'=>'2028-02-29']);
bad('Ambiguous thousands/decimal number rejected',fn()=>PartnerMapping::apply($simple,['n'=>'1.234,5','date'=>'29.02.2028']));
bad('Invalid calendar rejected',fn()=>PartnerMapping::apply($simple,['n'=>1,'date'=>'31.02.2028']));
bad('Executable transform rejected',fn()=>PartnerMapping::validate(['schema_version'=>PartnerMapping::VERSION,'fields'=>['/n'=>['from'=>'/n','op'=>'eval']]]));
bad('Prototype path rejected',fn()=>PartnerMapping::validate(['schema_version'=>PartnerMapping::VERSION,'fields'=>['/__proto__/x'=>['value'=>1]]]));
bad('Overlapping targets rejected',fn()=>PartnerMapping::validate(['schema_version'=>PartnerMapping::VERSION,'fields'=>['/a'=>['value'=>1],'/a/b'=>['value'=>2]]]));
bad('Numeric GTIN rejected to prevent lost zeros',fn()=>PartnerMapping::apply(['schema_version'=>PartnerMapping::VERSION,'fields'=>['/gtin'=>['from'=>'/ean','op'=>'gtin']]],['ean'=>9520000000011]));
$escaped=PartnerMapping::apply(['schema_version'=>PartnerMapping::VERSION,'fields'=>['/out'=>['from'=>'/a~1b/~0x']]],['a/b'=>['~x'=>'ok']]);ok('JSON Pointer escape decoding is exact',$escaped['out']==='ok');
function account():array{$r=Accounts::register(['email'=>'erp-'.Db::id().'@example.invalid','display_name'=>'Simulare ERP','organization'=>'Simulare izolată','password'=>bin2hex(random_bytes(18))],true);return ['tenant'=>$r['tenant_id'],'user'=>$r['id'],'role'=>'owner','admin'=>false,'token'=>null];}
$p=account();$other=account();$connector=Integrations::create($p,['name'=>'ERP simulator A','kind'=>'catalog'])['id'];
$mapping=['schema_version'=>PartnerMapping::VERSION,'fields'=>[
 '/name'=>['from'=>'/ARTICOL/DENUMIRE','required'=>true],'/category'=>['from'=>'/ARTICOL/CATEGORIE','required'=>true],'/trade_name'=>['from'=>'/ARTICOL/AMBALAJ'],
 '/label/name'=>['from'=>'/ARTICOL/DENUMIRE'],'/label/category'=>['from'=>'/ARTICOL/CATEGORIE'],'/label/version_key'=>['from'=>'/ETICHETA/REVIZIE'],
 '/label/ingredients'=>['from'=>'/ETICHETA/INGREDIENTE'],'/label/allergens'=>['from'=>'/ETICHETA/ALERGENI'],'/label/net_quantity'=>['from'=>'/ARTICOL/AMBALAJ'],'/label/operator'=>['value'=>'Operator fictiv · test izolat']]];
$payload=['ARTICOL'=>['DENUMIRE'=>'Iaurt simulator','CATEGORIE'=>'lactate','AMBALAJ'=>'200 g'],'ETICHETA'=>['REVIZIE'=>'SIM-1','INGREDIENTE'=>'Lapte, culturi.','ALERGENI'=>'Conține lapte.'],'PRIVATE'=>['recipe'=>'Not mapped']];
$mapId=Integrations::mapping($p,$connector,['version_key'=>'erp-a-1','mapping'=>$mapping])['id'];
bad('Mapping version cannot be overwritten',fn()=>Integrations::mapping($p,$connector,['version_key'=>'erp-a-1','mapping'=>$mapping]),409);
bad('Another tenant cannot read mappings',fn()=>Integrations::mappings($other,$connector),404);
bad('Viewer cannot create connector',fn()=>Integrations::create(array_replace($p,['role'=>'viewer']),['name'=>'no','kind'=>'catalog']),403);
$event=['event_key'=>'article-42-rev1','external_id'=>'ARTICLE-42','mapping_id'=>$mapId,'operation'=>'product','payload'=>$payload];
$before=(int)Db::one('SELECT COUNT(*) AS n FROM products WHERE tenant_id=?',[$p['tenant']])['n'];$preview=Integrations::preview($p,$connector,$event);
ok('Preview validates but performs no writes',$preview['writes']===false&&(int)Db::one('SELECT COUNT(*) AS n FROM products WHERE tenant_id=?',[$p['tenant']])['n']===$before);
ok('Private upstream fields omitted',!str_contains(Db::json($preview),'Not mapped'));
$result=Integrations::ingest($p,$connector,$event);ok('ERP product is a draft with no recipe or badge',$result['state']==='draft'&&Catalog::owned('product_info_versions',$result['label_id'],$p)['state']==='draft');
ok('Redelivery returns exactly original result',Integrations::ingest($p,$connector,$event)===$result);
$changed=$event;$changed['payload']['ARTICOL']['DENUMIRE']='Changed';bad('Event key reuse with different payload rejected',fn()=>Integrations::ingest($p,$connector,$changed),409);
bad('Tenant cannot ingest through someone else connector',fn()=>Integrations::ingest($other,$connector,$event),404);
$batchMapping=['schema_version'=>PartnerMapping::VERSION,'fields'=>['/trade_external_id'=>['from'=>'/article'],'/label_version_key'=>['from'=>'/label_revision'],'/lot_code'=>['from'=>'/lot'],'/use_by'=>['from'=>'/until','op'=>'date_dmy']]];
$batchMapId=Integrations::mapping($p,$connector,['version_key'=>'batch-b-1','mapping'=>$batchMapping])['id'];$batchEvent=['event_key'=>'batch-1','external_id'=>'BATCH-1','mapping_id'=>$batchMapId,'operation'=>'batch','payload'=>['article'=>'ARTICLE-42','label_revision'=>'SIM-1','lot'=>'A/B+01','until'=>'10.11.2026']];
bad('Batch cannot bind unapproved label',fn()=>Integrations::ingest($p,$connector,$batchEvent));Catalog::publishLabel($p,$result['label_id']);$batch=Integrations::ingest($p,$connector,$batchEvent);$b=Catalog::owned('batches',$batch['id'],$p);
ok('Batch preserves punctuation and exact label without recipe',$b['lot_code']==='A/B+01'&&$b['recipe_version_id']===null&&$b['product_info_version_id']===$result['label_id']);
ok('Batch import does not auto publish or release',$b['visibility']==='private'&&$b['state']==='planned');Catalog::publishBatch($p,$batch['id']);
$next=$event;$next['event_key']='article-42-rev2';$next['payload']['ETICHETA']['REVIZIE']='SIM-2';$next['payload']['ETICHETA']['INGREDIENTE']='Lapte, culturi, fructe.';$v2=Integrations::ingest($p,$connector,$next);Catalog::publishLabel($p,$v2['label_id']);
ok('ERP update creates immutable label; historical batch stays old',Catalog::publicBatch($batch['id'])['label']['ingredients']==='Lapte, culturi.'&&Catalog::publicItem($result['trade_item_id'])['label']['ingredients']==='Lapte, culturi, fructe.');
$receiptConnector=Integrations::create($p,['name'=>'Magazin simulator B','kind'=>'retail_receipt'])['id'];
$receiptMap=['schema_version'=>PartnerMapping::VERSION,'fields'=>['/merchant'=>['from'=>'/shop/title'],'/receipt_external_id'=>['from'=>'/ticket/id'],'/issued_at'=>['from'=>'/ticket/at'],'/simulation'=>['value'=>true]],'arrays'=>[['from'=>'/ticket/basketLines','to'=>'/lines','fields'=>['/line_id'=>['from'=>'/row','op'=>'text'],'/name'=>['from'=>'/text'],'/gtin'=>['from'=>'/EAN','op'=>'gtin'],'/quantity'=>['from'=>'/pieces','op'=>'integer'],'/unit'=>['value'=>'pack']]]]];
$receiptMapId=Integrations::mapping($p,$receiptConnector,['version_key'=>'shop-b-1','mapping'=>$receiptMap])['id'];
$receiptEvent=['event_key'=>'sale-002','external_id'=>'RECEIPT-002','operation'=>'receipt','mapping_id'=>$receiptMapId,'payload'=>['shop'=>['title'=>'Magazin fictiv · simulator'],'ticket'=>['id'=>'RECEIPT-002','at'=>'2026-10-24T13:30:00+03:00','basketLines'=>[['row'=>1,'text'=>'Pâine Bună Tehnic · demonstrație','EAN'=>'9520000000011','pieces'=>'2'],['row'=>2,'text'=>'Aliment necunoscut · demonstrație','EAN'=>'9506000134352','pieces'=>'1']]],'customer_email'=>'not imported@example.invalid']];
$receiptResult=Integrations::ingest($p,$receiptConnector,$receiptEvent);$token=substr($receiptResult['receipt_url'],strrpos($receiptResult['receipt_url'],'=')+1);$receipt=DigitalReceipt::preview($token);
ok('Simulated retailer creates confidential receipt with fragment URL',str_contains($receiptResult['receipt_url'],'#bon=')&&$receipt['simulation']&&count($receipt['lines'])===2);
ok('Missing lot and expiry remain unknown',$receipt['lines'][0]['expiry']===null&&$receipt['lines'][0]['lot_code']===null);
ok('Buyer contact not copied to receipt',!str_contains(Db::json($receipt),'customer_email')&&!str_contains(Db::json($receipt),'not imported'));
ok('Receipt retry returns original private link',Integrations::ingest($p,$receiptConnector,$receiptEvent)===$receiptResult);
bad('Invalid receipt token cannot enumerate',fn()=>DigitalReceipt::preview(str_repeat('0',64)),404);
Db::run('UPDATE consumer_receipts SET expires_at=? WHERE id=?',['2020-01-01 00:00:00',$receipt['id']]);bad('Expired receipt unavailable',fn()=>DigitalReceipt::preview($token),404);Db::run('UPDATE consumer_receipts SET expires_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()+3600),$receipt['id']]);
$package=Portability::export($p);ok('Producer export contains mappings and external bindings without receipt capabilities',count($package['data']['integration_connectors'])===2&&count($package['data']['integration_bindings'])===1&&!str_contains(Db::json($package),$token));
$imported=Portability::import($other,$package,false);$importedConnector=$imported['id_map'][$connector];ok('Connector mappings migrate with remapped product references',count(Integrations::mappings($other,$importedConnector))===2&&Db::one('SELECT trade_item_id FROM integration_bindings WHERE tenant_id=? AND connector_id=?',[$other['tenant'],$importedConnector])['trade_item_id']===$imported['id_map'][$result['trade_item_id']]);
$tokenData=Accounts::token($p,['name'=>'Simulate HTTP','scopes'=>['read','write'],'days'=>1]);file_put_contents(ROOT.'/tests/runtime/integration-fixtures.json',Db::json(['api_token'=>$tokenData['token'],'connector_id'=>$connector,'mapping_id'=>$mapId,'receipt_token'=>$token,'receipt_url'=>$receiptResult['receipt_url'],'receipt'=>$receipt]));
file_put_contents(ROOT.'/tests/runtime/reports/partner-simulator-report.json',Db::json(['environment'=>'local isolated openaliment_tests; ERP and retailer are explicit simulators, not live partners','executed_at'=>gmdate(DATE_ATOM),'passed'=>count($checks),'failed'=>0,'checks'=>$checks]));echo Db::json(['passed'=>count($checks),'failed'=>0]).PHP_EOL;
