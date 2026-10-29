<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Api,ApiRoutes,Auth,Config,Db,Http,Problem,Session};
use App\Domain\{Accounts,ApiKeys,ApiManage,ApiPublic,Catalog,Community,ConsumerFood,Documents,Mutations,OAuth,Portability};

final class PlatformApiController {
    public static function handle(string $method,string $path): void {
        $route=ApiRoutes::find($method==='HEAD'?'GET':$method,$path);
        if(!$route)throw new Problem(404,'route','Operațiunea API nu a fost găsită.');
        $handler=$route['handler'];$id=$route['resource_id'];$p=null;
        if(str_starts_with($handler,'program.')){ProgramController::api($method,$route);return;}
        if(str_starts_with($handler,'anaf.')){RegistrationController::api($method,$route);return;}
        if(str_starts_with($handler,'external.')){ExternalSourcesController::api($method,$route);return;}
        if(str_starts_with($handler,'standards.')){Gs1StandardsController::api($method,$route);return;}
        if(str_starts_with($handler,'marketplace.')){MarketplaceController::api($method,$route);return;}
        if(str_starts_with($handler,'account.')){AccountAccessController::handle($method,$path);return;}
        if(in_array($route['auth'],['producer','owner-session','session','admin-session'],true)){
            if($route['auth']!=='producer'&&Api::bearer()!==null)throw new Problem(403,'browser_session','Operațiunea necesită sesiunea contului în browser.');
            $p=Auth::principal($route['scope']);Api::limit('private-read',1,$p['token']??$p['user']);
            if($route['auth']==='owner-session')ApiKeys::owner($p);
            if($route['auth']==='admin-session')Auth::admin();
        }
        if($handler==='manage.upload'){
            Api::write($p);Auth::write($p);$file=$_FILES['document']??[];$data=$_POST;
            if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new Problem(422,'upload','Trimite documentul în câmpul multipart document.');
            if((int)$file['size']>15000000)throw new Problem(413,'body_size','Documentul depășește 15 MB.');
            $data['file_sha256']=hash_file('sha256',$file['tmp_name']);$data['file_bytes']=$file['size'];
            Api::mutation($p,$data,fn()=>Documents::upload($p,$id,$file,$data));
        }
        if($handler==='manage.importStream'){
            Auth::approve($p,'import');Api::write($p);$media=strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]));
            if($media!=='application/x-ndjson'||!in_array(strtolower($_SERVER['HTTP_CONTENT_ENCODING']??'identity'),['','identity'],true))throw new Problem(415,'media_type','Trimite application/x-ndjson fără compresie.');
            if((int)($_SERVER['CONTENT_LENGTH']??0)>\App\Domain\TransferStream::MAX_BYTES)throw new Problem(413,'body_size','Importul depășește 24 MB.');
            $dry=match($_GET['dry_run']??'true'){'true','1'=>true,'false','0'=>false,default=>throw new Problem(422,'dry_run','dry_run trebuie să fie true sau false.')};
            $stream=fopen('php://input','rb');try{$package=\App\Domain\TransferStream::read($stream);}finally{if(is_resource($stream))fclose($stream);}
            if($dry)Http::json(Portability::import($p,$package,true));Api::mutation($p,['data_sha256'=>$package['data_sha256'],'dry_run'=>false],fn()=>Portability::import($p,$package,false),200);
        }
        $data=in_array($method,['POST','PUT','PATCH'],true)?Http::input($handler==='manage.import'?24000000:2000000,['application/json']):[];
        if(in_array($method,['POST','PUT','PATCH'],true))\App\Core\ApiInput::validate($data,$route['body']);
        if($route['auth']==='session-optional'&&Api::bearer()!==null)throw new Problem(403,'browser_session','Operațiunea necesită browserul propriu.');
        if($handler==='admin.operations')Http::json(\App\Domain\Operations::status());
        if($handler==='manage.documentScan'){$doc=Catalog::owned('documents',$id,$p);if($method==='POST'){Api::write($p);Auth::approve($p);Api::limit('private-write',1,$p['token']??$p['user']);Http::json(\App\Domain\DocumentScanner::scan($doc));}Http::json(\App\Domain\DocumentScanner::status($doc));}
        if($handler==='admin.list'){
            [$limit,$cursor]=Api::page();$type=basename($path);
            $rows=match($type){
                'organizations'=>Db::all('SELECT id,display_name,identity_status,created_at FROM organizations WHERE id>? ORDER BY id LIMIT '.($limit+1),[$cursor]),
                'users'=>Db::all('SELECT id,email,display_name,email_verified,active,created_at FROM users WHERE id>? ORDER BY id LIMIT '.($limit+1),[$cursor]),
                default=>Db::all('SELECT id,tenant_id,name,gtin14,gtin_status,claim_evidence,verified_by,verified_at FROM trade_items WHERE id>? AND gtin14 IS NOT NULL ORDER BY id LIMIT '.($limit+1),[$cursor])};
            Http::json(Api::collection($rows,$limit));
        }
        if(str_starts_with($handler,'admin:'))Api::mutation($p,$data,fn()=>\App\Domain\Administration::decide(Auth::admin(),substr($handler,6),$id,$data),200);
        if($handler==='public.get')Http::json(ApiPublic::get($path,$id));
        if($handler==='public.catalog')Http::json(ApiPublic::catalog());
        if($handler==='public.children')Http::json(ApiPublic::children($path,$id));
        if($handler==='public.feedback')Http::json(ApiPublic::feedback($id));
        if($handler==='public.ratings')Http::json(ApiPublic::ratings($id));
        if($handler==='public.download')ApiPublic::download($id);
        if($handler==='public.archive')\App\Domain\PublicArchive::download($id);
        if($handler==='public.vocabulary')Http::json(ApiPublic::vocabulary($path));
        if($handler==='public.lookup'){
            $codes=$data['codes']??null;if(!is_array($codes)||!array_is_list($codes)||!$codes||count($codes)>20)throw new Problem(422,'codes','Trimite între 1 și 20 de coduri.');
            Api::limit('public',count($codes));$items=[];
            foreach($codes as$code){if(!is_string($code)||strlen($code)>4096)throw new Problem(422,'code','Cod incorect.');try{$items[]=['code'=>$code,'data'=>ConsumerFood::lookup($code)];}catch(Problem $e){$items[]=['code'=>$code,'error'=>['error_code'=>$e->errorCode,'message'=>$e->getMessage()]];}}
            Http::json(['items'=>$items]);
        }
        if($handler==='public.identifier'){Api::limit('tools');Http::json(['valid'=>true,'identity'=>ConsumerFood::code(Catalog::text($data,'code',4096)),'rights_verified'=>false]);}
        if($handler==='community.feedback')Http::json(Community::feedback($data),201);
        if(str_starts_with($handler,'community.')){
            $u=Db::one('SELECT id,email_verified FROM users WHERE id=? AND active=1',[$p['user']]);
            if(!$u||!$u['email_verified'])throw new Problem(403,'verification','Participarea necesită contact verificat.');
            if($handler==='community.votes'){
                [$limit,$cursor]=Api::page();$rows=\App\Core\Migrations::readiness()?Db::all('SELECT proposal_id AS id,active,mutation_version,updated_at FROM community_vote_states WHERE user_id=? AND proposal_id>? ORDER BY proposal_id LIMIT '.($limit+1),[$u['id'],$cursor]):Db::all('SELECT proposal_id AS id,created_at FROM votes WHERE user_id=? AND proposal_id>? ORDER BY proposal_id LIMIT '.($limit+1),[$u['id'],$cursor]);Http::json(Api::collection($rows,$limit));
            }
            Api::write($p);Api::limit('private-write',1,$p['token']??$p['user']);Auth::rate('api-vote|'.$u['id'],60,3600);
            if(!Db::one("SELECT id FROM change_proposals WHERE id=? AND visibility='public'",[$id]))throw new Problem(404,'not_found','Propunerea publică nu a fost găsită.');
            $expected=$data['expected_mutation_version']??$_GET['expected_mutation_version']??null;if(is_string($expected)&&ctype_digit($expected)&&strlen($expected)<11)$expected=(int)$expected;if($expected!==null&&(!is_int($expected)||$expected<0))throw new Problem(422,'vote_version','Versiunea votului este incorectă.');
            Http::json(Community::voteFor($u['id'],$id,$method==='PUT',$expected));
        }
        if($handler==='manage.dashboard')Http::json(ApiManage::dashboard($p));
        if($handler==='manage.quotas')Http::json(\App\Domain\ProducerQuota::summary($p));
        if($handler==='manage.organization')Http::json(['organization'=>Db::one('SELECT id,display_name,identity_status FROM organizations WHERE id=?',[$p['tenant']]),'role'=>$p['role']]);
        if($handler==='manage.list')Http::json(ApiManage::list($p,explode('/',$path)[4]));
        if($handler==='manage.get')Http::json(ApiManage::get($p,explode('/',$path)[4],$id));
        if($handler==='manage.children')Http::json(ApiManage::children($p,$path,$id));
        if($handler==='manage.download')ApiPublic::download($id,$p);
        if($handler==='manage.connector')Http::json(ApiManage::connector($p,$path,$id));
        if($handler==='manage.qr')Http::json(Catalog::qr($p,explode('/',$path)[5],$id));
        if($handler==='manage.preview'){$r=Catalog::owned('product_info_versions',$id,$p);Http::json(['id'=>$id,'state'=>$r['state'],'snapshot'=>json_decode($r['snapshot_json'],true),'snapshot_sha256'=>$r['snapshot_sha256'],'writes'=>false]);}
        if($handler==='manage.validate'){Auth::write($p);Api::write($p);Http::json(['valid'=>true,'snapshot'=>Catalog::labelData($data),'writes'=>false]);}
        if(str_starts_with($handler,'mutation:'))Api::mutation($p,$data,fn()=>Mutations::run($p,substr($handler,9),$id,$data));
        if($handler==='manage.export'){if(($_GET['format']??'')==='ndjson')Portability::stream($p);Http::json(Portability::export($p));}
        if($handler==='manage.import'){
            Api::write($p);$dry=($data['dry_run']??true)!==false;
            $run=fn()=>str_ends_with($path,'/import-pbt')?Portability::pilot($p,$dry):Portability::import($p,$data['package']??[],$dry);
            if($dry)Http::json($run());Api::mutation($p,$data,$run,200);
        }
        if($handler==='manage.ai'){if($method==='GET')Http::json(ApiManage::ai($p,$method,$data));Api::mutation($p,$data,fn()=>ApiManage::ai($p,$method,$data),202);}
        if(str_starts_with($handler,'keys.')){
            Api::installed();if($handler==='keys.list')Http::json(ApiKeys::list($p));
            Api::write($p);Api::limit('private-write',1,$p['user']);
            if($handler==='keys.revoke')Http::json(ApiKeys::revoke($p,$id));
            ApiKeys::recent($p,$data);unset($data['current_password']);$secret=null;
            $run=function()use($p,$data,$id,$handler,&$secret){$r=$handler==='keys.create'?ApiKeys::create($p,$data):ApiKeys::rotate($p,$id,$data);$secret=$r['token'];unset($r['token']);return $r+['secret_available'=>false];};
            $result=Mutations::idempotent($p,$_SERVER['HTTP_IDEMPOTENCY_KEY']??'',hash('sha256',$path.'|'.Db::json($data)),$run);
            if($secret!==null){$result['token']=$secret;$result['secret_available']=true;}
            Http::json($result,201);
        }
        if(str_starts_with($handler,'token.')){
            if(!$p['token'])throw new Problem(422,'bearer_required','Trimite credentialul Bearer pentru această operațiune.');
            if($handler==='token.inspect')Http::json(['organization_id'=>$p['tenant'],'user_id'=>$p['user'],'role'=>$p['role'],'scopes'=>$p['scopes'],'credential_id'=>$p['token'],'auth_type'=>$p['auth_type']]);
            if(isset($p['grant_id']))Db::atomic(fn()=>OAuth::revokeGrant($p['grant_id']));
            else{Db::run('UPDATE api_tokens SET revoked_at=? WHERE id=? AND tenant_id=?',[Db::now(),$p['token'],$p['tenant']]);Catalog::audit($p,'token.self_revoked',$p['token']);}Http::json(['revoked'=>true]);
        }
        if(str_starts_with($handler,'developer.')){
            Api::installed();
            if($method==='GET')Http::json(OAuth::clients($p));
            if($handler==='developer.apps')Api::mutation($p,$data,fn()=>OAuth::clientCreate($p,$data));
            Api::write($p);$c=Db::one('SELECT id FROM api_clients WHERE id=? AND owner_user_id=?',[$id,$p['user']]);if(!$c)throw new Problem(404,'not_found','Aplicația nu a fost găsită.');
            Db::run('UPDATE api_clients SET active=0 WHERE id=?',[$id]);Catalog::audit($p,'oauth.client_suspended',$id);Http::json(['id'=>$id,'active'=>false]);
        }
        if(str_starts_with($handler,'identity.'))self::identity($handler,$method,$data,$p,$id);
        throw new Problem(404,'route','Operațiunea nu a fost găsită.');
    }
    private static function identity(string $handler,string $method,array $d,?array $p,string $id): never {
        Session::start();
        if($handler==='identity.session'){
            if(Api::bearer()!==null){$who=Auth::principal('');Http::json(['authenticated'=>true,'realm'=>'producer','organization_id'=>$who['tenant'],'role'=>$who['role'],'scopes'=>$who['scopes']]);}
            $user=Auth::user();Http::json(['authenticated'=>$user!==null,'user'=>$user,'csrf'=>Session::csrf(),'registration_enabled'=>\App\Domain\RegistrationSettings::enabled()]);
        }
        if($method!=='GET'){Http::sameOrigin();Session::verify();}
        if($handler==='identity.login'){Accounts::login($d);$user=Auth::user();$pending=$user&&\App\Domain\Passkeys::required($user);Http::json(['authenticated'=>!$pending,'mfa_required'=>(bool)$pending,'verification_url'=>$pending?'/account/verify':null,'user'=>$user,'csrf'=>Session::csrf()]);}
        if(in_array($handler,['identity.password','identity.access-revoke'],true)){Accounts::secure($p,$d+['action'=>$handler==='identity.password'?'password':'revoke']);Http::json(['authenticated'=>true,'user'=>Auth::user(),'csrf'=>Session::csrf(),'access_revoked'=>true]);}
        if($handler==='identity.register'){Auth::rate('register|'.\App\Core\Api::clientIp(),5,3600);$r=Accounts::register($d);Session::login($r['id']);Http::json(['authenticated'=>true,'user_id'=>$r['id'],'organization_id'=>$r['tenant_id'],'csrf'=>Session::csrf()],201);}
        if($handler==='identity.logout'){\App\Domain\ProducerSessions::endCurrent();$_SESSION=[];session_destroy();setcookie('oa_session','',['expires'=>time()-3600,'path'=>'/','secure'=>Config::get('session_secure',true),'httponly'=>true,'samesite'=>'Lax']);Http::json(['authenticated'=>false]);}
        if($handler==='identity.organizations')Http::json(['items'=>Db::all('SELECT o.id,o.display_name,m.role FROM organizations o JOIN memberships m ON m.tenant_id=o.id WHERE m.user_id=? ORDER BY o.id',[$p['user']])]);
        if($handler==='identity.select'){$tenant=Api::id(Catalog::text($d,'organization_id',32));if(!Db::one('SELECT tenant_id FROM memberships WHERE user_id=? AND tenant_id=?',[$p['user'],$tenant]))throw new Problem(403,'membership','Nu ai acces la organizație.');$_SESSION['tenant_id']=$tenant;Http::json(['organization_id'=>$tenant]);}
        if($handler==='identity.grants'){
            Api::installed();if($method==='GET')Http::json(['items'=>Db::all('SELECT g.id,g.client_id,c.name,g.tenant_id,g.scopes_json,g.created_at,g.expires_at,g.revoked_at FROM api_oauth_grants g JOIN api_clients c ON c.id=g.client_id WHERE g.user_id=? ORDER BY g.id LIMIT 100',[$p['user']])]);
            if(!Db::one('SELECT id FROM api_oauth_grants WHERE id=? AND user_id=?',[$id,$p['user']]))throw new Problem(404,'not_found','Autorizarea nu a fost găsită.');Db::atomic(fn()=>OAuth::revokeGrant($id));Http::json(['id'=>$id,'revoked'=>true]);
        }
        throw new Problem(404,'route','Operațiunea de identitate nu a fost găsită.');
    }
}
