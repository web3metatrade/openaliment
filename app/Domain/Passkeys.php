<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Config,Db,Migrations,Passwords,Problem,Session};
use lbuchs\WebAuthn\WebAuthn;

/** Server-side WebAuthn verification is delegated to the pinned upstream implementation. */
final class Passkeys {
    public static function count(string $user): int {return Migrations::readiness()?(int)Db::one('SELECT COUNT(*) AS n FROM passkey_credentials WHERE user_id=?',[$user])['n']:0;}
    public static function required(array $user): bool {return Migrations::readiness()&&(self::count($user['id'])>0||((bool)$user['is_admin']&&Config::get('mfa_required_for_admin',false)===true));}
    public static function full(array $user): void {if(self::required($user)&&empty($_SESSION['mfa_verified_at']))throw new Problem(403,'mfa_required','Confirmă cheia de acces pentru a continua.');}
    public static function recent(array $user): void {
        self::full($user);$at=self::required($user)?($_SESSION['mfa_verified_at']??0):($_SESSION['authenticated_at']??0);
        if($at<time()-900)throw new Problem(403,'reauthentication','Confirmă din nou accesul la cont.');
    }
    public static function list(string $user): array {return Migrations::readiness()?Db::all('SELECT id,name,created_at,last_used_at,backup_eligible,backed_up FROM passkey_credentials WHERE user_id=? ORDER BY created_at LIMIT 20',[$user]):[];}
    private static function webauthn(): WebAuthn {
        $host=parse_url((string)Config::get('url'),PHP_URL_HOST);if(!is_string($host)||$host==='')throw new Problem(503,'passkey_config','Cheile de acces nu sunt disponibile momentan.');
        return new WebAuthn('OpenAliment',$host,['none','packed','fido-u2f','apple','android-key','tpm'],true);
    }
    public static function begin(array $user,string $kind,array $data=[]): array {
        AccountChallenges::ready();Session::start();Auth::rate('passkeys|'.$user['id'],20,900);
        $web=self::webauthn();$ids=array_map(static fn($r)=>self::decode($r['credential_id']),Db::all('SELECT credential_id FROM passkey_credentials WHERE user_id=?',[$user['id']]));
        if($kind==='create'){
            if(self::count($user['id'])>0)self::recent($user);elseif(($_SESSION['authenticated_at']??0)<time()-900)throw new Problem(403,'reauthentication','Conectează-te din nou înainte de a adăuga cheia.');$record=Db::one('SELECT password_hash FROM users WHERE id=? AND active=1',[$user['id']]);
            if(!$record||!Passwords::verify($data['password']??null,$record['password_hash']))throw new Problem(403,'reauthentication','Parola actuală nu este corectă.');
            if(count($ids)>=10)throw new Problem(422,'passkey_quota','Poți păstra maximum zece chei de acces.');
            $args=$web->getCreateArgs(hex2bin($user['id']),$user['email'],$user['display_name'],120,false,true,null,$ids);$args->publicKey->attestation='none';unset($args->publicKey->extensions);
        }elseif($kind==='get'){
            if(!$ids)throw new Problem(422,'passkey_missing','Adaugă mai întâi o cheie de acces.');$args=$web->getGetArgs($ids,120,true,true,true,true,true,true);
        }else throw new Problem(422,'passkey_operation','Operațiune incorectă.');
        $_SESSION['passkey_challenge']=['kind'=>$kind,'challenge'=>base64_encode($web->getChallenge()->getBinaryString()),'user'=>$user['id'],'session'=>hash('sha256',session_id()),'expires'=>time()+180,'name'=>$kind==='create'?Catalog::text($data,'name',120):''];
        return json_decode(json_encode($args,JSON_THROW_ON_ERROR),true,32,JSON_THROW_ON_ERROR);
    }
    public static function finish(array $user,string $kind,array $data): array {
        AccountChallenges::ready();Session::start();Auth::rate('passkey-finish|'.$user['id'],10,900);$challenge=$_SESSION['passkey_challenge']??null;unset($_SESSION['passkey_challenge']);
        if(!is_array($challenge)||$challenge['kind']!==$kind||$challenge['user']!==$user['id']||$challenge['expires']<time()||!hash_equals($challenge['session'],hash('sha256',session_id())))throw new Problem(410,'passkey_challenge','Confirmarea a expirat. Încearcă din nou.');
        $client=self::decode($data['clientDataJSON']??null);try{$clientObject=json_decode($client,true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(422,'passkey_encoding','Datele cheii de acces sunt incorecte.');}
        if(($clientObject['origin']??null)!==rtrim((string)Config::get('url'),'/')||($clientObject['crossOrigin']??false)!==false||isset($clientObject['topOrigin']))throw new Problem(403,'passkey_origin','Originea confirmării este incorectă.');
        $web=self::webauthn();$expected=base64_decode($challenge['challenge'],true);
        try{
            if($kind==='create'){
                $result=$web->processCreate($client,self::decode($data['attestationObject']??null),$expected,true,true,false,false);
                if(!hash_equals($result->credentialId,self::decode($data['credentialId']??null)))throw new Problem(403,'passkey','Identificatorul cheii nu corespunde confirmării.');
                $id=Db::id();Db::atomic(function()use($result,$user,$id,$challenge){Db::one('SELECT id FROM users WHERE id=? FOR UPDATE',[$user['id']]);if(self::count($user['id'])>=10)throw new Problem(422,'passkey_quota','Limita de chei este atinsă.');
                    Db::insert('passkey_credentials',['id'=>$id,'credential_sha256'=>hash('sha256',$result->credentialId),'credential_id'=>self::encode($result->credentialId),'user_id'=>$user['id'],'name'=>$challenge['name'],'public_key'=>$result->credentialPublicKey,'signature_counter'=>$result->signatureCounter??0,'backup_eligible'=>$result->isBackupEligible?1:0,'backed_up'=>$result->isBackedUp?1:0,'created_at'=>Db::now()]);});
                self::complete($user['id']);$codes=self::recoveryCodes($user);SecurityOutbox::notice($user['id'],'passkey-created','O cheie de acces nouă a fost adăugată contului.');return ['created'=>true,'id'=>$id,'recovery_codes'=>$codes,'csrf'=>Session::csrf(),'redirect'=>'/manage/security'];
            }
            $rawId=self::decode($data['credentialId']??null);
            Db::atomic(function()use($rawId,$user,$web,$client,$data,$expected){
                $key=Db::one('SELECT * FROM passkey_credentials WHERE credential_sha256=? AND user_id=? FOR UPDATE',[hash('sha256',$rawId),$user['id']]);if(!$key)throw new Problem(403,'passkey','Cheia de acces nu aparține contului.');
                $web->processGet($client,self::decode($data['authenticatorData']??null),self::decode($data['signature']??null),$key['public_key'],$expected,(int)$key['signature_counter'],true,true);
                Db::run('UPDATE passkey_credentials SET signature_counter=?,last_used_at=? WHERE id=?',[$web->getSignatureCounter()??0,Db::now(),$key['id']]);
            });self::complete($user['id']);return ['verified'=>true,'csrf'=>Session::csrf(),'redirect'=>$_SESSION['post_mfa_return']??'/manage'];
        }catch(\lbuchs\WebAuthn\WebAuthnException $error){\App\Core\SecurityLog::record('passkey.rejected',['user_id'=>$user['id'],'code'=>'webauthn.'.$error->getCode()]);throw new Problem(403,'passkey','Cheia de acces nu a putut fi verificată. Încearcă din nou.');}
    }
    private static function complete(string $user): void {
        $return=$_SESSION['mfa_return']??'/manage';
        if(isset($_SESSION['producer_session_id']))Db::run('UPDATE producer_sessions SET revoked_at=COALESCE(revoked_at,?) WHERE id=?',[Db::now(),$_SESSION['producer_session_id']]);
        Session::login($user,true);$_SESSION['post_mfa_return']=is_string($return)&&preg_match('~\A/oauth/authorize\?request=[a-f0-9]{32}\z~D',$return)?$return:'/manage';
    }
    public static function recoveryCodes(array $user): array {
        self::recent($user);if(self::count($user['id'])===0)throw new Problem(422,'passkey_missing','Adaugă o cheie de acces înainte de a genera coduri.');
        return Db::atomic(function()use($user){Db::one('SELECT id FROM users WHERE id=? FOR UPDATE',[$user['id']]);Db::run('DELETE FROM account_recovery_codes WHERE user_id=?',[$user['id']]);$codes=[];
            for($i=0;$i<10;$i++){$code=implode('-',str_split(bin2hex(random_bytes(10)),4));Db::insert('account_recovery_codes',['user_id'=>$user['id'],'code_hash'=>hash('sha256',str_replace('-','',$code)),'created_at'=>Db::now()]);$codes[]=$code;}return $codes;});
    }
    public static function recover(array $user,string $code): array {
        AccountChallenges::ready();Auth::rate('mfa-recovery|'.$user['id'],5,3600);$code=strtolower(str_replace(['-',' '],'',$code));
        if(!preg_match('/\A[a-f0-9]{20}\z/D',$code))throw new Problem(403,'recovery_code','Codul nu este valid.');
        Db::atomic(function()use($user,$code){$hash=hash('sha256',$code);$row=Db::one('SELECT used_at FROM account_recovery_codes WHERE user_id=? AND code_hash=? FOR UPDATE',[$user['id'],$hash]);if(!$row||$row['used_at']!==null)throw new Problem(403,'recovery_code','Codul nu este valid sau a fost folosit.');Db::run('UPDATE account_recovery_codes SET used_at=? WHERE user_id=? AND code_hash=?',[Db::now(),$user['id'],$hash]);});
        self::complete($user['id']);SecurityOutbox::notice($user['id'],'mfa-recovery','Un cod de recuperare a fost folosit pentru conectare. Verifică și înlocuiește cheia pierdută.');return ['verified'=>true,'csrf'=>Session::csrf(),'redirect'=>'/manage/security'];
    }
    public static function remove(array $user,string $id): array {
        self::recent($user);return Db::atomic(function()use($user,$id){Db::one('SELECT id FROM users WHERE id=? FOR UPDATE',[$user['id']]);if(self::count($user['id'])<2)throw new Problem(422,'last_passkey','Adaugă și verifică o altă cheie înainte de a o elimina pe ultima.');
            if(!Db::run('DELETE FROM passkey_credentials WHERE user_id=? AND id=?',[$user['id'],$id])->rowCount())throw new Problem(404,'passkey','Cheia nu a fost găsită.');SecurityOutbox::notice($user['id'],'passkey-removed','O cheie de acces a fost eliminată din cont.');return ['removed'=>true];});
    }
    public static function encode(string $value): string {return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
    public static function decode(mixed $value): string {
        if(!is_string($value)||$value===''||strlen($value)>200000||!preg_match('/\A[A-Za-z0-9_-]+\z/D',$value))throw new Problem(422,'passkey_encoding','Datele cheii de acces sunt incorecte.');
        $decoded=base64_decode(strtr($value,'-_','+/'),true);if($decoded===false||$decoded===''||self::encode($decoded)!==$value)throw new Problem(422,'passkey_encoding','Datele cheii de acces sunt incorecte.');return $decoded;
    }
}
