<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Api,Auth,Config,Db,Http,Problem,Session};

/** Opaque, revocable grants. PKCE S256 is mandatory for every public client. */
final class OAuth {
    public const SCOPES=['read','write','publish','alerts','export','import','community','sharing:manage','sharing:write','home:read','home:write','home:manage'];
    public static function clients(array $p): array {
        if($p['token']!==null)throw new Problem(403,'application_owner','Folosește contul pentru administrarea aplicațiilor.');
        return ['items'=>Db::all('SELECT id,name,redirect_uris_json,scopes_json,active,created_at FROM api_clients WHERE owner_user_id=? ORDER BY id LIMIT 100',[$p['user']])];
    }
    public static function clientCreate(array $p,array $data): array {
        if($p['token']!==null)throw new Problem(403,'application_owner','Folosește contul pentru administrarea aplicațiilor.');
        if((int)Db::one('SELECT COUNT(*) AS n FROM api_clients WHERE owner_user_id=?',[$p['user']])['n']>=20)throw new Problem(422,'application_quota','Limita este de 20 de aplicații per cont.');
        $uris=$data['redirect_uris']??null;
        if(!is_array($uris)||!array_is_list($uris)||count($uris)<1||count($uris)>10)throw new Problem(422,'redirect_uri','Înregistrează între 1 și 10 adrese de revenire.');
        foreach($uris as $uri)self::redirect($uri);
        $scopes=self::scopes($data['scopes']??['read','home:read']);$id=Db::id();
        Db::insert('api_clients',['id'=>$id,'owner_user_id'=>$p['user'],'name'=>Catalog::text($data,'name',120),'redirect_uris_json'=>Db::json(array_values(array_unique($uris))),'scopes_json'=>Db::json($scopes),'created_at'=>Db::now()]);
        foreach(array_unique(array_filter(array_map(self::origin(...),$uris)))as$origin)Db::run('INSERT IGNORE INTO api_client_origins (client_id,origin) VALUES (?,?)',[$id,$origin]);
        Catalog::audit($p,'oauth.client_created',$id);return ['id'=>$id,'client_id'=>$id,'client_type'=>'public','token_endpoint_auth_method'=>'none','redirect_uris'=>$uris,'scopes'=>$scopes];
    }
    public static function redirect(mixed $uri): void {
        if(!is_string($uri)||strlen($uri)>2048||preg_match('/[\x00-\x20\x7f\\\\]/',$uri))throw new Problem(422,'redirect_uri','Adresă de revenire incorectă.');
        $u=parse_url($uri);$scheme=strtolower($u['scheme']??'');
        if(!$u||isset($u['fragment'])||isset($u['user'])||isset($u['pass'])||!$scheme)throw new Problem(422,'redirect_uri','Adresa nu poate conține fragment sau credențiale.');
        $https=$scheme==='https'&&!empty($u['host']);
        $loopback=$scheme==='http'&&in_array($u['host']??'',['127.0.0.1','[::1]'],true);
        $native=preg_match('/\A[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)+\z/D',$scheme)&&!isset($u['host'])&&str_starts_with($u['path']??'','/');
        if(!$https&&!$loopback&&!$native)throw new Problem(422,'redirect_uri','Folosește HTTPS, loopback IP sau o schemă nativă cu nume de domeniu inversat.');
        parse_str($u['query']??'',$query);
        if(array_intersect(array_keys($query),['code','state','iss','error','error_description']))throw new Problem(422,'redirect_uri','Adresa de revenire conține parametri rezervați protocolului.');
    }
    public static function origin(string $uri): ?string {
        $u=parse_url($uri);if(!in_array($u['scheme']??'',['http','https'],true)||!isset($u['host']))return null;
        return strtolower($u['scheme'].'://'.$u['host']).(isset($u['port'])?':'.$u['port']:'');
    }
    public static function scopes(mixed $value): array {
        if(is_string($value))$value=preg_split('/ +/',trim($value));
        if(!is_array($value)||!array_is_list($value)||!$value||count($value)>count(self::SCOPES))throw new Problem(422,'invalid_scope','Permisiuni incorecte.');
        foreach($value as $scope)if(!is_string($scope)||!in_array($scope,self::SCOPES,true))throw new Problem(422,'invalid_scope','Permisiune necunoscută.');
        return array_values(array_unique($value));
    }
    public static function request(array $data): array {
        $id=Catalog::text($data,'client_id',32);Api::id($id);
        $client=Db::one('SELECT * FROM api_clients WHERE id=? AND active=1',[$id]);
        if(!$client)throw new Problem(400,'invalid_client','Aplicația nu este disponibilă.');
        \App\Core\SecurityBudget::check('oauth_requests_client_hour',1000,3600,$id);
        $uri=Catalog::text($data,'redirect_uri',2048);
        if(!in_array($uri,json_decode($client['redirect_uris_json'],true),true))throw new Problem(400,'invalid_redirect_uri','Adresa de revenire nu este înregistrată.');
        if(($data['response_type']??'')!=='code')throw new Problem(400,'unsupported_response_type','Este acceptat numai Authorization Code.');
        $challenge=$data['code_challenge']??'';
        if(($data['code_challenge_method']??'')!=='S256'||!is_string($challenge)||!preg_match('/\A[A-Za-z0-9_-]{43}\z/D',$challenge))throw new Problem(400,'invalid_request','PKCE S256 este obligatoriu.');
        $scopes=self::scopes($data['scope']??'read');
        if(array_diff($scopes,json_decode($client['scopes_json'],true)))throw new Problem(400,'invalid_scope','Aplicația nu are aceste permisiuni înregistrate.');
        $home=array_filter($scopes,static fn($s)=>str_starts_with($s,'home:'));
        if($home&&count($home)!==count($scopes))throw new Problem(400,'invalid_scope','Accesul personal și cel de producător se autorizează separat.');
        if($home&&Config::get('home_sync_enabled',true)!==true)throw new Problem(503,'home_unavailable','Accesul personal sincronizat nu este activat.');
        $state=$data['state']??null;
        if(!is_string($state)||strlen($state)<8||strlen($state)>512||preg_match('/[\x00-\x1f\x7f]/',$state))throw new Problem(400,'invalid_request','State trebuie să conțină între 8 și 512 octeți.');
        $request=Db::id();
        Db::atomic(function()use($request,$id,$home,$uri,$state,$challenge,$scopes){
            Db::one('SELECT id FROM api_clients WHERE id=? FOR UPDATE',[$id]);
            if((int)Db::one('SELECT COUNT(*) AS n FROM api_oauth_requests WHERE client_id=? AND consumed_at IS NULL AND expires_at>?',[$id,Db::now()])['n']>=100)throw new Problem(429,'authorization_capacity','Aplicația are prea multe cereri de autorizare în așteptare.');
            Db::insert('api_oauth_requests',['id'=>$request,'client_id'=>$id,'realm'=>$home?'home':'producer','redirect_uri'=>$uri,'state_value'=>$state,'challenge'=>$challenge,'scopes_json'=>Db::json($scopes),'expires_at'=>gmdate('Y-m-d H:i:s',time()+600),'created_at'=>Db::now()]);
        });
        return self::pending($request);
    }
    public static function pending(string $id): array {
        Api::id($id);$r=Db::one('SELECT r.*,c.name AS client_name FROM api_oauth_requests r JOIN api_clients c ON c.id=r.client_id AND c.active=1 WHERE r.id=? AND r.expires_at>? AND r.consumed_at IS NULL',[$id,Db::now()]);
        if(!$r)throw new Problem(410,'authorization_expired','Cererea de autorizare a expirat sau a fost folosită. Revino în aplicație.');return $r;
    }
    public static function consent(array $request,array $principal,string $resource,bool $approved): string {
        return Db::atomic(function()use($request,$principal,$resource,$approved){
            Db::one('SELECT id FROM api_oauth_requests WHERE id=? FOR UPDATE',[$request['id']]);$r=self::pending($request['id']);
            if(!$approved){Db::run('UPDATE api_oauth_requests SET consumed_at=? WHERE id=?',[Db::now(),$r['id']]);return self::callback($r,['error'=>'access_denied']);}
            $scopes=json_decode($r['scopes_json'],true);$grant=Db::id();$device=null;
            if($r['realm']==='home'){
                HomeIdentity::deviceQuota($principal['account_id']);
                $m=Db::one('SELECT role FROM home_members WHERE account_id=? AND household_id=?',[$principal['account_id'],$resource]);
                if(!$m)throw new Problem(403,'home_membership','Nu ai acces la această familie.');
                if(in_array('home:manage',$scopes,true)&&$m['role']!=='owner')throw new Problem(403,'home_owner','Administrarea familiei necesită proprietarul.');
                if(in_array('home:write',$scopes,true)&&!in_array($m['role'],['owner','editor'],true))throw new Problem(403,'home_role','Nu ai drept de modificare în familie.');
                $device=Db::id();Db::insert('home_devices',['id'=>$device,'account_id'=>$principal['account_id'],'household_id'=>$resource,'name'=>mb_substr($r['client_name'],0,120),'token_hash'=>hash('sha256',random_bytes(32)),'expires_at'=>gmdate('Y-m-d H:i:s',time()+90*86400),'last_seen_at'=>Db::now(),'created_at'=>Db::now()]);
            }else{
                Db::one('SELECT id FROM users WHERE id=? FOR UPDATE',[$principal['id']]);
                if((int)Db::one('SELECT COUNT(*) AS n FROM api_oauth_grants WHERE user_id=? AND revoked_at IS NULL AND expires_at>?',[$principal['id'],Db::now()])['n']>=200)throw new Problem(422,'grant_quota','Retrage o autorizare nefolosită înainte de a adăuga alta.');
                $m=Db::one('SELECT role FROM memberships WHERE user_id=? AND tenant_id=?',[$principal['id'],$resource]);
                if(!$m)throw new Problem(403,'membership','Nu ai acces la organizație.');
                if(array_intersect($scopes,['publish','alerts','export','import'])&&!in_array($m['role'],['owner','quality'],true))throw new Problem(403,'role','Aceste permisiuni necesită un responsabil autorizat.');
                if(in_array('write',$scopes,true)&&!in_array($m['role'],['owner','quality','editor'],true))throw new Problem(403,'role','Nu ai drept de modificare.');
                if(in_array('sharing:manage',$scopes,true)&&$m['role']!=='owner')throw new Problem(403,'role','Acordurile de distribuire necesită proprietarul organizației.');
                if(in_array('sharing:write',$scopes,true)&&!in_array($m['role'],['owner','quality'],true))throw new Problem(403,'role','Distribuirea necesită un responsabil autorizat.');
            }
            Db::insert('api_oauth_grants',['id'=>$grant,'client_id'=>$r['client_id'],'realm'=>$r['realm'],'user_id'=>$r['realm']==='producer'?$principal['id']:null,'tenant_id'=>$r['realm']==='producer'?$resource:null,'home_account_id'=>$r['realm']==='home'?$principal['account_id']:null,'household_id'=>$r['realm']==='home'?$resource:null,'home_device_id'=>$device,'scopes_json'=>$r['scopes_json'],'expires_at'=>gmdate('Y-m-d H:i:s',time()+90*86400),'created_at'=>Db::now()]);
            $code=bin2hex(random_bytes(32));Db::insert('api_oauth_codes',['code_hash'=>hash('sha256',$code),'request_id'=>$r['id'],'grant_id'=>$grant,'expires_at'=>gmdate('Y-m-d H:i:s',time()+120),'created_at'=>Db::now()]);
            Db::run('UPDATE api_oauth_requests SET consumed_at=? WHERE id=?',[Db::now(),$r['id']]);
            if($r['realm']==='producer')Catalog::audit(['tenant'=>$resource,'user'=>$principal['id']],'oauth.granted',$grant,['client_id'=>$r['client_id'],'scopes'=>$scopes]);
            else HomeAudit::record(array_replace($principal,['household_id'=>$resource]),'oauth.granted',$grant,['client_id'=>$r['client_id'],'scopes'=>$scopes]);
            return self::callback($r,['code'=>$code]);
        });
    }
    private static function callback(array $request,array $query): string {
        return $request['redirect_uri'].(str_contains($request['redirect_uri'],'?')?'&':'?').http_build_query($query+['state'=>$request['state_value'],'iss'=>rtrim(Config::get('url'),'/')],'','&',PHP_QUERY_RFC3986);
    }
    public static function token(array $data): array {
        $client=Catalog::text($data,'client_id',32);Api::id($client);
        $kind=$data['grant_type']??'';
        if(!in_array($kind,['authorization_code','refresh_token'],true))throw new Problem(400,'unsupported_grant_type','Tip de autorizare neacceptat.');
        $result=Db::atomic(function()use($data,$client,$kind){
            if($kind==='authorization_code'){
                $code=$data['code']??'';$verifier=$data['code_verifier']??'';
                if(!is_string($code)||!preg_match('/\A[a-f0-9]{64}\z/D',$code)||!is_string($verifier)||!preg_match('/\A[A-Za-z0-9._~-]{43,128}\z/D',$verifier))return ['error'=>'invalid_grant'];
                $row=Db::one('SELECT c.*,r.client_id,r.redirect_uri,r.challenge FROM api_oauth_codes c JOIN api_oauth_requests r ON r.id=c.request_id WHERE c.code_hash=? FOR UPDATE',[hash('sha256',$code)]);
                $challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');
                if(!$row||$row['client_id']!==$client||($data['redirect_uri']??null)!==$row['redirect_uri']||!hash_equals($row['challenge'],$challenge)||$row['expires_at']<=Db::now())return ['error'=>'invalid_grant'];
                if($row['used_at']!==null){self::revokeGrant($row['grant_id']);return ['error'=>'invalid_grant'];}
                $grant=self::grant($row['grant_id']);if(!$grant)return ['error'=>'invalid_grant'];
                Db::run('UPDATE api_oauth_codes SET used_at=? WHERE code_hash=?',[Db::now(),$row['code_hash']]);
            }else{
                $refresh=$data['refresh_token']??'';
                if(!is_string($refresh)||!preg_match('/\Aoa_rt_[a-f0-9]{64}\z/D',$refresh))return ['error'=>'invalid_grant'];
                $row=Db::one('SELECT t.*,g.client_id FROM api_oauth_tokens t JOIN api_oauth_grants g ON g.id=t.grant_id WHERE t.refresh_hash=? FOR UPDATE',[hash('sha256',$refresh)]);
                if(!$row||$row['client_id']!==$client)return ['error'=>'invalid_grant'];
                if($row['refresh_used_at']!==null){self::revokeGrant($row['grant_id']);return ['error'=>'invalid_grant'];}
                if($row['revoked_at']!==null||$row['refresh_expires_at']<=Db::now())return ['error'=>'invalid_grant'];
                $grant=self::grant($row['grant_id']);if(!$grant)return ['error'=>'invalid_grant'];
                if(isset($data['scope'])&&self::scopes($data['scope'])!==json_decode($grant['scopes_json'],true))return ['error'=>'invalid_scope'];
                Db::run('UPDATE api_oauth_tokens SET refresh_used_at=?,revoked_at=? WHERE id=?',[Db::now(),Db::now(),$row['id']]);
            }
            return self::issue($grant);
        });
        if(isset($result['error']))throw new Problem(400,$result['error'],'Codul sau tokenul nu mai este valid pentru această cerere.');return $result;
    }
    private static function issue(array $grant): array {
        $access='oa_at_'.bin2hex(random_bytes(32));$refresh='oa_rt_'.bin2hex(random_bytes(32));
        $accessUntil=min(time()+900,strtotime($grant['expires_at'].' UTC'));$refreshUntil=min(time()+30*86400,strtotime($grant['expires_at'].' UTC'));
        Db::insert('api_oauth_tokens',['id'=>Db::id(),'grant_id'=>$grant['id'],'access_hash'=>hash('sha256',$access),'refresh_hash'=>hash('sha256',$refresh),'access_expires_at'=>gmdate('Y-m-d H:i:s',$accessUntil),'refresh_expires_at'=>gmdate('Y-m-d H:i:s',$refreshUntil),'created_at'=>Db::now()]);
        return ['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>$accessUntil-time(),'refresh_token'=>$refresh,'refresh_expires_in'=>$refreshUntil-time(),'scope'=>implode(' ',json_decode($grant['scopes_json'],true)),'grant_id'=>$grant['id']];
    }
    private static function grant(string $id): ?array {
        $g=Db::one('SELECT g.* FROM api_oauth_grants g JOIN api_clients c ON c.id=g.client_id AND c.active=1 WHERE g.id=? AND g.revoked_at IS NULL AND g.expires_at>?',[$id,Db::now()]);
        if(!$g)return null;
        if($g['realm']==='producer')return Db::one('SELECT m.role FROM memberships m JOIN users u ON u.id=m.user_id AND u.active=1 WHERE m.tenant_id=? AND m.user_id=?',[$g['tenant_id'],$g['user_id']])?$g:null;
        return Db::one('SELECT m.role FROM home_members m JOIN home_devices d ON d.account_id=m.account_id AND d.household_id=m.household_id WHERE m.household_id=? AND m.account_id=? AND d.id=? AND d.revoked_at IS NULL AND d.expires_at>?',[$g['household_id'],$g['home_account_id'],$g['home_device_id'],Db::now()])?$g:null;
    }
    public static function access(string $secret,string $scope,string $realm): array {
        Api::installed();$t=Db::one('SELECT grant_id,id FROM api_oauth_tokens WHERE access_hash=? AND revoked_at IS NULL AND access_expires_at>?',[hash('sha256',$secret),Db::now()]);
        $g=$t?self::grant($t['grant_id']):null;
        if(!$g||$g['realm']!==$realm){header('WWW-Authenticate: Bearer realm="OpenAliment", error="invalid_token"');throw new Problem(401,'invalid_token','Autorizarea a expirat sau a fost revocată.');}
        $scopes=json_decode($g['scopes_json'],true);if($scope!==''&&!in_array($scope,$scopes,true))throw new Problem(403,'scope','Aplicația nu are permisiunea necesară.');
        return $g+['token_id'=>$t['id'],'scopes'=>$scopes];
    }
    public static function producer(string $secret,string $scope): array {
        $g=self::access($secret,$scope,'producer');$m=Db::one('SELECT role FROM memberships WHERE tenant_id=? AND user_id=?',[$g['tenant_id'],$g['user_id']]);
        return ['tenant'=>$g['tenant_id'],'user'=>$g['user_id'],'role'=>$m['role'],'admin'=>false,'token'=>$g['id'],'grant_id'=>$g['id'],'client_id'=>$g['client_id'],'scopes'=>$g['scopes'],'auth_type'=>'oauth'];
    }
    public static function home(string $secret,string $scope): array {
        $g=self::access($secret,$scope,'home');$r=Db::one('SELECT a.email,m.role,h.name AS household_name FROM home_accounts a JOIN home_members m ON m.account_id=a.id JOIN home_households h ON h.id=m.household_id WHERE a.id=? AND m.household_id=?',[$g['home_account_id'],$g['household_id']]);
        return $r+['account_id'=>$g['home_account_id'],'household_id'=>$g['household_id'],'device_id'=>$g['home_device_id'],'grant_id'=>$g['id'],'token'=>$g['token_id'],'scopes'=>$g['scopes'],'csrf'=>null];
    }
    public static function revoke(string $client,string $token): void {
        if(strlen($token)>180)return;
        $hash=hash('sha256',$token);$g=Db::one('SELECT g.id FROM api_oauth_grants g JOIN api_oauth_tokens t ON t.grant_id=g.id WHERE g.client_id=? AND (t.access_hash=? OR t.refresh_hash=?)',[$client,$hash,$hash]);
        if($g)Db::atomic(fn()=>self::revokeGrant($g['id']));
    }
    public static function revokeGrant(string $id): void {
        Db::run('UPDATE api_oauth_grants SET revoked_at=COALESCE(revoked_at,?) WHERE id=?',[Db::now(),$id]);
        Db::run('UPDATE api_oauth_tokens SET revoked_at=COALESCE(revoked_at,?) WHERE grant_id=?',[Db::now(),$id]);
        Db::run('UPDATE home_devices d JOIN api_oauth_grants g ON g.home_device_id=d.id SET d.revoked_at=COALESCE(d.revoked_at,?) WHERE g.id=?',[Db::now(),$id]);
    }
}
