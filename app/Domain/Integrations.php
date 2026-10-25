<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Db,Problem};

final class Integrations {
    private static function connector(array $p,string $id): array {return Db::one('SELECT * FROM integration_connectors WHERE id=? AND tenant_id=?',[$id,$p['tenant']])??throw new Problem(404,'connector','Conectorul nu a fost găsit.');}
    public static function list(array $p): array {return Db::all('SELECT id,name,kind,created_at FROM integration_connectors WHERE tenant_id=? ORDER BY created_at,id LIMIT 200',[$p['tenant']]);}
    public static function create(array $p,array $data): array {
        Auth::write($p);$kind=$data['kind']??null;if(!in_array($kind,['catalog','retail_receipt'],true))throw new Problem(422,'connector_kind','Tip de conector incorect.');
        return Db::atomic(function()use($p,$data,$kind){$id=Db::id();ProducerQuota::count($p,'integration_connectors');Db::insert('integration_connectors',['id'=>$id,'tenant_id'=>$p['tenant'],'name'=>Catalog::text($data,'name',180),'kind'=>$kind,'created_at'=>Db::now()]);Catalog::audit($p,'connector.created',$id,['kind'=>$kind]);return ['id'=>$id,'kind'=>$kind];});
    }
    public static function mappings(array $p,string $id): array {self::connector($p,$id);$rows=Db::all('SELECT id,version_key,sha256,mapping_json,created_at FROM integration_mappings WHERE connector_id=? AND tenant_id=? ORDER BY created_at,id LIMIT 200',[$id,$p['tenant']]);foreach($rows as&$r){$r['mapping']=json_decode($r['mapping_json'],true,64,JSON_THROW_ON_ERROR);unset($r['mapping_json']);}return $rows;}
    public static function mapping(array $p,string $connector,array $data): array {
        Auth::write($p);self::connector($p,$connector);if(!is_array($data['mapping']??null))throw new Problem(422,'partner_mapping','Maparea lipsește.');$mapping=PartnerMapping::validate($data['mapping']);$version=Catalog::text($data,'version_key',60);$json=Db::json($mapping);
        return Db::atomic(function()use($p,$connector,$version,$json){
            Db::one('SELECT id FROM integration_connectors WHERE id=? AND tenant_id=? FOR UPDATE',[$connector,$p['tenant']]);
            if(Db::one('SELECT id FROM integration_mappings WHERE connector_id=? AND version_key=?',[$connector,$version]))throw new Problem(409,'mapping_version','Versiunea mapării există deja. Creează o versiune nouă.');
            $id=Db::id();$sha=hash('sha256',$json);ProducerQuota::count($p,'integration_mappings');Db::insert('integration_mappings',['id'=>$id,'tenant_id'=>$p['tenant'],'connector_id'=>$connector,'version_key'=>$version,'mapping_json'=>$json,'sha256'=>$sha,'created_at'=>Db::now()]);Catalog::audit($p,'connector.mapping_created',$id,['connector_id'=>$connector,'sha256'=>$sha]);return ['id'=>$id,'sha256'=>$sha,'version_key'=>$version];
        });
    }
    private static function mapped(array $p,array $connector,array $data): array {
        $mappingId=Catalog::text($data,'mapping_id',32);$mapping=Db::one('SELECT * FROM integration_mappings WHERE id=? AND connector_id=? AND tenant_id=?',[$mappingId,$connector['id'],$p['tenant']])??throw new Problem(404,'mapping','Maparea nu a fost găsită.');
        if(!is_array($data['payload']??null))throw new Problem(422,'payload','Datele partenerului lipsesc.');
        $out=PartnerMapping::apply(json_decode($mapping['mapping_json'],true,32,JSON_THROW_ON_ERROR),$data['payload']);
        $operation=$data['operation']??null;
        if($connector['kind']==='retail_receipt'&&$operation==='receipt')$canonical=DigitalReceipt::validate($out);
        elseif($connector['kind']==='catalog'&&$operation==='product'){
            self::keys($out,['name','category','trade_name','gtin','claim_evidence','label']);if(!is_array($out['label']??null))throw new Problem(422,'label','Informațiile etichetei lipsesc.');
            self::keys($out['label'],['version_key','name','category','description','ingredients','allergens','net_quantity','operator','storage','after_opening','origin','nutrition','usage','document_ids','food_data']);
            $label=Catalog::labelData($out['label']);$label['version_key']=Catalog::text($out['label'],'version_key',60);
            $original=Catalog::text($out,'gtin',14,false);$gtin=$original?DigitalLink::gtin($original):null;DemoBread::assertCommercial($gtin);
            $canonical=['name'=>Catalog::text($out,'name',180),'category'=>Catalog::text($out,'category',80),'trade_name'=>Catalog::text($out,'trade_name',180),'gtin'=>$gtin,'claim_evidence'=>Catalog::text($out,'claim_evidence',5000,false),'label'=>$label];
        }elseif($connector['kind']==='catalog'&&$operation==='batch'){
            self::keys($out,['trade_external_id','label_version_key','lot_code','production_date','best_before','use_by','public_notes']);
            $canonical=Catalog::batchData($out);$canonical['trade_external_id']=Catalog::text($out,'trade_external_id',120);$canonical['label_version_key']=Catalog::text($out,'label_version_key',60);$canonical['lot_code']=Catalog::text($out,'lot_code',120);
            if($canonical['lot_code']!==($out['lot_code']??null))throw new Problem(422,'lot_code','Lotul nu poate avea spații la capete.');
        }else throw new Problem(422,'integration_operation','Operațiunea nu este permisă pentru acest conector.');
        return [$canonical,$mapping,$operation];
    }
    private static function keys(array $data,array $allowed): void {if(array_diff(array_keys($data),$allowed))throw new Problem(422,'mapping_target','Maparea conține destinații neacceptate pentru operațiune.');}
    public static function preview(array $p,string $id,array $data): array {
        $connector=self::connector($p,$id);[$canonical,$mapping,$operation]=self::mapped($p,$connector,$data);
        return ['schema_version'=>'openaliment.integration-preview.v1','operation'=>$operation,'mapping_sha256'=>$mapping['sha256'],'canonical'=>$canonical,'writes'=>false,'publication'=>'requires_separate_approval'];
    }
    public static function ingest(array $p,string $id,array $data): array {
        Auth::write($p);$event=Catalog::text($data,'event_key',128);$external=Catalog::text($data,'external_id',120);$sha=hash('sha256',Db::json($data));
        return Db::atomic(function()use($p,$id,$data,$event,$external,$sha){
            // Serialize each connector, including first delivery of a previously unseen external ID.
            $connector=Db::one('SELECT * FROM integration_connectors WHERE id=? AND tenant_id=? FOR UPDATE',[$id,$p['tenant']])??throw new Problem(404,'connector','Conectorul nu a fost găsit.');
            $previous=Db::one('SELECT request_sha256,result_json FROM integration_events WHERE connector_id=? AND event_key=?',[$id,$event]);
            if($previous){if(!hash_equals($previous['request_sha256'],$sha))throw new Problem(409,'integration_event_conflict','Evenimentul a fost folosit cu alte date.');return json_decode($previous['result_json'],true,64,JSON_THROW_ON_ERROR);}
            [$canonical,$mapping,$operation]=self::mapped($p,$connector,$data);
            if($operation==='receipt')$result=DigitalReceipt::store($p,$id,$canonical);
            else{
                $reference=$operation==='product'?$external:$canonical['trade_external_id'];
                $binding=Db::one('SELECT * FROM integration_bindings WHERE tenant_id=? AND connector_id=? AND external_id=?',[$p['tenant'],$id,$reference]);
                if($operation==='product'){
                    if(!$binding){$product=Catalog::product($p,$canonical)['id'];$item=Catalog::trade($p,$product,['name'=>$canonical['trade_name'],'gtin'=>$canonical['gtin'],'claim_evidence'=>$canonical['claim_evidence']])['id'];$binding=['product_id'=>$product,'trade_item_id'=>$item];Db::insert('integration_bindings',['tenant_id'=>$p['tenant'],'connector_id'=>$id,'external_id'=>$external]+$binding);}
                    else{$item=Catalog::owned('trade_items',$binding['trade_item_id'],$p);if($item['gtin14']!==$canonical['gtin'])throw new Problem(409,'identifier_change','GTIN diferit pentru articolul asociat. Folosește un articol extern nou.');}
                    if(Db::one('SELECT id FROM product_info_versions WHERE tenant_id=? AND trade_item_id=? AND version_key=?',[$p['tenant'],$binding['trade_item_id'],$canonical['label']['version_key']]))throw new Problem(409,'label_version','Versiunea etichetei există deja. Nu poate fi rescrisă.');
                    $label=Catalog::label($p,$binding['trade_item_id'],$canonical['label']);$result=['product_id'=>$binding['product_id'],'trade_item_id'=>$binding['trade_item_id'],'label_id'=>$label['id'],'state'=>'draft'];
                }else{
                    if(!$binding)throw new Problem(422,'external_reference','Articolul extern nu a fost importat prin acest conector.');
                    if(Db::one('SELECT id FROM batches WHERE tenant_id=? AND trade_item_id=? AND lot_code=?',[$p['tenant'],$binding['trade_item_id'],$canonical['lot_code']]))throw new Problem(409,'lot_exists','Lotul există deja. Nu poate fi rescris printr-un eveniment nou.');
                    $label=Db::one("SELECT id FROM product_info_versions WHERE tenant_id=? AND trade_item_id=? AND version_key=? AND state='published'",[$p['tenant'],$binding['trade_item_id'],$canonical['label_version_key']]);if(!$label)throw new Problem(422,'approved_version','Publică versiunea explicită a etichetei înainte de importul lotului.');
                    $result=Catalog::batch($p,$binding['trade_item_id'],$canonical+['product_info_version_id'=>$label['id']]);
                }
            }
            $result+=['operation'=>$operation,'mapping_id'=>$mapping['id'],'mapping_sha256'=>$mapping['sha256'],'event_key'=>$event];
            Db::insert('integration_events',['id'=>Db::id(),'tenant_id'=>$p['tenant'],'connector_id'=>$id,'mapping_id'=>$mapping['id'],'event_key'=>$event,'request_sha256'=>$sha,'result_json'=>Db::json($result),'created_at'=>Db::now()]);Catalog::audit($p,'connector.ingested',$id,['operation'=>$operation,'event_key'=>$event,'mapping_sha256'=>$mapping['sha256']]);return $result;
        });
    }
}
