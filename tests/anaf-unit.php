<?php
declare(strict_types=1);
require __DIR__.'/../app/autoload.php';

use App\Core\Problem;
use App\Domain\{AnafClient,AnafPresentation};

$count=0;
function anafAssert(string $name,bool $ok):void{global $count;if(!$ok)throw new RuntimeException($name);$count++;echo 'PASS '.$name.PHP_EOL;}
function anafReject(string $name,callable $run,int $status):void{try{$run();}catch(Problem $e){anafAssert($name,$e->status===$status);return;}throw new RuntimeException($name);}
$fixtures=json_decode(file_get_contents(__DIR__.'/fixtures/anaf-v9.json'),true,24,JSON_THROW_ON_ERROR);
$body=$fixtures['900000001'];$body['found'][0]['date_generale']['data']='2026-10-28';$raw=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
anafAssert('Normalize only the CUI, accepting optional RO',AnafClient::cui(' ro 900000001 ')==='900000001');
foreach(['','RO','AB123','1234567890123','00000',[],900000001] as $input)anafReject('Invalid CUI never becomes a network request',fn()=>AnafClient::cui($input),422);
$parsed=AnafClient::decode($raw,'900000001','2026-10-28');
anafAssert('Live-compatible envelope without cod/message is accepted',$parsed['state']==='found');
$groups=AnafPresentation::groups($parsed['record']);
anafAssert('All seven groups and 58 known fields are visible',count($groups)===7&&array_sum(array_map(fn($g)=>count($g['fields']),$groups))===58);
anafAssert('False is distinct from an unknown value',AnafPresentation::value(false)==='Nu'&&AnafPresentation::value(null)==='Nefurnizat de ANAF'&&AnafPresentation::value('')==='Nefurnizat de ANAF');
anafAssert('Address separation preserves leading zero codes',str_contains(AnafPresentation::address($parsed['record']['adresa_sediu_social'],'s'),'001234')&&!str_contains(AnafPresentation::address($parsed['record']['adresa_sediu_social'],'s'),'fiscală'));
$extended=$body;$extended['found'][0]['date_generale']['future_field']='<script>untrusted</script>';$extended['found'][0]['inregistrare_scop_Tva']['perioade_TVA'][]=$extended['found'][0]['inregistrare_scop_Tva']['perioade_TVA'][0];
$extendedResult=AnafClient::decode(json_encode($extended),'900000001','2026-10-28');
anafAssert('Unknown fields and every VAT period are preserved',count($extendedResult['record']['inregistrare_scop_Tva']['perioade_TVA'])===2&&$extendedResult['record']['date_generale']['future_field']==='<script>untrusted</script>');
anafAssert('Every period is rendered without dropping future fields',array_sum(array_map(fn($g)=>count($g['fields']),AnafPresentation::groups($extendedResult['record'])))===63);
anafReject('Returned CUI must match',fn()=>AnafClient::decode($raw,'900000002','2026-10-28'),503);
anafReject('Reference day must match',fn()=>AnafClient::decode($raw,'900000001','2026-10-29'),503);
anafReject('Invalid JSON is unavailable, never not_found',fn()=>AnafClient::decode('<html>down</html>','900000001','2026-10-28'),503);
anafReject('Oversized responses are rejected',fn()=>AnafClient::decode(str_repeat(' ',1048577),'900000001','2026-10-28'),503);
anafAssert('notFound is a separate successful business result',AnafClient::decode(json_encode($fixtures['1234']),'1234','2026-10-28')['state']==='not_found');
foreach(['adresa_sediu_social','stare_inactiv','inregistrare_scop_Tva']as$key){$malformed=$body;$malformed['found'][0][$key]='broken';anafReject('Malformed known section is rejected',fn()=>AnafClient::decode(json_encode($malformed),'900000001','2026-10-28'),503);}
$malformed=$body;$malformed['found'][0]['inregistrare_scop_Tva']['scpTVA']='false';anafReject('String false is not a valid boolean',fn()=>AnafClient::decode(json_encode($malformed),'900000001','2026-10-28'),503);
$malformed=['found'=>[],'notFound'=>[['cui'=>1234]]];anafReject('Malformed notFound is rejected safely',fn()=>AnafClient::decode(json_encode($malformed),'1234','2026-10-28'),503);

$negative=json_encode($fixtures['1234'],JSON_THROW_ON_ERROR);
anafAssert('ANAF HTTP 404 with matching notFound is a successful negative result',AnafClient::response(404,'application/json',$negative,'1234','2026-10-28')===$negative);
anafAssert('ANAF HTTP 200 negative results remain compatible',AnafClient::response(200,'Application/JSON; charset=utf-8',$negative,'1234','2026-10-28')===$negative);
anafReject('HTTP 404 for another CUI does not become not_found',fn()=>AnafClient::response(404,'application/json',$negative,'5678','2026-10-28'),503);
anafReject('Generic HTML 404 remains an upstream error',fn()=>AnafClient::response(404,'text/html','<html>Not found</html>','1234','2026-10-28'),503);
anafReject('Malformed JSON 404 remains an upstream error',fn()=>AnafClient::response(404,'application/json','{}','1234','2026-10-28'),503);
anafReject('HTTP 500 with notFound cannot hide an upstream failure',fn()=>AnafClient::response(500,'application/json',$negative,'1234','2026-10-28'),503);
anafReject('HTTP 404 with a found record is inconsistent and rejected',fn()=>AnafClient::response(404,'application/json',$raw,'900000001','2026-10-28'),503);
echo json_encode(['passed'=>$count,'failed'=>0]).PHP_EOL;
