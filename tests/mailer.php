<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use App\Core\{Config,Db,Mailer,Problem};
$cfg=oa_test_config();
if(!str_contains($cfg['db']['dsn'],'dbname=openaliment_tests;'))throw new RuntimeException('Isolated tests only.');
$checks=[];function mailCheck(string $name,bool $passed):void{global$checks;if(!$passed)throw new RuntimeException($name);$checks[]=['name'=>$name,'passed'=>true];echo 'PASS '.$name.PHP_EOL;}
$cfg['environment']='test';$cfg['mail']=['enabled'=>false];Config::load($cfg);mailCheck('Email disabled without affecting application configuration',!Mailer::ready());
$cfg['mail']=['enabled'=>true,'transport'=>'smtp','host'=>'smtp.example.invalid','port'=>465,'encryption'=>'ssl','username'=>'test-only','password'=>'test-only','from_address'=>'sender@example.invalid'];Config::load($cfg);mailCheck('Explicit authenticated TLS SMTP settings accepted',Mailer::ready());
$cfg['mail']['encryption']='none';Config::load($cfg);mailCheck('Plaintext SMTP not accepted',!Mailer::ready());
$cfg['mail']['encryption']='ssl';$cfg['mail']['password']='';Config::load($cfg);mailCheck('Incomplete SMTP settings not accepted',!Mailer::ready());
$cfg['mail']=['enabled'=>true,'transport'=>'test_file'];Config::load($cfg);mailCheck('Isolated test file driver enabled only explicitly',Mailer::ready());
mailCheck('Test transport never claims real SMTP authentication',Mailer::probe()===['transport'=>'test_file','authenticated'=>false,'email_sent'=>false,'simulated'=>true]);
$dir=$cfg['storage'].'/private/mail-test';$before=is_dir($dir)?glob($dir.'/*.json'):[];$token=bin2hex(random_bytes(32));Mailer::loginLink('synthetic@example.invalid',$token);$after=glob($dir.'/*.json');$created=array_values(array_diff($after,$before));mailCheck('Test delivery writes one private message',count($created)===1);
$message=json_decode(file_get_contents($created[0]),true,16,JSON_THROW_ON_ERROR);mailCheck('Login token is in a fragment, not URL query',str_contains($message['text'],'#conectare='.$token)&&!str_contains($message['text'],'?token='));
try{Mailer::loginLink("recipient@example.invalid\r\nBcc: injected@example.invalid",$token);throw new RuntimeException('Header injection accepted');}catch(Problem $e){mailCheck('Email header injection rejected',$e->status===422);}
$cfg['environment']='production';Config::load($cfg);mailCheck('Production cannot enable the test file driver',!Mailer::ready());
try{Mailer::loginLink('synthetic@example.invalid',$token);throw new RuntimeException('Production file transport accepted');}catch(Problem $e){mailCheck('Production cannot silently pretend to send email',$e->status===503);}
file_put_contents(ROOT.'/tests/runtime/reports/mailer-report.json',Db::json(['executed_at'=>gmdate(DATE_ATOM),'environment'=>'isolated private file transport; no real SMTP delivery','passed'=>count($checks),'failed'=>0,'checks'=>$checks]));echo Db::json(['passed'=>count($checks),'failed'=>0]).PHP_EOL;
