<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Http,Migrations,Problem,Session};
use App\Domain\Integrations;

final class IntegrationManageController {
    public static function handle(string $method,?string $id): void {
        $p=Auth::browser();if(!Migrations::integrations())throw new Problem(503,'integration_setup','Conectoarele nu sunt active pe această platformă. Contactează administratorul.');$report=null;$submitted=[];
        if($method==='POST'){
            Http::sameOrigin();Session::verify();Auth::write($p);$data=Http::input(1100000);$action=$data['action']??'';
            if($action==='create'&&$id===null){$r=Integrations::create($p,$data);Http::redirect('/manage/connectors/'.$r['id']);}
            if($id===null)throw new Problem(422,'connector','Selectează conectorul.');
            $field=$action==='mapping'?'mapping_json':'payload_json';try{$json=json_decode((string)($data[$field]??''),true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(422,'json','Document JSON incorect.');}
            if(!is_array($json))throw new Problem(422,'json','Este necesar un obiect JSON.');
            if($action==='mapping'){Integrations::mapping($p,$id,['version_key'=>$data['version_key']??null,'mapping'=>$json]);Session::flash('Versiunea mapării a fost păstrată.');Http::redirect('/manage/connectors/'.$id);}
            $submitted=$data;$event=['mapping_id'=>$data['mapping_id']??null,'operation'=>$data['operation']??null,'event_key'=>$data['event_key']??null,'external_id'=>$data['external_id']??null,'payload'=>$json];
            $report=match($action){'preview'=>Integrations::preview($p,$id,$event),'ingest'=>Integrations::ingest($p,$id,$event),default=>throw new Problem(422,'action','Operațiune incorectă.')};
        }elseif(!in_array($method,['GET','HEAD'],true))throw new Problem(405,'method','Metodă nepermisă.');
        $connectors=Integrations::list($p);$connector=null;foreach($connectors as$c)if($c['id']===$id)$connector=$c;
        if($id!==null&&!$connector)throw new Problem(404,'connector','Conectorul nu a fost găsit.');
        Http::view('connectors',['title'=>'Conectoare','p'=>$p,'connectors'=>$connectors,'connector'=>$connector,'mappings'=>$id?Integrations::mappings($p,$id):[],'report'=>$report,'submitted'=>$submitted]);
    }
}
