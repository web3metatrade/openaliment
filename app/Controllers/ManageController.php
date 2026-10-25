<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Db,Http,Problem,Session};
use App\Domain\{Accounts,Catalog,Documents,Mutations,Portability};
final class ManageController {
    public static function handle(string $method,string $path): void {
        if($path==='/manage/security'){AccountSecurityController::handle($method);return;}
        if($path==='/manage/developers'){DeveloperController::handle($method);return;}
        if(preg_match('~\A/manage/connectors(?:/([a-f0-9]{32}))?\z~D',$path,$connectorMatch)){IntegrationManageController::handle($method,$connectorMatch[1]??null);return;}
        if(preg_match('~^/manage/resolver/([a-f0-9]{32})$~',$path,$resolverMatch)){ResolverManageController::handle($method,$resolverMatch[1]);return;}
        $p=Auth::browser();$org=Db::one('SELECT display_name FROM organizations WHERE id=?',[$p['tenant']]);$data=['p'=>$p,'organization'=>$org['display_name'],'title'=>'Spațiul producătorului','section'=>'overview'];
        if($method==='POST'){
            Http::sameOrigin();Session::verify();$d=Http::input();$action=$d['_action']??'';$id=(string)($d['_id']??'');$back='/manage';
            foreach(['rights_confirmed','license_confirmed','public_projection_confirmed','public']as$k)if(isset($d[$k]))$d[$k]=$d[$k]==='1';
            if(isset($d['snapshot_json'])){try{$d['snapshot']=json_decode($d['snapshot_json'],true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(422,'json','JSON incorect.');}}
            if($action==='label.create'&&isset($d['food_structured_present']))$d['food_data']=\App\Domain\FoodData::fromForm($d);
            if($action==='document.upload'){Documents::upload($p,$id,$_FILES['document']??[],$d);$back='/manage/products/'.$id;}
            elseif($action==='document.scan'){Auth::approve($p);$document=Catalog::owned('documents',$id,$p);$scan=\App\Domain\DocumentScanner::scan($document);Session::flash(match($scan['state']){'clean'=>'Documentul a trecut verificarea de securitate. Poți aproba publicarea.','rejected'=>'Documentul nu a trecut verificarea de securitate.','pending'=>'Documentul așteaptă configurarea scanerului.',default=>'Verificarea nu a putut fi finalizată. Documentul rămâne privat.'});Http::redirect('/manage/products/'.$document['product_id']);}
            elseif($action==='token.create'){$result=\App\Domain\ApiKeys::create($p,$d);$_SESSION['new_token']=$result['token'];$back='/manage/api';}
            elseif($action==='token.rotate'){$result=\App\Domain\ApiKeys::rotate($p,$id,$d);$_SESSION['new_token']=$result['token'];$back='/manage/api';}
            elseif($action==='token.revoke'){\App\Domain\ApiKeys::revoke($p,$id);$back='/manage/api';}
            elseif($action==='pilot.inspect'||$action==='pilot.import'){$_SESSION['import_report']=Portability::pilot($p,$action==='pilot.inspect');$back='/manage/import';}
            elseif($action==='transfer.inspect'||$action==='transfer.import'){
                $file=$_FILES['package']??[];if(($file['error']??1)!==0||$file['size']>24000000||!is_uploaded_file($file['tmp_name']))throw new Problem(422,'file','Selectează un export JSON sau NDJSON de maximum 24 MB.');
                try{$package=json_decode(file_get_contents($file['tmp_name']),true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){$stream=fopen($file['tmp_name'],'rb');try{$package=\App\Domain\TransferStream::read($stream);}finally{if(is_resource($stream))fclose($stream);}}
                $_SESSION['import_report']=Portability::import($p,$package,$action==='transfer.inspect');$back='/manage/import';
            }
            else{
                $result=Mutations::run($p,$action,$id,$d);
                $back=match($action){'product.create'=>'/manage/products/'.$result['id'],'trade.create'=>'/manage/items/'.$result['id'],'label.create'=>'/manage/labels/'.$result['id'],'batch.create'=>'/manage/batches/'.$result['id'],'label.publish'=>'/manage/items/'.Catalog::owned('product_info_versions',$id,$p)['trade_item_id'],'batch.publish','batch.release','alert.create'=>'/manage/batches/'.$id,'alert.resolve'=>'/manage/batches/'.Catalog::owned('alerts',$id,$p)['batch_id'],'recipe.create','proposal.create','document.publish'=>'/manage','feedback.moderate'=>'/manage/feedback',default=>'/manage'};
            }
            Session::flash('Informațiile au fost salvate.');Http::redirect($back);
        }
        if($path==='/manage'){$data['products']=Db::all('SELECT * FROM products WHERE tenant_id=? ORDER BY created_at DESC LIMIT 100',[$p['tenant']]);$data['counts']=Db::one('SELECT (SELECT COUNT(*) FROM trade_items WHERE tenant_id=?) AS items,(SELECT COUNT(*) FROM batches WHERE tenant_id=?) AS batches,(SELECT COUNT(*) FROM feedback WHERE tenant_id=? AND status=\'pending\') AS feedback',[$p['tenant'],$p['tenant'],$p['tenant']]);}
        elseif(preg_match('~^/manage/products/([a-f0-9]{32})$~',$path,$m)){
            $data['section']='product';$data['product']=Catalog::owned('products',$m[1],$p);$data['items']=Db::all('SELECT * FROM trade_items WHERE tenant_id=? AND product_id=? ORDER BY created_at',[$p['tenant'],$m[1]]);$data['documents']=Db::all('SELECT id,tenant_id,title,visibility,rights_status,license_id,sha256 FROM documents WHERE tenant_id=? AND product_id=? ORDER BY created_at',[$p['tenant'],$m[1]]);$data['recipes']=Db::all('SELECT id,version_key,visibility,technical_status FROM recipe_versions WHERE tenant_id=? AND product_id=? ORDER BY created_at',[$p['tenant'],$m[1]]);
        }
        elseif(preg_match('~^/manage/items/([a-f0-9]{32})$~',$path,$m)){
            $data['section']='item';$data['item']=Catalog::owned('trade_items',$m[1],$p);$data['product']=Catalog::owned('products',$data['item']['product_id'],$p);
            $data['labels']=Db::all('SELECT id,version_key,state,approved_at FROM product_info_versions WHERE tenant_id=? AND trade_item_id=? ORDER BY created_at DESC',[$p['tenant'],$m[1]]);$data['batches']=Db::all('SELECT id,lot_code,visibility,state FROM batches WHERE tenant_id=? AND trade_item_id=? ORDER BY created_at DESC LIMIT 100',[$p['tenant'],$m[1]]);
            $data['documents']=Db::all("SELECT id,title FROM documents WHERE tenant_id=? AND product_id=? AND visibility='public' AND rights_status='cleared'",[$p['tenant'],$data['item']['product_id']]);
            $data['recipes']=Db::all('SELECT id,version_key FROM recipe_versions WHERE tenant_id=? AND product_id=?',[$p['tenant'],$data['item']['product_id']]);
        }
        elseif(preg_match('~^/manage/labels/([a-f0-9]{32})$~',$path,$m)){$data['section']='label';$data['label']=Catalog::owned('product_info_versions',$m[1],$p);$data['snapshot']=json_decode($data['label']['snapshot_json'],true);}
        elseif(preg_match('~^/manage/batches/([a-f0-9]{32})$~',$path,$m)){$data['section']='batch';$data['batch']=Catalog::owned('batches',$m[1],$p);$data['snapshot']=json_decode($data['batch']['snapshot_json'],true);$data['alerts']=Db::all('SELECT * FROM alerts WHERE tenant_id=? AND batch_id=? ORDER BY created_at DESC',[$p['tenant'],$m[1]]);$data['events']=Db::all('SELECT event_type,payload_json,created_at FROM batch_events WHERE tenant_id=? AND batch_id=? ORDER BY created_at DESC',[$p['tenant'],$m[1]]);}
        elseif(preg_match('~^/manage/qr/(item|batch)/([a-f0-9]{32})$~',$path,$m)){$data['section']='qr';$data['qr']=Catalog::qr($p,$m[1],$m[2]);}
        elseif($path==='/manage/api'){$data['section']='api';$data['tokens']=Db::all('SELECT id,name,scopes,expires_at,revoked_at FROM api_tokens WHERE tenant_id=? ORDER BY created_at DESC',[$p['tenant']]);$data['newToken']=$_SESSION['new_token']??null;unset($_SESSION['new_token']);}
        elseif($path==='/manage/import'){$data['section']='import';$data['report']=$_SESSION['import_report']??null;unset($_SESSION['import_report']);}
        elseif($path==='/manage/feedback'){$data['section']='feedback';$data['feedback']=Db::all('SELECT f.*,j.state AS ai_state,j.result_json FROM feedback f LEFT JOIN ai_jobs j ON j.feedback_id=f.id AND j.tenant_id=f.tenant_id WHERE f.tenant_id=? ORDER BY f.created_at DESC LIMIT 100',[$p['tenant']]);}
        elseif($path==='/manage/export'){if(($_GET['format']??'')==='ndjson')Portability::stream($p);header('Content-Disposition: attachment; filename="openaliment-transfer.json"');Http::json(Portability::export($p));}
        else throw new Problem(404,'not_found','Pagina nu a fost găsită.');
        Http::view('manage',$data);
    }
}
