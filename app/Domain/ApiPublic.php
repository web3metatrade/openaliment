<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Api,Config,Db,Http,Problem};

final class ApiPublic {
    public static function get(string $path,string $id): array {
        $type=explode('/',$path)[4];
        if($type==='products')return Catalog::publicItem($id,true);
        if($type==='trade-items')return Catalog::publicItem($id);
        if($type==='batches')return Catalog::publicBatch($id);
        if($type==='labels')return self::label($id);
        if($type==='documents')return self::document($id);
        if($type==='recipe-versions'){
            $r=Db::one("SELECT id,product_id,version_key,snapshot_json,snapshot_sha256,license_id,technical_status,created_at FROM recipe_versions WHERE id=? AND visibility='public' AND rights_status='cleared' AND license_id IS NOT NULL",[$id]);
        }else $r=Db::one("SELECT p.id,p.product_id,p.title,p.body,p.state,p.decision,p.created_at,(SELECT COUNT(*) FROM votes v WHERE v.proposal_id=p.id) AS votes FROM change_proposals p WHERE p.id=? AND p.visibility='public'",[$id]);
        if(!$r)throw new Problem(404,'not_found','Resursa publică nu a fost găsită.');return self::decode($r);
    }
    public static function label(string $id): array {
        $r=Db::one("SELECT id,product_id,trade_item_id,version_key,snapshot_json,snapshot_sha256,approved_at FROM product_info_versions WHERE id=? AND state='published'",[$id]);
        if(!$r)throw new Problem(404,'not_found','Versiunea publică nu a fost găsită.');return self::decode($r);
    }
    public static function document(string $id): array {
        $r=Db::one("SELECT id,product_id,title,sha256,mime,size_bytes,license_id,created_at FROM documents WHERE id=? AND visibility='public' AND rights_status='cleared'",[$id]);
        if(!$r)throw new Problem(404,'not_found','Documentul public nu a fost găsit.');return $r+['download_url'=>url('/api/v2/public/documents/'.$id.'/content')];
    }
    public static function decode(array $r): array {
        foreach(['snapshot_json'=>'snapshot','result_json'=>'result','details_json'=>'details','payload_json'=>'payload','provenance_json'=>'provenance','target_json'=>'target','qualifiers_json'=>'qualifiers','mapping_json'=>'mapping']as$column=>$field){
            if(array_key_exists($column,$r)){$r[$field]=$r[$column]===null?null:json_decode($r[$column],true,64,JSON_THROW_ON_ERROR);unset($r[$column]);}
        }
        unset($r['storage_key'],$r['contact_token_hash'],$r['request_key'],$r['request_hash'],$r['lease_token']);
        return $r;
    }
    public static function catalog(?string $product=null): array {
        Api::installed();[$limit,$cursor]=Api::page();Api::limit('catalog');$where=["v.state='published'",'t.id>?'];$params=[$cursor];
        if($product!==null){Catalog::publicItem($product,true);$where[]='t.product_id=?';$params[]=$product;}
        foreach(['category'=>'s.category','producer'=>'t.tenant_id']as$key=>$column)if(isset($_GET[$key])){$value=Catalog::text($_GET,$key,$key==='category'?80:32);if($key==='producer')Api::id($value);$where[]=$column.'=?';$params[]=$value;}
        if(isset($_GET['gtin'])){$where[]='t.verified_at IS NOT NULL AND t.gtin14=?';$params[]=DigitalLink::gtin(Catalog::text($_GET,'gtin',14));}
        if(isset($_GET['q'])){$q=Catalog::text($_GET,'q',120);$where[]="s.name LIKE ? ESCAPE '!'";$params[]=strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
        $rows=Db::all('SELECT t.id,t.product_id,CASE WHEN t.verified_at IS NOT NULL THEN t.gtin14 ELSE NULL END AS gtin14,t.gtin_status AS identifier_status,v.id AS product_info_version_id,v.version_key,v.snapshot_json,o.display_name AS producer FROM api_public_items s JOIN trade_items t ON t.id=s.trade_item_id AND t.current_info_id=s.product_info_version_id JOIN product_info_versions v ON v.id=t.current_info_id AND v.tenant_id=t.tenant_id JOIN organizations o ON o.id=t.tenant_id WHERE '.implode(' AND ',$where).' ORDER BY t.id LIMIT '.($limit+1),$params);
        return Api::collection($rows,$limit,static function($r){$s=json_decode($r['snapshot_json'],true);unset($r['snapshot_json']);return $r+['name'=>$s['name'],'category'=>$s['category'],'net_quantity'=>$s['net_quantity'],'url'=>url('/t/'.$r['id'])];});
    }
    public static function children(string $path,string $id): array {
        $parts=explode('/',$path);$parent=$parts[4];$child=$parts[6];
        if($parent==='products'&&$child==='trade-items')return self::catalog($id);
        if($parent==='batches'){
            $batch=Catalog::publicBatch($id);
            if($child==='alerts')return ['items'=>$batch['alerts'],'checked_at'=>gmdate(DATE_ATOM)];
            $rows=Db::all("SELECT r.id,r.version_key,r.license_id,r.technical_status,r.snapshot_sha256 FROM recipe_versions r JOIN batches b ON b.recipe_version_id=r.id AND b.tenant_id=r.tenant_id WHERE b.id=? AND r.visibility='public' AND r.rights_status='cleared' AND r.license_id IS NOT NULL",[$id]);return ['items'=>$rows];
        }
        if($parent==='labels'){
            $label=self::label($id);$docs=[];foreach($label['snapshot']['document_ids']??[]as$doc){try{$docs[]=self::document($doc);}catch(Problem $e){if($e->status!==404)throw $e;}}
            return ['items'=>$docs];
        }
        [$limit,$cursor]=Api::page();
        if($child==='recipe-versions'){
            if(!Db::one("SELECT id FROM recipe_versions WHERE product_id=? AND visibility='public' AND rights_status='cleared' AND license_id IS NOT NULL LIMIT 1",[$id])){
                Catalog::publicItem($id,true);
            }
        }elseif(!Db::one("SELECT id FROM change_proposals WHERE product_id=? AND visibility='public' LIMIT 1",[$id]))Catalog::publicItem($id,true);
        if($child==='recipe-versions'){
            $where="product_id=? AND visibility='public' AND rights_status='cleared' AND license_id IS NOT NULL AND id>?";$params=[$id,$cursor];
            if(isset($_GET['version_key'])){$where.=' AND version_key=?';$params[]=Catalog::text($_GET,'version_key',60);}
            $rows=Db::all('SELECT id,version_key,license_id,technical_status,snapshot_sha256 FROM recipe_versions WHERE '.$where.' ORDER BY id LIMIT '.($limit+1),$params);
        }else $rows=Db::all("SELECT p.id,p.product_id,p.title,p.body,p.state,p.decision,p.created_at,(SELECT COUNT(*) FROM votes v WHERE v.proposal_id=p.id) AS votes FROM change_proposals p WHERE p.product_id=? AND p.visibility='public' AND p.id>? ORDER BY p.id LIMIT ".($limit+1),[$id,$cursor]);
        return Api::collection($rows,$limit);
    }
    public static function feedback(string $id): array {
        $pub=Catalog::publicItem($id);[$limit,$cursor]=Api::page();$version=$_GET['label_id']??$pub['product_info_version_id'];Api::id((string)$version);
        $label=self::label($version);if($label['trade_item_id']!==$id)throw new Problem(422,'version','Versiunea nu aparține articolului.');
        $where="product_id=? AND product_info_version_id=? AND status='published' AND kind<>'quality' AND id>?";$params=[$pub['product_id'],$version,$cursor];
        if(isset($_GET['batch_id'])){$batch=Catalog::publicBatch(Api::id((string)$_GET['batch_id']));if($batch['trade_item_id']!==$id||$batch['product_info_version_id']!==$version)throw new Problem(422,'batch','Lotul nu corespunde versiunii.');$where.=' AND batch_id=?';$params[]=$batch['batch']['id'];}
        $rows=Db::all('SELECT id,kind,rating,public_text,created_at,batch_id,product_info_version_id FROM feedback WHERE '.$where.' ORDER BY id LIMIT '.($limit+1),$params);
        return Api::collection($rows,$limit);
    }
    public static function ratings(string $id): array {
        $pub=Catalog::publicItem($id);$version=Api::id((string)($_GET['label_id']??$pub['product_info_version_id']));$label=self::label($version);
        if($label['trade_item_id']!==$id)throw new Problem(422,'version','Versiunea nu aparține articolului.');$batchId=null;
        if(isset($_GET['batch_id'])){$batch=Catalog::publicBatch(Api::id((string)$_GET['batch_id']));if($batch['trade_item_id']!==$id||$batch['product_info_version_id']!==$version)throw new Problem(422,'batch','Lotul nu corespunde versiunii.');$batchId=$batch['batch']['id'];}
        return ['product_info_version_id'=>$version,'batch_id'=>$batchId]+Community::aggregates($pub['product_id'],$version,$batchId);
    }
    public static function download(string $id,?array $p=null): never {
        Api::limit('download',1,$p?($p['token']??$p['user']):null);
        $doc=$p?Catalog::owned('documents',$id,$p):Db::one("SELECT * FROM documents WHERE id=? AND visibility='public' AND rights_status='cleared'",[$id]);
        if(!$doc)throw new Problem(404,'not_found','Documentul nu a fost găsit.');
        $base=realpath(Config::get('storage').'/private');$file=realpath(Config::get('storage').'/private/'.$doc['storage_key']);
        if(!$base||!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file))throw new Problem(404,'not_found','Fișierul nu este disponibil.');
        $ext=['application/pdf'=>'pdf','text/plain'=>'txt','application/json'=>'json','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$doc['mime']]??'bin';
        header('Content-Type: '.$doc['mime']);header('Content-Length: '.filesize($file));header('Content-Disposition: attachment; filename="document-'.$id.'.'.$ext.'"');
        header('Content-Security-Policy: sandbox');if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')readfile($file);exit;
    }
    public static function vocabulary(string $path): array {
        $name=basename($path);
        return match($name){
            'allergens'=>['schema_version'=>'openaliment.vocabularies.v1','items'=>FoodData::ALLERGENS],
            'licenses'=>['items'=>['CC-BY-4.0','CC-BY-SA-4.0','CC0-1.0'],'hosting_requires_open_license'=>false],
            'units'=>['items'=>['pack','g','ml'],'nutrition_bases'=>['per_100_g','per_100_ml','per_portion']],
            'nutrition'=>['fields'=>['energy_kj','energy_kcal','fat_g','saturates_g','carbohydrate_g','sugars_g','polyols_g','fibre_g','protein_g','salt_g'],'preparation'=>['as_sold','as_prepared'],'jurisdictions'=>['EU','US','other']],
            'link-types'=>['namespace'=>ResolverRegistry::NS,'profile_url'=>url('/resolver/profile'),'vocabulary_url'=>'https://ref.gs1.org/voc/','examples'=>['gs1:pip','gs1:allergenInfo','gs1:nutritionalInfo','gs1:masterData']],
            'privacy'=>PlatformInformation::privacy(),
            default=>throw new Problem(404,'not_found','Dicționarul nu a fost găsit.')
        };
    }
}
