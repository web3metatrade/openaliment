<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Api, ApiInput, Auth, Db, Http, Migrations, Problem, Session};
use App\Domain\{Catalog, ExternalCatalog, ExternalDatasets, ExternalJobs, ExternalMedia, ExternalSharing, ExternalSources, Mutations};

final class ExternalSourcesController
{
    public static function matches(string $path): bool
    {
        return in_array($path,['/surse/open-food-facts','/admin/sources','/manage/sources','/manage/sources/openfoodfacts/sharing'],true)
            ||preg_match('~\A/manage/items/[a-f0-9]{32}/sources\z~D',$path)===1;
    }

    public static function web(string $method, string $path): never
    {
        Api::method($method,['GET','HEAD','POST']);
        if($path==='/surse/open-food-facts'){
            Api::method($method,['GET','HEAD']);
            Http::view('external-source',['title'=>'Open Food Facts și proveniența datelor','active'=>ExternalCatalog::available(),'licenses'=>\App\Domain\OpenFoodFacts::licenses()]);exit;
        }
        if($path==='/admin/sources'){
            $user=Auth::admin();
            if($method==='POST'){
                Http::sameOrigin();Session::verify();$data=$_POST;
                Api::limit('private-write',1,$user['id']);
                foreach(['license_policy_confirmed','api_usage_registered_confirmed']as$key)if(isset($data[$key]))$data[$key]=$data[$key]==='1';
                $action=$data['action']??'';
                if($action==='dataset'||$action==='datasetPreview'){
                    $file=$_FILES['dataset']??[];
                    if(($file['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||($file['size']??0)>8388608)throw new Problem(422,'external_dataset','Selectează un pachet NDJSON de maximum 8 MiB.');
                    try{$manifest=json_decode((string)($data['manifest_json']??''),true,30,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(422,'external_manifest','Manifest JSON incorect.');}
                    $data=['manifest'=>$manifest,'content_base64'=>base64_encode(file_get_contents($file['tmp_name']))];
                }
                $result=self::admin($action,(string)($_POST['resource_id']??''),$data,$user);
                Session::flash(match($action){'state'=>'Starea sursei a fost actualizată.','datasetExport'=>'Exportul a fost programat.','worker'=>'Procesarea a fost executată.','datasetPreview'=>'Pachetul a trecut verificarea.','dataset'=>'Importul a fost programat.',default=>'Operațiunea a fost înregistrată.'});Http::redirect($path);
            }
            Http::view('external-sources-admin',['title'=>'Surse externe','sourceStatus'=>self::admin('status','',[],$user)]);exit;
        }
        $p=Auth::browser();ExternalCatalog::required();$itemId=null;$report=null;
        if(preg_match('~\A/manage/items/([a-f0-9]{32})/sources\z~D',$path,$match))$itemId=$match[1];
        if($method==='POST'){
            Http::sameOrigin();Session::verify();$data=$_POST;$action=$data['action']??'';
            foreach(['public_data_confirmed','licenses_confirmed','independently_verified']as$key)if(isset($data[$key]))$data[$key]=$data[$key]==='1';
            if(isset($data['expected_version']))$data['expected_version']=(int)$data['expected_version'];
            if(($data['expected_label_id']??null)==='')$data['expected_label_id']=null;
            if(in_array($action,['connect','disconnect','grant','revoke'],true))\App\Domain\ApiKeys::recent($p,$data);
            if($action==='lookup'){
                $item=ExternalSources::item($p,(string)$itemId);Auth::write($p);$report=ExternalJobs::lookup((string)$item['gtin14']);
            }elseif($action==='preview')$report=ExternalSources::preview($p,(string)$itemId,$data);
            elseif($action==='draft'){
                $result=ExternalSources::draft($p,(string)$itemId,$data);Session::flash('Ciorna a fost creată. Verifică informațiile înainte de publicare.');Http::redirect('/manage/labels/'.$result['id']);
            }elseif($action==='decision')$report=ExternalSources::decide($p,(string)($data['resource_id']??''),$data);
            elseif($action==='disconnectBinding')$report=ExternalSources::disconnect($p,(string)$itemId,(string)($data['resource_id']??''));
            elseif($action==='connect')$report=ExternalSharing::connect($p,$data);
            elseif($action==='disconnect')$report=ExternalSharing::disconnect($p);
            elseif($action==='grant')$report=ExternalSharing::grant($p,$data);
            elseif($action==='revoke')$report=ExternalSharing::revoke($p,(string)($data['resource_id']??''));
            elseif($action==='publication')$report=ExternalSharing::publication($p,(string)($data['trade_item_id']??''),$data);
            else throw new Problem(422,'external_action','Operațiune incorectă.');
        }
        if($path==='/manage/sources/openfoodfacts/sharing'){
            if($p['role']!=='owner'&&!in_array($p['role'],['quality'],true))throw new Problem(403,'role','Accesul necesită un responsabil autorizat.');
            Http::view('external-sharing',['title'=>'Distribuire Open Food Facts','p'=>$p,'report'=>$report,'connection'=>ExternalSharing::connection($p),'grants'=>ExternalSharing::grants($p)['items'],'publications'=>ExternalSharing::publications($p)['items'],'items'=>Db::all("SELECT id,name FROM trade_items WHERE tenant_id=? AND gtin_status='verified' ORDER BY name LIMIT 200",[$p['tenant']])]);exit;
        }
        $item=$itemId?ExternalSources::item($p,$itemId):null;
        $record=$item&&$item['gtin14']?ExternalCatalog::cached($item['gtin14']):null;
        Http::view('external-sources-manage',['title'=>'Informații din surse externe','p'=>$p,'item'=>$item,'record'=>$record,'report'=>$report,'bindings'=>ExternalSources::list($p,$itemId)['items'],'conflicts'=>ExternalSources::conflicts($p)['items']]);exit;
    }

    public static function api(string $method, array $route): never
    {
        $handler=substr($route['handler'],9);$id=$route['resource_id'];
        $data=$method==='POST'?Http::input(str_starts_with($handler,'dataset')?12000000:40000,['application/json']):[];
        if($method==='POST')ApiInput::validate($data,$route['body']);
        if($route['auth']==='public'){
            header('Cache-Control: no-store');
            if($handler==='asset'){Api::limit('download');ExternalMedia::serve($id);}
            if($handler==='export'){Api::limit('download');ExternalDatasets::download();}
            if($handler==='resolve')Http::json(ExternalCatalog::resolve(Catalog::text($data,'code',4096)));
            if($handler==='sources')Http::json(ExternalCatalog::sources());
            if($handler==='revisionIndex')Http::json(ExternalCatalog::revisionIndex());
            if($handler==='revision')Http::json(ExternalCatalog::revision($id));
            if($handler==='lookupStatus')Http::json(ExternalJobs::status($id));
            if($handler==='lookup'){
                Api::limit('tools');$result=ExternalJobs::lookup(Catalog::text($data,'gtin',14));
                $pending=in_array($result['state'],['queued','retry','running'],true);
                if($pending)header('Retry-After: '.($result['retry_after']??30));Http::json($result,$pending?202:200);
            }
        }
        if($route['auth']==='admin-session'){
            $user=Auth::admin();Api::limit($method==='POST'?'private-write':'private-read',1,$user['id']);
            if($method==='POST'){Http::sameOrigin();Session::verify();}
            Http::json(self::admin($handler,$id,$data,$user));
        }
        $p=Auth::principal($route['scope']);Api::limit('private-read',1,$p['token']??$p['user']);
        if($handler==='package')ExternalSharing::package($p,$id);
        $read=match($handler){
            'bindings'=>fn()=>ExternalSources::list($p,$id),'preview'=>fn()=>ExternalSources::preview($p,$id,$data),
            'conflicts'=>fn()=>ExternalSources::conflicts($p),'connection'=>fn()=>ExternalSharing::connection($p),
            'grants'=>fn()=>ExternalSharing::grants($p),'publicationPreview'=>fn()=>ExternalSharing::preview($p,$id,$data),
            'publications'=>fn()=>ExternalSharing::publications($p),default=>null,
        };
        if($read){if($method==='POST')Api::write($p);Http::json($read());}
        $write=match($handler){
            'bind'=>fn()=>ExternalSources::bind($p,$id,$data),'disconnectBinding'=>fn()=>ExternalSources::disconnect($p,$id,$route['parameters']['binding']),
            'draft'=>fn()=>ExternalSources::draft($p,$id,$data),'decision'=>fn()=>ExternalSources::decide($p,$id,$data),
            'connect'=>fn()=>ExternalSharing::connect($p,$data),'disconnect'=>fn()=>ExternalSharing::disconnect($p),
            'grant'=>fn()=>ExternalSharing::grant($p,$data),'revoke'=>fn()=>ExternalSharing::revoke($p,$id),
            'publication'=>fn()=>ExternalSharing::publication($p,$id,$data),default=>throw new Problem(404,'external_route','Operațiunea nu a fost găsită.'),
        };
        if($p['token']===null&&in_array($handler,['connect','disconnect','grant','revoke'],true))\App\Domain\ApiKeys::recent($p,$data);
        Api::mutation($p,$data,$write);
    }

    private static function admin(string $handler, string $id, array $data, array $user): array
    {
        if($handler==='status')return ExternalCatalog::status()+['datasets'=>Migrations::external()?Db::all('SELECT id,kind,state,record_count,accepted_count,rejected_count,started_at,finished_at FROM external_dataset_runs ORDER BY started_at DESC,id DESC LIMIT 30'):[]];
        ExternalCatalog::required();
        if($handler==='state')return ExternalCatalog::state($user,$data);
        if($handler==='visibility')return ExternalCatalog::visibility($id,$data,$user);
        if($handler==='retry')return ExternalJobs::retry($id);
        if($handler==='datasetPreview')return ExternalDatasets::prepare($data,true);
        if($handler==='dataset')return ExternalDatasets::prepare($data,false);
        if($handler==='datasetRetry')return ExternalDatasets::retry($id);
        if($handler==='datasetExport')return ExternalDatasets::export();
        if($handler==='worker'){
            $jobs=ExternalJobs::run(10);
            foreach(Db::all("SELECT id FROM external_dataset_runs WHERE state IN ('queued','running') ORDER BY started_at LIMIT 1")as$run)$jobs['dataset']=ExternalDatasets::step($run['id']);
            return $jobs;
        }
        if($handler==='verify'){
            if(!Migrations::externalSharing())throw new Problem(503,'external_sharing_setup','Distribuirea nu este instalată.');
            $connection=Db::one('SELECT id,transport FROM external_connections WHERE id=?',[$id]);
            if(!$connection)throw new Problem(404,'external_connection','Conexiunea nu a fost găsită.');
            ExternalCatalog::adminAudit($user,'external.package_verified',$id,['scope'=>'producer_package_format_only']);
            return ['id'=>$id,'verification_scope'=>'producer_package_format_only','remote_organization_verified'=>false,'remote_write_available'=>false];
        }
        throw new Problem(404,'external_route','Operațiunea nu a fost găsită.');
    }
}
