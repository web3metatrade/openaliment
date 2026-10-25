<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Config,Db,Http,Problem,Seo,Session};
use App\Domain\{Accounts,Catalog,Community,DigitalLink};
final class PublicController {
    public static function handle(string $method,string $path): void {
        if(in_array($path,['/api/consumer-openapi.json','/api/integration-openapi.json','/api/home-openapi.json'],true)||preg_match('~\A/api/schemas/(food-data|food-date|consumer-product-profile|consumer-inventory|consumer-transfer|partner-mapping|digital-receipt|home-sync)\.schema\.json\z~D',$path)){
            header('Access-Control-Allow-Origin: *');header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
            header('Allow: GET, HEAD, OPTIONS');
            if($method==='OPTIONS'){header('Allow: GET, HEAD, OPTIONS');http_response_code(204);return;}
            if(!in_array($method,['GET','HEAD'],true))throw new Problem(405,'method','Metodă nepermisă.');
        }
        if($path===\App\Domain\DemoBread::PAGE||str_starts_with($path,\App\Domain\DemoBread::PAGE.'/')){DemoBreadController::handle($method,$path);return;}
        if($method==='POST'){
            Http::sameOrigin();
            if($path==='/feedback'){$d=$_POST;$d['consent']=isset($d['consent']);$result=Community::feedback($d);Http::view('message',['title'=>'Feedback înregistrat','message'=>'Mulțumim. Observația ta a fost trimisă producătorului pentru revizuire.']);return;}
            Session::verify();
            if($path==='/login'){$return=$_SESSION['oauth_return']??$_SESSION['application_return']??'/manage';Accounts::login($_POST);$next=is_string($return)&&preg_match('~\A/(?:oauth/authorize\?request=[a-f0-9]{32}|developers/marketplace(?:/[a-f0-9]{32})?|manage/printing)\z~D',$return)?$return:'/manage';$u=Auth::user();if($u&&\App\Domain\Passkeys::required($u)){$_SESSION['mfa_return']=$next;Http::redirect('/account/verify');}Http::redirect($next);}
            if($path==='/register'){Auth::rate('register|'.\App\Core\Api::clientIp(),5,3600);$r=Accounts::register($_POST);Session::login($r['id']);Http::redirect('/manage');}
            if($path==='/logout'){Session::start();\App\Domain\ProducerSessions::endCurrent();$_SESSION=[];session_destroy();setcookie('oa_session','',['expires'=>time()-3600,'path'=>'/','secure'=>Config::get('session_secure'),'httponly'=>true,'samesite'=>'Lax']);Http::redirect('/');}
            if(preg_match('~^/proposals/([a-f0-9]{32})/vote$~',$path,$m)){$expected=$_POST['expected_mutation_version']??null;if($expected!==null&&(!is_string($expected)||!ctype_digit($expected)||strlen($expected)>10))throw new Problem(422,'vote_version','Versiunea votului este incorectă.');Community::vote($m[1],($_POST['vote']??'')==='1',$expected===null?null:(int)$expected);Http::redirect('/produse');}
            throw new Problem(405,'method','Metodă nepermisă.');
        }
        if(!in_array($method,['GET','HEAD'],true))throw new Problem(405,'method','Metodă nepermisă.');
        if($path==='/robots.txt'||$path==='/sitemap.xml'){
            header('Content-Type: '.($path==='/robots.txt'?'text/plain':'application/xml').'; charset=utf-8');
            echo $path==='/robots.txt'?Seo::robots():Seo::sitemap();return;
        }
        if($path==='/api/openapi.json'){header('Content-Type: application/json; charset=utf-8');readfile(ROOT.'/contracts/openapi.json');return;}
        if($path==='/api/consumer-openapi.json'){header('Content-Type: application/json; charset=utf-8');readfile(ROOT.'/contracts/consumer-openapi.json');return;}
        if($path==='/api/home-openapi.json'){header('Content-Type: application/json; charset=utf-8');readfile(ROOT.'/contracts/home-openapi.json');return;}
        if($path==='/api/integration-openapi.json'){header('Content-Type: application/json; charset=utf-8');readfile(ROOT.'/contracts/integration-openapi.json');return;}
        if(preg_match('~\A/api/schemas/(food-data|food-date|consumer-product-profile|consumer-inventory|consumer-transfer|partner-mapping|digital-receipt|home-sync)\.schema\.json\z~D',$path,$schemaMatch)){header('Content-Type: application/schema+json; charset=utf-8');readfile(ROOT.'/contracts/'.$schemaMatch[1].'.schema.json');return;}
        if($path==='/'){Http::view('home');return;}
        if($path==='/gs1-digital-link'){Http::view('digital-link-guide');return;}
        if($path==='/login'||$path==='/register'){
            \App\Core\Api::limit('auth');
            if($existing=Auth::user())Http::redirect(\App\Domain\Passkeys::required($existing)&&empty($_SESSION['mfa_verified_at'])?'/account/verify':'/manage');
            Http::view('auth',['mode'=>$path==='/login'?'login':'register','title'=>$path==='/login'?'Intră în cont':'Înregistrează producătorul']);return;
        }
        if($path==='/produse'){
            \App\Core\Api::limit('catalog');
            $rows=Db::all("SELECT t.id,t.product_id,v.snapshot_json,o.display_name AS producer FROM trade_items t JOIN product_info_versions v ON v.id=t.current_info_id AND v.tenant_id=t.tenant_id JOIN organizations o ON o.id=t.tenant_id WHERE v.state='published' ORDER BY v.approved_at DESC LIMIT 60");
            Http::view('catalog',['title'=>'Produse','items'=>$rows]);return;
        }
        if(preg_match('~^/(p|t|b)/([a-f0-9]{32})$~',$path,$m)){\App\Core\Api::limit('public');self::product($m[1]==='b'?Catalog::publicBatch($m[2]):Catalog::publicItem($m[2],$m[1]==='p'));return;}
        if(preg_match('~^/p/([a-f0-9]{32})/v/([^/]+)$~',$path,$m)){
            \App\Core\Api::limit('public');
            $r=Db::one("SELECT version_key,snapshot_json,snapshot_sha256,license_id,technical_status FROM recipe_versions WHERE product_id=? AND version_key=? AND visibility='public' AND rights_status='cleared' AND license_id IS NOT NULL",[$m[1],rawurldecode($m[2])]);if(!$r)throw new Problem(404,'not_found','Versiunea nu a fost găsită.');Http::view('recipe',['title'=>'Documentație '.$r['version_key'],'recipe'=>$r]);return;
        }
        if($path==='/confidentialitate'&&Config::get('privacy_text')){Http::view('privacy',['title'=>'Confidențialitate']);return;}
        if($path==='/despre'){Http::view('about',['title'=>'Despre OpenAliment']);return;}
        if($path==='/api/docs'){ApiDocumentationController::handle($method,$path);return;}
        if(preg_match('~^/(?:d|manage/documents)/([a-f0-9]{32})$~',$path,$m)){
            \App\Core\Api::limit('download');
            $doc=Db::one("SELECT * FROM documents WHERE id=? AND visibility='public' AND rights_status='cleared'",[$m[1]]);
            if(!$doc){$p=Auth::principal();$doc=Catalog::owned('documents',$m[1],$p);}
            $file=Config::get('storage').'/private/'.$doc['storage_key'];$base=realpath(Config::get('storage').'/private');$real=realpath($file);if(!$base||!$real||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)||!is_file($file))throw new Problem(404,'not_found','Fișierul nu este disponibil.');
            header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="document-'.$doc['id'].'.bin"');header('Content-Length: '.filesize($file));readfile($file);return;
        }
        throw new Problem(404,'not_found','Pagina nu a fost găsită.');
    }
    private static function product(array $pub): void {
        $pub['requested_lot']=null;
        if(!isset($pub['batch'])&&is_string($_GET['requested_lot']??null)){try{DigitalLink::lot($_GET['requested_lot']);$pub['requested_lot']=$_GET['requested_lot'];}catch(Problem){}}
        $pub['date_mismatch']=false;
        if(isset($pub['batch']))foreach(['11'=>'production_date','15'=>'best_before','17'=>'use_by']as$ai=>$field)if(isset($_GET[$ai])){$expected=$pub['batch']['data'][$field]??null;if(!$expected||$_GET[$ai]!==substr(str_replace('-','',$expected),2))$pub['date_mismatch']=true;}
        $feedback=Db::all("SELECT kind,rating,public_text,created_at,batch_id,product_info_version_id FROM feedback WHERE product_id=? AND product_info_version_id=? AND status='published' ORDER BY created_at DESC LIMIT 30",[$pub['product_id'],$pub['product_info_version_id']]);
        $proposals=Db::all("SELECT p.id,p.title,p.body,p.state,p.decision,(SELECT COUNT(*) FROM votes v WHERE v.proposal_id=p.id) AS votes FROM change_proposals p WHERE p.product_id=? AND p.visibility='public' ORDER BY p.created_at DESC LIMIT 20",[$pub['product_id']]);
        if(isset($pub['batch']))$recipes=Db::all("SELECT r.version_key,r.license_id,r.technical_status FROM recipe_versions r JOIN batches b ON b.recipe_version_id=r.id AND b.tenant_id=r.tenant_id WHERE b.id=? AND r.visibility='public' AND r.rights_status='cleared' AND r.license_id IS NOT NULL",[$pub['batch']['id']]);else $recipes=Db::all("SELECT version_key,license_id,technical_status FROM recipe_versions WHERE product_id=? AND visibility='public' AND rights_status='cleared' AND license_id IS NOT NULL ORDER BY created_at",[$pub['product_id']]);
        $canVote=false;$myVotes=[];$voteVersions=[];if(isset($_COOKIE['oa_session'])){$viewer=Auth::user();$canVote=$viewer&&(bool)$viewer['email_verified']&&(!\App\Domain\Passkeys::required($viewer)||!empty($_SESSION['mfa_verified_at']));if($canVote){$myVotes=array_column(Db::all('SELECT proposal_id FROM votes WHERE user_id=?',[$viewer['id']]),'proposal_id');if(\App\Core\Migrations::readiness())$voteVersions=array_column(Db::all('SELECT proposal_id,mutation_version FROM community_vote_states WHERE user_id=?',[$viewer['id']]),'mutation_version','proposal_id');}}
        Http::view('product',['recipes'=>$recipes,'canVote'=>$canVote,'myVotes'=>$myVotes,'voteVersions'=>$voteVersions,'title'=>$pub['label']['name'],'product'=>$pub,'feedback'=>$feedback,'proposals'=>$proposals,'feedbackEnabled'=>Community::enabled()]);
    }
}
