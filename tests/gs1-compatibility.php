<?php
declare(strict_types=1);
require __DIR__.'/../app/autoload.php';
use App\Core\{Config,Problem};
use App\Domain\{ConsumerFood,HomeInventory,Printing,ResolverUri,ResolverRegistry};
Config::load(['environment'=>'test','url'=>'https://openaliment.ro','timezone'=>'Europe/Bucharest']);
require __DIR__.'/../app/bootstrap.php';
$checks=[];
function gs1Check(string $name,bool $passed):void{global $checks;if(!$passed)throw new RuntimeException($name);$checks[]=['name'=>$name,'passed'=>true];}
$fixture=json_decode(file_get_contents(__DIR__.'/fixtures/gs1-compatibility.json'),true,32,JSON_THROW_ON_ERROR);
foreach($fixture['cases'] as $case){
    try{$p=ConsumerFood::code($case['url']);$accepted=true;}catch(Problem){$accepted=false;}
    gs1Check($case['name'].' validity',$accepted===$case['valid']);if(!$accepted)continue;
    gs1Check($case['name'].' identity',$p['gtin14']===$case['gtin14']&&(($p['qualifiers']??null)?:null)===$case['qualifiers']);
    $expiry=null;foreach($p['dates']as$d)if($expiry===null||$d['kind']==='use_by')$expiry=$d;
    gs1Check($case['name'].' date',$expiry==$case['expiry']);
}
try{ResolverUri::path('09520000000011',['99'=>'X']);$rejected=false;}catch(Problem){$rejected=true;}
gs1Check('URI builder never silently drops unknown qualifiers',$rejected);
$profile=ConsumerFood::lookup('https://openaliment.ro/01/09520000000011/22/2A/10/DEMO-PBT-01/21/CaseSensitive');
$private=HomeInventory::profile(json_decode(json_encode($profile,JSON_THROW_ON_ERROR),true,32,JSON_THROW_ON_ERROR));
gs1Check('Public to private projection retains the complete observed identity',(array)$private['gs1_qualifiers']===['22'=>'2A','10'=>'DEMO-PBT-01','21'=>'CaseSensitive']);
$legacy=$profile;unset($legacy['gs1_qualifiers']);gs1Check('Old consumer profiles remain accepted',HomeInventory::profile($legacy)['gs1_qualifiers']===null);
gs1Check('Registration association differs intentionally from request URI',count(ResolverUri::qualifiers(['10'=>'L','21'=>'S']))===2);
try{ResolverRegistry::qualifiers(['10'=>'L','21'=>'S']);$rejected=false;}catch(Problem){$rejected=true;}
gs1Check('Registered serial link uses GTIN plus serial independently of lot',$rejected);
$old=(int)date('Y')-40;
$uri=Printing::digitalLink('09520000000011','L','S',['production_date'=>$old.'-01-01']);
gs1Check('Representable pre-2000 production year is not artificially excluded',str_contains($uri,'11='.substr((string)$old,2).'0101'));
$uri='https://example.org/'.str_repeat('a',492);
$zpl=Printing::zpl([['digital_link'=>$uri,'snapshot'=>['name'=>'Food','lot_code'=>'L','serial'=>'S']]]);
gs1Check('ZPL reference reserves explicit canvas and separated text',str_contains($zpl,'^PW600')&&str_contains($zpl,'^LL650')&&str_contains($zpl,'^FO40,120^BQN,2,5')&&str_contains($zpl,'^FO40,570'));
preg_match('/\^FDLA,((?:_[A-F0-9]{2})+)\^FS/',$zpl,$m);
gs1Check('Longest reference URI is transmitted without truncation',hex2bin(str_replace('_','',$m[1]))===$uri);
try{Printing::zpl([['digital_link'=>$uri.'x','snapshot'=>[]]]);$rejected=false;}catch(Problem $e){$rejected=$e->errorCode==='print_zpl_capacity';}
gs1Check('Oversized ZPL reference URI requires an adapter instead of truncation',$rejected);
$generated=[];
foreach($fixture['cases']as$case)if($case['valid']&&$case['qualifiers']&&$case['qualifiers']!==['10'=>'..'])$generated[]=['name'=>$case['name'],'uri'=>url(ResolverUri::path($case['gtin14'],$case['qualifiers']))];
foreach(['production_date','best_before','use_by']as$key)$generated[]=['name'=>'Printing date '.$key,'uri'=>Printing::digitalLink('09520000000011','DEMO-PBT-01','DEMO000001',[$key=>'2026-10-25'])];
file_put_contents(__DIR__.'/runtime/gs1-generated-uris.json',json_encode($generated,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
file_put_contents(__DIR__.'/runtime/reports/gs1-compatibility-php.json',json_encode(['passed'=>count($checks),'failed'=>0,'network_or_database'=>false,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['passed'=>count($checks),'failed'=>0]).PHP_EOL;
