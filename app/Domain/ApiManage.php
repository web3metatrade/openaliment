<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Api,Config,Db,Migrations,Problem};

final class ApiManage {
    public const TABLES=['products'=>'products','trade-items'=>'trade_items','labels'=>'product_info_versions','batches'=>'batches','recipes'=>'recipe_versions','documents'=>'documents','feedback'=>'feedback','proposals'=>'change_proposals','imports'=>'imports','audit-events'=>'audit_events','ai-jobs'=>'ai_jobs'];
    public static function list(array $p,string $resource,array $filters=[]): array {
        $table=self::TABLES[$resource]??throw new Problem(404,'not_found','Colecție necunoscută.');[$limit,$cursor]=Api::page();$where=['tenant_id=?','id>?'];$params=[$p['tenant'],$cursor];
        $allowed=match($resource){'products'=>['category'],'trade-items'=>['product_id','gtin14'],'labels'=>['product_id','trade_item_id','state'],'batches'=>['product_id','trade_item_id','lot_code','state'],'recipes','documents','proposals'=>['product_id'],'feedback'=>['product_id','batch_id','status','kind'],'ai-jobs'=>['feedback_id','state'],'audit-events'=>['object_id','action'],default=>[]};
        foreach($allowed as$key)if(isset($filters[$key])||isset($_GET[$key])){$value=$filters[$key]??$_GET[$key];if(!is_string($value)||strlen($value)>190)throw new Problem(422,'filter','Filtru incorect.');if(str_ends_with($key,'_id'))Api::id($value);$where[]=$key.'=?';$params[]=$value;}
        $columns=match($resource){
            'imports'=>'id,source_key,package_sha256,result_json,created_at',
            'ai-jobs'=>'id,feedback_id,job_type,output_audience,state,attempts,available_at,source_sha256,error_code,created_at',
            'documents'=>'id,product_id,title,sha256,mime,size_bytes,visibility,rights_status,license_id,provenance_json,created_at',
            default=>'*'};
        return Api::collection(Db::all('SELECT '.$columns.' FROM '.$table.' WHERE '.implode(' AND ',$where).' ORDER BY id LIMIT '.($limit+1),$params),$limit,ApiPublic::decode(...));
    }
    public static function get(array $p,string $resource,string $id): array {
        $table=self::TABLES[$resource]??throw new Problem(404,'not_found','Resursă necunoscută.');
        // This fixed repository map also includes imports, audit and AI jobs.
        $r=Db::one('SELECT * FROM '.$table.' WHERE id=? AND tenant_id=?',[$id,$p['tenant']])??throw new Problem(404,'not_found','Înregistrarea nu a fost găsită.');
        if($resource==='imports')unset($r['payload_json']);
        if($resource==='ai-jobs')unset($r['lease_token']);return ApiPublic::decode($r);
    }
    public static function children(array $p,string $path,string $id): array {
        $parts=explode('/',$path);$parent=$parts[4];$child=$parts[6];
        $parentTable=['products'=>'products','trade-items'=>'trade_items','batches'=>'batches','feedback'=>'feedback','proposals'=>'change_proposals'][$parent];Catalog::owned($parentTable,$id,$p);
        if(in_array($child,['trade-items','documents','recipes','proposals','labels','batches'],true))return self::list($p,$child,[match($parent){'products'=>'product_id','trade-items'=>'trade_item_id',default=>'batch_id'}=>$id]);
        [$limit,$cursor]=Api::page();
        if($child==='resolver-links'){
            if(!Migrations::resolver())throw new Problem(503,'resolver_setup','Administrarea destinațiilor resolverului nu este activă. Contactează administratorul platformei.');
            $rows=Db::all('SELECT * FROM resolver_links WHERE tenant_id=? AND trade_item_id=? AND id>? ORDER BY id LIMIT '.($limit+1),[$p['tenant'],$id,$cursor]);
        }elseif($child==='events'||$child==='alerts'){
            $table=$child==='events'?'batch_events':'alerts';$rows=Db::all('SELECT * FROM '.$table.' WHERE tenant_id=? AND batch_id=? AND id>? ORDER BY id LIMIT '.($limit+1),[$p['tenant'],$id,$cursor]);
        }elseif($child==='ai-results')$rows=Db::all('SELECT id,job_type,state,result_json,source_sha256,error_code,created_at FROM ai_jobs WHERE tenant_id=? AND feedback_id=? AND id>? ORDER BY id LIMIT '.($limit+1),[$p['tenant'],$id,$cursor]);
        else $rows=Db::all('SELECT id,action,actor_id,details_json,created_at FROM audit_events WHERE tenant_id=? AND object_id=? AND id>? ORDER BY id LIMIT '.($limit+1),[$p['tenant'],$id,$cursor]);
        return Api::collection($rows,$limit,ApiPublic::decode(...));
    }
    public static function dashboard(array $p): array {
        $counts=[];foreach(['products'=>'products','trade_items'=>'trade_items','batches'=>'batches','pending_feedback'=>'feedback']as$key=>$table)$counts[$key]=(int)Db::one('SELECT COUNT(*) AS n FROM '.$table.' WHERE tenant_id=?'.($key==='pending_feedback'?" AND status='pending'":''),[$p['tenant']])['n'];return ['counts'=>$counts];
    }
    public static function connector(array $p,string $path,string $id): array {
        $c=Db::one('SELECT id,name,kind,created_at FROM integration_connectors WHERE tenant_id=? AND id=?',[$p['tenant'],$id]);if(!$c)throw new Problem(404,'not_found','Conectorul nu a fost găsit.');
        if(str_ends_with($path,'/'.$id))return $c;
        [$limit,$cursor]=Api::page();
        if(str_ends_with($path,'/bindings')){
            $external=$_GET['external_id']??'';if(!is_string($external)||strlen($external)>120)throw new Problem(422,'filter','Identificator extern incorect.');
            $rows=Db::all('SELECT external_id,product_id,trade_item_id FROM integration_bindings WHERE tenant_id=? AND connector_id=? AND external_id>? ORDER BY external_id LIMIT '.($limit+1),[$p['tenant'],$id,$external]);
            $more=count($rows)>$limit;$rows=array_slice($rows,0,$limit);return ['items'=>$rows,'next_external_id'=>$more?end($rows)['external_id']:null,'has_more'=>$more,'limit'=>$limit];
        }
        $rows=Db::all('SELECT id,mapping_id,event_key,request_sha256,result_json,created_at FROM integration_events WHERE tenant_id=? AND connector_id=? AND id>? ORDER BY id LIMIT '.($limit+1),[$p['tenant'],$id,$cursor]);
        return Api::collection($rows,$limit,static function($r){$r=ApiPublic::decode($r);unset($r['result']['receipt_url']);return $r;});
    }
    public static function ai(array $p,string $method,array $d): array {
        if($method==='GET')return ['enabled'=>(bool)(Config::get('ai')['enabled']??false),'job_types'=>['feedback_triage'],'qr_depends_on_ai'=>false,'public_context_only'=>true];
        \App\Core\Auth::approve($p);$id=Api::id(Catalog::text($d,'feedback_id',32));
        if(($d['kind']??'feedback_triage')!=='feedback_triage')throw new Problem(422,'ai_job_type','Acest tip de job nu este disponibil.');
        if(!(Config::get('ai')['enabled']??false))throw new Problem(503,'ai_disabled','Analiza AI este dezactivată.');Ai::context($p['tenant'],$id);
        $old=Db::one("SELECT id,state FROM ai_jobs WHERE tenant_id=? AND feedback_id=? AND job_type='feedback_triage'",[$p['tenant'],$id]);if($old)return $old;
        $job=Db::id();Db::insert('ai_jobs',['id'=>$job,'tenant_id'=>$p['tenant'],'feedback_id'=>$id,'available_at'=>Db::now(),'created_at'=>Db::now()]);return ['id'=>$job,'state'=>'pending'];
    }
}
