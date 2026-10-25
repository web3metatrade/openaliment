<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);require __DIR__.'/bootstrap.php';
use App\Core\{Config,Db,PrivateKey};use App\Domain\Passkeys;
$data=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$mode=$data['mode'];
if($mode==='reset-token'){
    $user=Db::one('SELECT id FROM users WHERE email=?',[$data['email']]);foreach(Db::all('SELECT event_key,message FROM security_outbox WHERE user_id=? AND event_key LIKE ? AND sent_at IS NULL ORDER BY created_at DESC',[$user['id'],'reset-%'])as$row){if(!Db::one('SELECT token_hash FROM account_challenges WHERE token_hash=? AND used_at IS NULL AND expires_at>?',[substr($row['event_key'],6),Db::now()]))continue;if(preg_match('/#token=([a-f0-9]{64})/',PrivateKey::open($row['message']),$m)){echo Db::json(['token'=>$m[1]]);exit;}}throw new RuntimeException('Synthetic reset link missing');
}
if($mode==='home-token'){
    foreach(glob(Config::get('storage').'/private/mail-test/*.json')as$file){$message=json_decode(file_get_contents($file),true);if($message['recipient']===$data['email']&&preg_match('/#'.($data['kind']==='login'?'conectare':'confirma').'=([a-f0-9]{64})/',$message['text'],$m)){echo Db::json(['token'=>$m[1]]);exit;}}throw new RuntimeException('Synthetic home link missing');
}
function fixtureCbor(mixed $value): string {$head=static fn(int $major,int $n)=>chr(($major<<5)|($n<24?$n:($n<256?24:25))).($n<24?'':($n<256?chr($n):pack('n',$n)));if(is_int($value))return $head($value<0?1:0,$value<0?-$value-1:$value);if(is_string($value))return $head(2,strlen($value)).$value;if(is_array($value)){$out=$head(5,count($value));foreach($value as$k=>$v){$out.=is_int($k)?fixtureCbor($k):$head(3,strlen($k)).$k;$out.=fixtureCbor($v);}return $out;}throw new RuntimeException('Unsupported CBOR fixture');}
$directory=__DIR__.'/runtime/webauthn-private';if(!is_dir($directory))mkdir($directory,0700,true);$origin=(string)Config::get('url');$rp=parse_url($origin,PHP_URL_HOST);
if($mode==='create'){
    $id=Db::id();$key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);openssl_pkey_export($key,$pem);$credential=random_bytes(32);file_put_contents($directory.'/'.$id.'.json',Db::json(['pem'=>$pem,'credential'=>Passkeys::encode($credential),'counter'=>1]),LOCK_EX);chmod($directory.'/'.$id.'.json',0600);$ec=openssl_pkey_get_details($key)['ec'];$cose=fixtureCbor([1=>2,3=>-7,-1=>1,-2=>str_pad($ec['x'],32,"\0",STR_PAD_LEFT),-3=>str_pad($ec['y'],32,"\0",STR_PAD_LEFT)]);$auth=hash('sha256',$rp,true).chr(0x45).pack('N',1).str_repeat("\0",16).pack('n',32).$credential.$cose;
    $attestation="\xa3\x63fmt\x64none\x67attStmt\xa0\x68authData".fixtureCbor($auth);$client=Db::json(['type'=>'webauthn.create','origin'=>$origin,'challenge'=>$data['challenge']]);echo Db::json(['fixture'=>$id,'payload'=>['credentialId'=>Passkeys::encode($credential),'clientDataJSON'=>Passkeys::encode($client),'attestationObject'=>Passkeys::encode($attestation)]]);exit;
}
if($mode==='get'&&preg_match('/\A[a-f0-9]{32}\z/D',$data['fixture']??'')){
    $path=$directory.'/'.$data['fixture'].'.json';$key=json_decode(file_get_contents($path),true);$key['counter']++;$client=Db::json(['type'=>'webauthn.get','origin'=>$origin,'challenge'=>$data['challenge']]);$auth=hash('sha256',$rp,true).chr(0x05).pack('N',$key['counter']);openssl_sign($auth.hash('sha256',$client,true),$signature,openssl_pkey_get_private($key['pem']),OPENSSL_ALGO_SHA256);file_put_contents($path,Db::json($key),LOCK_EX);echo Db::json(['payload'=>['credentialId'=>$key['credential'],'clientDataJSON'=>Passkeys::encode($client),'authenticatorData'=>Passkeys::encode($auth),'signature'=>Passkeys::encode($signature)]]);exit;
}
throw new RuntimeException('Invalid isolated helper operation');
