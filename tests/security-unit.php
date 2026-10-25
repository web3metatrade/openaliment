<?php
declare(strict_types=1);
require __DIR__.'/../app/autoload.php';
use App\Core\{Auth,Config,FilePolicy,Http,Passwords,Problem,SecurityLog};
Config::load(['storage'=>__DIR__.'/runtime/storage']);$checks=[];
function securityCheck(string $name,bool $ok): void {global$checks;if(!$ok)throw new RuntimeException('FAIL '.$name);$checks[]=['name'=>$name,'passed'=>true];echo 'PASS '.$name.PHP_EOL;}
function securityReject(string $name,callable $run,int $status): void {try{$run();}catch(Problem $e){securityCheck($name,$e->status===$status);return;}throw new RuntimeException('Missing rejection '.$name);}
$read=['tenant'=>str_repeat('a',32),'user'=>str_repeat('b',32),'role'=>'owner','token'=>str_repeat('c',32),'scopes'=>['read']];
foreach(['write','publish','alerts','export','import']as$scope)securityReject('Domain refuses read credential for '.$scope,fn()=>$scope==='write'?Auth::write($read):Auth::approve($read,$scope),403);
foreach(['publish','alerts','export','import']as$scope){Auth::approve(array_replace($read,['scopes'=>[$scope]]),$scope);securityCheck('Dedicated scope '.$scope.' does not require unrelated write',true);}
securityReject('Write credential cannot approve',fn()=>Auth::approve(array_replace($read,['scopes'=>['write']])),403);
securityReject('Viewer cannot publish with a publish credential',fn()=>Auth::approve(array_replace($read,['role'=>'viewer','scopes'=>['publish']])),403);
$_SERVER['HTTP_AUTHORIZATION']='Bearer oa_'.str_repeat('a',64);securityReject('HTML refuses valid-shaped Bearer',fn()=>Auth::browserOnly(),403);unset($_SERVER['HTTP_AUTHORIZATION']);
foreach([null,[],str_repeat('a',11),"valid-long-password\0bad",str_repeat('a',201)]as$i=>$bad)securityReject('Invalid new password '.$i,fn()=>Passwords::validate($bad),422);
$original=str_repeat('a',72).'X';$changed=str_repeat('a',72).'Y';
if(Passwords::argon()){$hash=Passwords::hash($original);securityCheck('New password includes suffix beyond byte 72',Passwords::verify($original,$hash)&&!Passwords::verify($changed,$hash));}
else securityReject('Bcrypt-only host rejects new passwords beyond 72 bytes',fn()=>Passwords::hash($original),422);
$legacy=password_hash(str_repeat('a',72),PASSWORD_BCRYPT,['cost'=>4]);securityCheck('Existing bcrypt passwords remain verifiable',Passwords::verify(str_repeat('a',72),$legacy));
securityCheck('Legacy long bcrypt suffix never silently becomes trusted',!Passwords::needsUpgrade($legacy,$original));
securityCheck('Malformed login password is rejected without a type error',!Passwords::verify([],$legacy)&&!Passwords::verify(str_repeat('x',10000),$legacy));
securityCheck('Unknown account verification remains false',!Passwords::verify('any-password-value',null));
$app1="\xff\xe1\x00\x0aEXIF-PII";$app13="\xff\xed\x00\x0aIPTC-PII";$comment="\xff\xfe\x00\x0aCOMMENT!";
$colour="\xff\xe2\x00\x05ICC";$scan="\xff\xda\x00\x02DATA\xff\x00MORE\xff\xd0DATA";
$clean="\xff\xd8".$colour.$scan."\xff\xd9";
securityCheck('JPEG removes EXIF IPTC and comments without modifying scan samples',FilePolicy::jpegWithoutMetadata("\xff\xd8".$app1.$app13.$comment.$colour.$scan."\xff\xd9TRAILER")===$clean);
securityCheck('Progressive JPEG filters metadata between scans',FilePolicy::jpegWithoutMetadata("\xff\xd8".$scan.$app1.$scan."\xff\xd9")==="\xff\xd8".$scan.$scan."\xff\xd9");
securityCheck('Already sanitized JPEG is byte-stable',FilePolicy::jpegWithoutMetadata($clean)===$clean);
foreach(['bad',"\xff\xd8\xff\xe1\xff\xff", "\xff\xd8".$scan]as$i=>$bad)securityReject('Truncated JPEG rejected '.$i,fn()=>FilePolicy::jpegWithoutMetadata($bad),422);
securityCheck('Only publicable MIME types pass the shared policy',FilePolicy::publishable('application/pdf')&&!FilePolicy::publishable('application/x-php')&&!FilePolicy::publishable('image/svg+xml'));
$_SERVER['CONTENT_LENGTH']='1';$_SERVER['CONTENT_TYPE']='text/plain; application/json';securityReject('JSON substring is not an accepted MIME',fn()=>Http::input(),415);
$_SERVER['CONTENT_TYPE']='application/x-www-form-urlencoded';securityReject('JSON-only operations reject form bodies',fn()=>Http::input(4096,['application/json']),415);
$_SERVER['CONTENT_TYPE']='application/json';$_SERVER['HTTP_CONTENT_ENCODING']='gzip';securityReject('Unsupported compressed requests rejected',fn()=>Http::input(),415);unset($_SERVER['HTTP_CONTENT_ENCODING']);
SecurityLog::record('test.redaction',['user_id'=>str_repeat('b',32),'password'=>'do-not-write-this-secret','token'=>'do-not-write-this-token','code'=>'synthetic']);
$log=__DIR__.'/runtime/storage/logs/security/'.gmdate('Y-m-d').'.ndjson';securityCheck('Security log allowlist excludes unknown sensitive fields',is_file($log)&&!str_contains(file_get_contents($log),'do-not-write-this-'));
file_put_contents(__DIR__.'/runtime/reports/security-unit.json',json_encode(['passed'=>count($checks),'failed'=>0,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['passed'=>count($checks),'failed'=>0]).PHP_EOL;
