<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Config,Http,Mailer,Migrations,Problem};
use App\Domain\{HomeIdentity,HomeHouseholds,HomeInventory,HomePhotos,HomeSync};

final class HomeController {
    public static function handle(string $method,string $path): void {
        header('X-Robots-Tag: noindex, nofollow');header('Allow: GET, HEAD, POST');if($method==='HEAD')$method='GET';
        if(!in_array($method,['GET','POST'],true))throw new Problem(405,'method','Metodă nepermisă.');
        if(Config::get('home_sync_enabled',true)!==true||!Migrations::home()){
            if($method==='GET'&&$path==='/api/v2/home/session')Http::json(['authenticated'=>false,'available'=>false,'magic_link_ready'=>false]);
            throw new Problem(503,'home_setup','Sincronizarea nu este disponibilă momentan. Alimentele rămân pe dispozitiv.');
        }
        if($method==='GET'){
            $p=HomeIdentity::principal($path!=='/api/v2/home/session');
            if($path==='/api/v2/home/session')Http::json(['authenticated'=>$p!==null,'available'=>true,'magic_link_ready'=>Mailer::ready(),'session'=>$p?array_intersect_key($p,array_flip(['account_id','device_id','household_id','household_name','email','role','csrf','scopes','grant_id'])):null,'devices'=>$p?(isset($p['grant_id'])?[['id'=>$p['device_id'],'name'=>'Aplicația autorizată']]:HomeIdentity::devices($p)):[]]);
            if($path==='/api/v2/home/changes'){
                $after=$_GET['after']??'0';$limit=$_GET['limit']??'50';if(!is_string($after)||!preg_match('/\A[0-9]{1,18}\z/D',$after)||!is_string($limit)||!ctype_digit($limit))throw new Problem(422,'home_cursor','Cursor incorect.');Http::json(HomeSync::changes($p,(int)$after,(int)$limit));
            }
            if($path==='/api/v2/home/members')Http::json(['items'=>HomeHouseholds::members($p)]);
            if($path==='/api/v2/home/households')Http::json(['items'=>isset($p['grant_id'])?array_values(array_filter(HomeHouseholds::list($p),static fn($h)=>$h['id']===$p['household_id'])):HomeHouseholds::list($p)]);
            if($path==='/api/v2/home/grants'){
                \App\Core\Api::installed();if(isset($p['grant_id']))throw new Problem(403,'account_session','Administrarea autorizărilor necesită sesiunea personală.');
                Http::json(['items'=>\App\Core\Db::all('SELECT g.id,c.name,g.household_id,g.scopes_json,g.expires_at,g.revoked_at,g.created_at FROM api_oauth_grants g JOIN api_clients c ON c.id=g.client_id WHERE g.home_account_id=? ORDER BY g.id LIMIT 100',[$p['account_id']])]);
            }
            if(preg_match('~\A/api/v2/home/photos/([a-f0-9]{32})\z~D',$path,$m)){$file=HomePhotos::read($p,$m[1]);header('Content-Type: image/jpeg');header('Content-Length: '.$file['size']);readfile($file['path']);return;}
        }else{
            if(\App\Core\Api::bearer()===null)Http::sameOrigin();
            if(in_array($path,['/api/v2/home/login/request','/api/v2/home/login/preview','/api/v2/home/login/confirm','/api/v2/home/invites/preview'],true)){
                $d=Http::input(3000);Auth::rate('home-auth|'.\App\Core\Api::clientIp(),120,3600);
                if($path==='/api/v2/home/login/request'){if(!is_string($d['email']??null))throw new Problem(422,'email','Completează adresa de email.');Http::json(HomeIdentity::request($d['email']));}
                if(!is_string($d['token']??null))throw new Problem(422,'home_token','Legătură incorectă.');
                if($path==='/api/v2/home/invites/preview')Http::json(HomeHouseholds::preview($d['token']));
                if($path==='/api/v2/home/login/preview')Http::json(HomeIdentity::preview($d['token']));
                $name=$d['device_name']??'Dispozitivul meu';if(!is_string($name))throw new Problem(422,'home_device','Denumire incorectă.');$r=HomeIdentity::confirm($d['token'],$name);HomeIdentity::cookie($r['secret']);unset($r['secret']);
                \App\Core\Session::start();$pending=$_SESSION['home_oauth_return']??null;unset($_SESSION['home_oauth_return']);
                if(is_string($pending)){try{\App\Domain\OAuth::pending($pending);$r['authorization_url']='/api/v2/home/oauth/authorize?request='.$pending;}catch(Problem){}}
                Http::json(['connected'=>true]+$r);
            }
            $p=HomeIdentity::principal();HomeIdentity::csrf($p);Auth::rate('home-write|'.$p['device_id'],120);
            if(isset($p['grant_id'])){
                if(in_array($path,['/api/v2/home/join','/api/v2/home/switch'],true))throw new Problem(403,'household_grant','Această autorizare este limitată la familia aleasă. Autorizează separat altă familie.');
                if(in_array($path,['/api/v2/home/invites','/api/v2/home/rename'],true)||str_ends_with($path,'/remove'))if(!in_array('home:manage',$p['scopes'],true))throw new Problem(403,'scope','Aplicația nu poate administra familia.');
                if(preg_match('~\A/api/v2/home/devices/([a-f0-9]{32})/revoke\z~D',$path,$deviceMatch)&&$deviceMatch[1]!==$p['device_id'])throw new Problem(403,'device_grant','Aplicația poate revoca numai propriul dispozitiv.');
            }
            if(preg_match('~\A/api/v2/home/photos/([a-f0-9]{32})\z~D',$path,$m)){
                if((int)($_SERVER['CONTENT_LENGTH']??0)>1000000)throw new Problem(413,'home_photo','Fotografia este prea mare.');$bytes=file_get_contents('php://input',false,null,0,1000001);Http::json(HomePhotos::put($p,$m[1],$bytes));
            }
            $d=Http::input(1500000);
            if($path==='/api/v2/home/account/action/request'){if(!is_string($d['action']??null)||!is_array($d['payload']??[]))throw new Problem(422,'account_action','Operațiune incorectă.');Http::json(\App\Domain\HomePrivacy::request($p,$d['action'],$d['payload']??[]));}
            if(in_array($path,['/api/v2/home/account/action/preview','/api/v2/home/account/action/confirm'],true)){if(!is_string($d['token']??null))throw new Problem(422,'confirmation','Legătură incorectă.');Http::json($path==='/api/v2/home/account/action/preview'?\App\Domain\HomePrivacy::preview($p,$d['token']):\App\Domain\HomePrivacy::confirm($p,$d['token']));}
            if(preg_match('~\A/api/v2/home/grants/([a-f0-9]{32})/revoke\z~D',$path,$grantMatch)){
                \App\Core\Api::installed();if(isset($p['grant_id']))throw new Problem(403,'account_session','Administrarea autorizărilor necesită sesiunea personală.');
                if(!\App\Core\Db::one('SELECT id FROM api_oauth_grants WHERE id=? AND home_account_id=?',[$grantMatch[1],$p['account_id']]))throw new Problem(404,'not_found','Autorizarea nu a fost găsită.');
                \App\Core\Db::atomic(fn()=>\App\Domain\OAuth::revokeGrant($grantMatch[1]));Http::json(['revoked'=>true]);
            }
            if($path==='/api/v2/home/sync'){$r=HomeSync::push($p,$d);Http::json($r,$r['conflicts']?409:200);}
            if($path==='/api/v2/home/invites'){Auth::rate('home-invite|'.$p['account_id'],10,86400);Http::json(HomeHouseholds::invite($p),201);}
            if($path==='/api/v2/home/join'){if(!is_string($d['token']??null))throw new Problem(422,'home_invite','Invitație incorectă.');Http::json(HomeHouseholds::join($p,$d['token']));}
            if($path==='/api/v2/home/logout')Http::json(HomeIdentity::revoke($p,$p['device_id']));
            if($path==='/api/v2/home/switch'){if(!is_string($d['household_id']??null))throw new Problem(422,'home_household','Familie incorectă.');Http::json(HomeHouseholds::switch($p,$d['household_id']));}
            if($path==='/api/v2/home/rename'){if(!is_string($d['name']??null))throw new Problem(422,'home_household','Denumire incorectă.');Http::json(HomeHouseholds::rename($p,$d['name']));}
            if(preg_match('~\A/api/v2/home/devices/([a-f0-9]{32})/revoke\z~D',$path,$m))Http::json(HomeIdentity::revoke($p,$m[1]));
            if(preg_match('~\A/api/v2/home/members/([a-f0-9]{32})/remove\z~D',$path,$m))Http::json(HomeHouseholds::remove($p,$m[1]));
        }
        throw new Problem(404,'route','Adresa nu a fost găsită.');
    }
}
