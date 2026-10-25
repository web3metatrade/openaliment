<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use App\Core\{Config,Db,Migrations,Problem};
use App\Domain\{Accounts,Catalog,EpcDecoder,Portability,Resolver,ResolverRegistry,ResolverUri};
Config::load(oa_test_config());
if(Db::pdo()->query('SELECT DATABASE()')->fetchColumn()!=='openaliment_tests')throw new RuntimeException('Only isolated test database permitted.');
$fixture=json_decode(file_get_contents(__DIR__.'/runtime/http-fixtures.json'),true,32,JSON_THROW_ON_ERROR);
$pa=$fixture['tenant_a'];$pb=$fixture['tenant_b'];$item=$fixture['item_id'];$g='09506000134352';$checks=[];
function assertResolver(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException('FAIL '.$name);echo 'PASS '.$name.PHP_EOL;}
function rejectResolver(string $name,callable $fn,int $status):void {try{$fn();}catch(Problem $e){assertResolver($name,$e->status===$status);return;}throw new RuntimeException('Missing rejection '.$name);}
Migrations::upgradeResolver(); assertResolver('Additive resolver migration is repeatable',Migrations::resolver());
Db::run('DELETE FROM resolver_links');
$parse=ResolverUri::parse('/01/'.$g.'/22/2A/10/A%2FB%2B00%25%3F/21/SERIAL-1/','17=261101&custom=one%20two');
assertResolver('All GTIN qualifiers preserve order and reserved characters',$parse['qualifiers']===['22'=>'2A','10'=>'A/B+00%?','21'=>'SERIAL-1']);
assertResolver('Query attributes retained byte for byte',$parse['query']==='17=261101&custom=one%20two');
assertResolver('Hierarchy is the required union including independent serial and lot scopes',count(ResolverUri::scopes($parse['qualifiers']))===5);
assertResolver('TPX form supported',ResolverUri::parse('/01/'.$g.'/235/TPX123')['qualifiers']['235']==='TPX123');
rejectResolver('Qualifier ordering enforced',fn()=>ResolverUri::parse('/01/'.$g.'/21/S/10/L'),400);
rejectResolver('TPX cannot be combined with another qualifier',fn()=>ResolverUri::parse('/01/'.$g.'/10/L/235/T'),400);
rejectResolver('Malformed percent encoding rejected',fn()=>ResolverUri::parse('/01/'.$g.'/10/A%Q1'),400);
rejectResolver('Control characters rejected in query',fn()=>ResolverUri::parse('/01/'.$g,'x=%0d%0a'),400);
rejectResolver('Duplicate selection parameters rejected',fn()=>ResolverUri::parse('/01/'.$g,'linkType=gs1:pip&linkType=gs1:other'),400);
foreach(['eh30164596f40c0e5cbe991a83','exMBZFlvQMDly-mRqD']as$encoded)assertResolver('Official EPC worked example '.$encoded,EpcDecoder::decode($encoded)===['01'=>'09528765123457','21'=>'123456789123']);
rejectResolver('Truncated EPC rejected',fn()=>EpcDecoder::decode('eh3016'),400);
rejectResolver('Unsupported EPC header rejected',fn()=>EpcDecoder::decode('ehffffffffffffffffffffffff'),400);
rejectResolver('Unknown valid GTIN has no entity information',fn()=>Resolver::links(ResolverUri::parse('/01/09528765123457')),404);
$root=Resolver::links(ResolverUri::parse('/01/'.$g));
assertResolver('Published product has a default without optional attributes',count(array_intersect(array_keys($root['default']),['type','hreflang','context']))===0);
assertResolver('Native default exposes a descriptive link type',in_array(ResolverRegistry::NS.'pip',$root['default']['types'],true));
assertResolver('Private recipe is absent from resolver links',!str_contains(Db::json($root),'SECRET_RECIPE'));
$data=['href'=>'https://example.com/product','title'=>'Consumer information','types'=>['gs1:pip'],'is_default'=>true];
rejectResolver('Viewer cannot create resolver draft',fn()=>ResolverRegistry::draft(array_replace($pa,['role'=>'viewer']),$item,$data),403);
rejectResolver('Other tenant cannot register against an owned GTIN',fn()=>ResolverRegistry::draft($pb,$item,$data),404);
rejectResolver('Credentials in target URI rejected',fn()=>ResolverRegistry::draft($pa,$item,array_replace($data,['href'=>'https://user:password@example.com/'])),422);
rejectResolver('Script URI rejected',fn()=>ResolverRegistry::draft($pa,$item,array_replace($data,['href'=>'javascript:alert(1)'])),422);
rejectResolver('Private address rejected',fn()=>ResolverRegistry::draft($pa,$item,array_replace($data,['href'=>'http://127.0.0.1/admin'])),422);
rejectResolver('Default cannot have language or media metadata',fn()=>ResolverRegistry::draft($pa,$item,array_replace($data,['hreflang'=>['ro']])),422);
rejectResolver('Unregistered media type rejected',fn()=>ResolverRegistry::draft($pa,$item,array_replace($data,['is_default'=>false,'type'=>'application/unregistered-oa-test'])),422);
rejectResolver('Link association cannot combine serial with lot',fn()=>ResolverRegistry::draft($pa,$item,array_replace($data,['qualifiers'=>['21'=>'S','10'=>'L']])),422);
$draft=ResolverRegistry::draft($pa,$item,$data);
assertResolver('Draft has no public effect',Resolver::links(ResolverUri::parse('/01/'.$g))['default']['href']!==$data['href']);
rejectResolver('Editor cannot publish',fn()=>ResolverRegistry::publish(array_replace($pa,['role'=>'editor']),$draft['id'],['public_projection_confirmed'=>true]),403);
rejectResolver('Explicit publication approval required',fn()=>ResolverRegistry::publish($pa,$draft['id'],[]),422);
ResolverRegistry::publish($pa,$draft['id'],['public_projection_confirmed'=>true]);
$set=Resolver::links(ResolverUri::parse('/01/'.$g));
assertResolver('Approved custom default replaces root default',$set['default']['href']===$data['href']);
assertResolver('Effective entity has exactly one default',count(array_filter($set['links'],fn($l)=>in_array(ResolverRegistry::NS.'defaultLink',$l['types'],true)))===1);
$conflict=ResolverRegistry::draft($pa,$item,array_replace($data,['href'=>'https://example.com/another']));
rejectResolver('Two defaults at same identity rejected',fn()=>ResolverRegistry::publish($pa,$conflict['id'],['public_projection_confirmed'=>true]),409);
$next=ResolverRegistry::draft($pa,$item,array_replace($data,['href'=>'https://example.com/updated','link_key'=>$draft['link_key'],'expected_version'=>1]));
ResolverRegistry::publish($pa,$next['id'],['public_projection_confirmed'=>true]);
assertResolver('New version preserves original target in history',ResolverRegistry::owned($pa,$draft['id'])['target_json']===Db::json(ResolverRegistry::target($data)));
rejectResolver('Stale update cannot overwrite latest version',fn()=>ResolverRegistry::draft($pa,$item,array_replace($data,['link_key'=>$draft['link_key'],'expected_version'=>1])),409);
$targets=[];
foreach([
 ['qualifiers'=>['22'=>'2A'],'title'=>'Variant','href'=>'https://example.com/variant'],
 ['qualifiers'=>['10'=>'LotA'],'title'=>'Lot','href'=>'https://example.com/lot'],
 ['qualifiers'=>['22'=>'2A','10'=>'LotA'],'title'=>'Variant lot','href'=>'https://example.com/variant-lot'],
 ['qualifiers'=>['21'=>'SERIAL1'],'title'=>'Serial','href'=>'https://example.com/serial'],
 ['qualifiers'=>['235'=>'TPX1'],'title'=>'TPX','href'=>'https://example.com/tpx'],
]as$entry){$target=ResolverRegistry::draft($pa,$item,$entry+['types'=>['gs1:traceability']]);ResolverRegistry::publish($pa,$target['id'],['public_projection_confirmed'=>true]);$targets[]=$target;}
$uri=ResolverUri::parse('/01/'.$g.'/22/2A/10/LotA/21/SERIAL1');$set=Resolver::links($uri);$urls=array_column($set['links'],'href');
assertResolver('Combined identity includes independent CPV, lot, serial, combined and primary links',count(array_intersect($urls,['https://example.com/variant','https://example.com/lot','https://example.com/variant-lot','https://example.com/serial','https://example.com/updated']))===5);
assertResolver('TPX links do not leak to serial query',!in_array('https://example.com/tpx',$urls,true));
assertResolver('Less-granular request does not expose more-granular variant lot links',!in_array('https://example.com/variant-lot',array_column(Resolver::links(ResolverUri::parse('/01/'.$g.'/10/LotA'))['links'],'href'),true));
$typed=Resolver::choose($uri+[], $set,'','');
assertResolver('Known lot redirects to its exact historical page',str_contains($typed['link']['href'],'/b/'.$fixture['case_batch_id']));
$missing=Resolver::choose(ResolverUri::parse('/01/'.$g,'linkType=gs1:nonexistent'),$set,'','');assertResolver('Unavailable requested type is 404',$missing['status']===404);
assertResolver('Query forwarded intact before fragment',Resolver::location('https://example.com/a?existing=1#section','17=261101&x=A%2BB')==='https://example.com/a?existing=1&17=261101&x=A%2BB#section');
$variants=[];
foreach([['ro','RO','https://example.com/ro'],['en','GB','https://example.com/en']]as[$lang,$context,$href]){$r=ResolverRegistry::draft($pa,$item,['href'=>$href,'title'=>'Nutrition '.$lang,'types'=>['gs1:nutritionalInfo'],'hreflang'=>[$lang],'type'=>'text/html','context'=>[$context]]);ResolverRegistry::publish($pa,$r['id'],['public_projection_confirmed'=>true]);$variants[]=$r;}
$u=ResolverUri::parse('/01/'.$g,'linkType=gs1:nutritionalInfo');$s=Resolver::links($u);
assertResolver('Language negotiation uses q values',Resolver::choose($u,$s,'text/html','ro;q=0.3,en;q=0.9')['link']['href']==='https://example.com/en');
$u=ResolverUri::parse('/01/'.$g,'linkType=gs1:nutritionalInfo&context=RO');
assertResolver('Explicit context disambiguates links',Resolver::choose($u,Resolver::links($u),'text/html','en')['link']['href']==='https://example.com/ro');
assertResolver('Equal applicable choices return 300',Resolver::choose(ResolverUri::parse('/01/'.$g,'linkType=gs1:nutritionalInfo'),$s,'','')['status']===300);
$multi=[];
foreach(['ro','en']as$lang){$r=ResolverRegistry::draft($pa,$item,['href'=>'https://example.com/default-'.$lang,'title'=>'Default '.$lang,'types'=>['gs1:pip','gs1:defaultLinkMulti'],'hreflang'=>[$lang],'type'=>'text/html']);ResolverRegistry::publish($pa,$r['id'],['public_projection_confirmed'=>true]);$multi[]=$r;}
$u=ResolverUri::parse('/01/'.$g);$s=Resolver::links($u);
assertResolver('Default multi selects preferred language',Resolver::choose($u,$s,'text/html','en')['link']['href']==='https://example.com/default-en');
assertResolver('Unmatched default multi falls back to single default',Resolver::choose($u,$s,'','fr')['link']['href']===$s['default']['href']);
$lotDefault=ResolverRegistry::draft($pa,$item,['href'=>'https://example.com/lot-default','title'=>'Lot default','types'=>['gs1:pip'],'is_default'=>true,'qualifiers'=>['10'=>'LotA']]);ResolverRegistry::publish($pa,$lotDefault['id'],['public_projection_confirmed'=>true]);
$lotUri=ResolverUri::parse('/01/'.$g.'/10/LotA');assertResolver('Specific default wins at a granular level',Resolver::links($lotUri)['default']['href']==='https://example.com/lot-default');
$alert=Catalog::alert($pa,$fixture['case_batch_id'],['message'=>'Resolver safety test','state'=>'on_hold'])['id'];
assertResolver('Active batch alert keeps its local page as effective default',str_contains(Resolver::links($lotUri)['default']['href'],'/b/'.$fixture['case_batch_id']));
Catalog::resolveAlert($pa,$alert,['resolution'=>'Test complete']);
$account=Accounts::register(['email'=>'resolver-'.bin2hex(random_bytes(4)).'@example.invalid','display_name'=>'Resolver export fixture','organization'=>'Resolver export fixture','password'=>bin2hex(random_bytes(18))]);
$pc=['tenant'=>$account['tenant_id'],'user'=>$account['id'],'role'=>'owner','admin'=>false,'token'=>null];
$productC=Catalog::product($pc,['name'=>'Export resolver test','category'=>'conserve'])['id'];
$itemC=Catalog::trade($pc,$productC,['name'=>'Export resolver test'])['id'];
Db::run("UPDATE trade_items SET gtin14='09521234000006',gtin_original='09521234000006',gtin_status='verified',verified_at=? WHERE id=?",[Db::now(),$itemC]);
$c1=ResolverRegistry::draft($pc,$itemC,$data);ResolverRegistry::publish($pc,$c1['id'],['public_projection_confirmed'=>true]);
$c2=ResolverRegistry::draft($pc,$itemC,array_replace($data,['href'=>'https://example.com/revised','link_key'=>$c1['link_key'],'expected_version'=>1]));ResolverRegistry::publish($pc,$c2['id'],['public_projection_confirmed'=>true]);
$export=Portability::export($pc);assertResolver('Export includes complete resolver version history',count($export['data']['resolver_links'])===2);
$import=Portability::import($pb,$export,false);$imported=Db::all('SELECT state,is_current FROM resolver_links WHERE tenant_id=?',[$pb['tenant']]);
assertResolver('Import retains resolver revisions privately without restoring approval',count($imported)===2&&count(array_filter($imported,fn($r)=>$r['state']==='draft'&&!$r['is_current']))===2);
$beforeRights=Db::one('SELECT gtin_status FROM trade_items WHERE id=?',[$item])['gtin_status'];Db::run("UPDATE trade_items SET gtin_status='disputed' WHERE id=?",[$item]);
assertResolver('Rights dispute preserves URI with safe identity-notice default',str_contains(Resolver::links(ResolverUri::parse('/01/'.$g))['default']['href'],'/t/'));
assertResolver('Rights dispute cannot be bypassed by default multi negotiation',str_contains(Resolver::choose($u,Resolver::links($u),'text/html','en')['link']['href'],'/t/'));
rejectResolver('Disputed rights cannot create new public destinations',fn()=>ResolverRegistry::draft($pa,$item,$data),422);
Db::run('UPDATE trade_items SET gtin_status=? WHERE id=?',[$beforeRights,$item]);
ResolverRegistry::withdraw($pa,$next['id'],['reason'=>'Withdraw test']);assertResolver('Withdrawal retains previous approved version',ResolverRegistry::owned($pa,$next['id'])['state']==='published'&&!ResolverRegistry::owned($pa,$next['id'])['is_current']);
rejectResolver('Other tenant cannot read destination history',fn()=>ResolverRegistry::history($pb,$item),404);
// Create an isolated EPC worked-example product for HTTP conformance probes, never production.
$epcItem=Catalog::trade($pa,$fixture['product_id'],['name'=>'EPC worked example · local test','gtin'=>'09528765123457'])['id'];
$label=Catalog::label($pa,$epcItem,['version_key'=>'EPC1','name'=>'EPC worked example · local test','category'=>'test','ingredients'=>'Test.','allergens'=>'Test.','net_quantity'=>'1','operator'=>'Local isolated fixture'])['id'];Catalog::publishLabel($pa,$label);
Db::run("UPDATE trade_items SET gtin_status='verified',verified_at=? WHERE id=?",[Db::now(),$epcItem]);Db::insert('gtin_registry',['gtin14'=>'09528765123457','tenant_id'=>$pa['tenant'],'trade_item_id'=>$epcItem]);
// Keep the HTTP fixtures deterministic after domain tests.
foreach(Db::all("SELECT id FROM resolver_links WHERE tenant_id=? AND trade_item_id=? AND is_current=1 AND state='published'",[$pa['tenant'],$item])as$r)ResolverRegistry::withdraw($pa,$r['id'],['reason'=>'Domain test cleanup before HTTP suite']);
file_put_contents(__DIR__.'/runtime/resolver-fixtures.json',Db::json(['epc_item_id'=>$epcItem,'epc_gtin'=>'09528765123457']));
file_put_contents(ROOT.'/tests/runtime/reports/resolver-domain-report.json',Db::json(['suite'=>'Resolver domain and MariaDB integration','passed'=>count($checks),'failed'=>0,'checks'=>$checks]));echo Db::json(['passed'=>count($checks),'failed'=>0]).PHP_EOL;
