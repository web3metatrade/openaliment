<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Config,Db,Problem};
final class Catalog {
    public static function text(array $d,string $key,int $max=180,bool $required=true): ?string {
        $v=$d[$key]??null;if($v===null||$v===''){if($required)throw new Problem(422,'validation','Completează câmpul '.$key.'.');return null;}
        if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$v))throw new Problem(422,'validation','Valoare incorectă pentru '.$key.'.');$clean=trim($v);if($required&&$clean==='')throw new Problem(422,'validation','Completează câmpul '.$key.'.');return $clean;
    }
    public static function owned(string $table,string $id,array $p): array {
        $allowed=['products','trade_items','product_info_versions','batches','recipe_versions','documents','feedback','change_proposals','alerts'];
        if(!in_array($table,$allowed,true))throw new \LogicException('Invalid repository');
        return Db::one('SELECT * FROM '.$table.' WHERE id=? AND tenant_id=?',[$id,$p['tenant']])??throw new Problem(404,'not_found','Înregistrarea nu a fost găsită.');
    }
    public static function audit(array $p,string $action,string $id,array $details=[]): void {
        $details['_actor']=['auth_type'=>$p['auth_type']??'session','credential_id'=>$p['token']??null,'grant_id'=>$p['grant_id']??null,'client_id'=>$p['client_id']??null];
        Db::insert('audit_events',['id'=>Db::id(),'tenant_id'=>$p['tenant'],'actor_id'=>$p['user']??null,'action'=>$action,'object_id'=>$id,'details_json'=>Db::json($details),'created_at'=>Db::now()]);
    }
    public static function product(array $p,array $d): array {Auth::write($p);return Db::atomic(function()use($p,$d){
        $id=Db::id();ProducerQuota::count($p,'products');Db::insert('products',['id'=>$id,'tenant_id'=>$p['tenant'],'name'=>self::text($d,'name'),'category'=>self::text($d,'category',80),'created_at'=>Db::now()]);self::audit($p,'product.created',$id);return ['id'=>$id];
    });}
    public static function trade(array $p,string $product,array $d): array {Auth::write($p);self::owned('products',$product,$p);return Db::atomic(function()use($p,$product,$d){
        $original=self::text($d,'gtin',14,false);$g=$original?DigitalLink::gtin($original):null;
        DemoBread::assertCommercial($g);
        if($g&&Db::one('SELECT trade_item_id FROM gtin_registry WHERE gtin14=?',[$g]))throw new Problem(409,'gtin_claim','GTIN este deja înregistrat; drepturile se clarifică prin administrator.');
        $id=Db::id();ProducerQuota::count($p,'trade_items');Db::insert('trade_items',['id'=>$id,'tenant_id'=>$p['tenant'],'product_id'=>$product,'name'=>self::text($d,'name'),'gtin14'=>$g,'gtin_original'=>$original,'claim_evidence'=>self::text($d,'claim_evidence',5000,false),'created_at'=>Db::now()]);self::audit($p,'trade_item.created',$id);return ['id'=>$id,'gtin14'=>$g,'gtin_status'=>'unverified'];
    });}
    public static function labelData(array $d): array {
        $out=[];
        foreach(['name'=>180,'category'=>80,'description'=>2000,'ingredients'=>5000,'allergens'=>1500,'net_quantity'=>100,'operator'=>500,'storage'=>1500,'after_opening'=>1000,'origin'=>300,'nutrition'=>3000,'usage'=>2000] as $k=>$max)$out[$k]=self::text($d,$k,$max,in_array($k,['name','category','ingredients','allergens','net_quantity','operator'],true));
        if(isset($d['document_ids'])&&!is_array($d['document_ids']))throw new Problem(422,'documents','Lista documentelor este incorectă.');$out['document_ids']=array_values($d['document_ids']??[]);if(count($out['document_ids'])>20)throw new Problem(422,'documents','Prea multe documente.');
        foreach($out['document_ids'] as $v)if(!is_string($v)||!preg_match('/\A[a-f0-9]{32}\z/D',$v))throw new Problem(422,'documents','Referință de document incorectă.');
        if(array_key_exists('food_data',$d))$out['food_data']=FoodData::validate($d['food_data']);
        return $out;
    }
    public static function label(array $p,string $itemId,array $d): array {Auth::write($p);$item=self::owned('trade_items',$itemId,$p);return Db::atomic(function()use($p,$item,$d){
        $data=self::labelData($d);foreach($data['document_ids']as$id){$doc=self::owned('documents',$id,$p);if($doc['product_id']!==$item['product_id'])throw new Problem(422,'documents','Documentul aparține altui produs.');}
        $id=Db::id();$json=Db::json($data);ProducerQuota::count($p,'product_info_versions');Db::insert('product_info_versions',['id'=>$id,'tenant_id'=>$p['tenant'],'product_id'=>$item['product_id'],'trade_item_id'=>$item['id'],'version_key'=>self::text($d,'version_key',60),'snapshot_json'=>$json,'snapshot_sha256'=>hash('sha256',$json),'created_at'=>Db::now()]);self::audit($p,'label.created',$id);return ['id'=>$id,'state'=>'draft'];
    });}
    public static function publishLabel(array $p,string $id): array {Auth::approve($p);return Db::atomic(function()use($p,$id){
        $v=self::owned('product_info_versions',$id,$p);if($v['state']!=='draft')throw new Problem(409,'state','Această versiune este deja închisă.');
        $d=json_decode($v['snapshot_json'],true,512,JSON_THROW_ON_ERROR);self::labelData($d);
        foreach($d['document_ids']as$docId){$doc=self::owned('documents',$docId,$p);if($doc['visibility']!=='public'||$doc['rights_status']!=='cleared')throw new Problem(422,'document_rights','Un document selectat nu are publicarea aprobată.');}
        Db::run("UPDATE product_info_versions SET state='published',approved_by=?,approved_at=? WHERE id=? AND tenant_id=? AND state='draft'",[$p['user'],Db::now(),$id,$p['tenant']]);
        Db::run('UPDATE trade_items SET current_info_id=? WHERE id=? AND tenant_id=?',[$id,$v['trade_item_id'],$p['tenant']]);ApiPublicIndex::refresh($v['trade_item_id']);self::audit($p,'label.published',$id,['sha256'=>$v['snapshot_sha256']]);return ['id'=>$id,'state'=>'published'];
    });}
    public static function batchData(array $d): array {
        $snapshot=[];foreach(['production_date','best_before','use_by']as$key){$val=self::text($d,$key,10,false);if($val){$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$val);if(!$date||$date->format('Y-m-d')!==$val)throw new Problem(422,'date','Data este incorectă.');}$snapshot[$key]=$val;}
        if($snapshot['best_before']&&$snapshot['use_by'])throw new Problem(422,'date','Selectează un singur tip de dată.');
        $end=$snapshot['best_before']??$snapshot['use_by'];if($end&&$snapshot['production_date']&&$end<$snapshot['production_date'])throw new Problem(422,'date','Data finală precede producția.');
        $snapshot['public_notes']=self::text($d,'public_notes',2000,false);return $snapshot;    }
    public static function batch(array $p,string $itemId,array $d): array {Auth::write($p);$item=self::owned('trade_items',$itemId,$p);return Db::atomic(function()use($p,$item,$d){
        $v=self::owned('product_info_versions',self::text($d,'product_info_version_id',32),$p);
        if($v['trade_item_id']!==$item['id']||$v['state']!=='published')throw new Problem(422,'label','Lotul necesită o versiune publicabilă aprobată a acestui articol.');
        $recipe=self::text($d,'recipe_version_id',32,false);if($recipe){$r=self::owned('recipe_versions',$recipe,$p);if($r['product_id']!==$item['product_id'])throw new Problem(422,'recipe','Rețeta aparține altui produs.');}
        $lot=self::text($d,'lot_code',120);if($lot!==($d['lot_code']??null))throw new Problem(422,'lot_code','Lotul nu poate începe sau termina cu spații.');
        $snapshot=self::batchData($d);$json=Db::json($snapshot);$id=Db::id();
        ProducerQuota::count($p,'batches');Db::insert('batches',['id'=>$id,'tenant_id'=>$p['tenant'],'product_id'=>$item['product_id'],'trade_item_id'=>$item['id'],'product_info_version_id'=>$v['id'],'recipe_version_id'=>$recipe,'lot_code'=>$lot,'snapshot_json'=>$json,'snapshot_sha256'=>hash('sha256',$json),'created_at'=>Db::now()]);self::audit($p,'batch.created',$id);return ['id'=>$id,'recipe_version_id'=>$recipe,'visibility'=>'private'];
    });}
    public static function publishBatch(array $p,string $id): array {Auth::approve($p);return Db::atomic(function()use($p,$id){
        $b=self::owned('batches',$id,$p);$label=self::owned('product_info_versions',$b['product_info_version_id'],$p);if($label['state']!=='published')throw new Problem(422,'label','Publică mai întâi informațiile aplicabile lotului.');if($b['visibility']==='public')throw new Problem(409,'state','Lotul este deja public.');
        Db::run("UPDATE batches SET visibility='public',approved_at=?,approved_by=? WHERE id=? AND tenant_id=?",[Db::now(),$p['user'],$id,$p['tenant']]);self::event($p,$id,'published',['state'=>$b['state']]);self::audit($p,'batch.published',$id);return ['id'=>$id,'visibility'=>'public'];
    });}
    public static function releaseBatch(array $p,string $id): array {Auth::approve($p);return Db::atomic(function()use($p,$id){
        $b=self::owned('batches',$id,$p);if(!in_array($b['state'],['planned','in_production','on_hold'],true))throw new Problem(409,'state','Starea lotului nu permite eliberarea.');
        $v=self::owned('product_info_versions',$b['product_info_version_id'],$p);if($v['state']!=='published')throw new Problem(422,'label','Informațiile aplicabile nu sunt aprobate.');
        if(Db::one('SELECT id FROM alerts WHERE batch_id=? AND resolved_at IS NULL',[$id]))throw new Problem(422,'alert','Lotul are alerte active.');
        Db::run("UPDATE batches SET state='released' WHERE id=? AND tenant_id=?",[$id,$p['tenant']]);self::event($p,$id,'released',['decision'=>'producer_release']);self::audit($p,'batch.released',$id);return ['id'=>$id,'state'=>'released'];
    });}
    public static function event(array $p,string $batch,string $type,array $data): void {Db::insert('batch_events',['id'=>Db::id(),'tenant_id'=>$p['tenant'],'batch_id'=>$batch,'event_type'=>$type,'payload_json'=>Db::json($data),'actor_id'=>$p['user'],'created_at'=>Db::now()]);}
    public static function alert(array $p,string $id,array $d): array {Auth::approve($p,'alerts');return Db::atomic(function()use($p,$id,$d){
        self::owned('batches',$id,$p);$message=self::text($d,'message',3000);$state=$d['state']??'on_hold';if(!in_array($state,['on_hold','recalled'],true))throw new Problem(422,'state','Starea alertei este incorectă.');
        $alert=Db::id();Db::insert('alerts',['id'=>$alert,'tenant_id'=>$p['tenant'],'batch_id'=>$id,'message'=>$message,'created_by'=>$p['user'],'created_at'=>Db::now()]);Db::run('UPDATE batches SET state=? WHERE id=? AND tenant_id=?',[$state,$id,$p['tenant']]);self::event($p,$id,'alert',['alert_id'=>$alert,'state'=>$state,'message'=>$message]);self::audit($p,'batch.alert',$id);return ['id'=>$alert];
    });}
    public static function resolveAlert(array $p,string $id,array $d): array {Auth::approve($p,'alerts');return Db::atomic(function()use($p,$id,$d){
        $a=self::owned('alerts',$id,$p);if($a['resolved_at'])throw new Problem(409,'state','Alerta este deja închisă.');$reason=self::text($d,'resolution',3000);
        Db::run('UPDATE alerts SET resolved_at=?,resolved_by=?,resolution=? WHERE id=? AND tenant_id=?',[Db::now(),$p['user'],$reason,$id,$p['tenant']]);self::event($p,$a['batch_id'],'alert_resolved',['alert_id'=>$id,'resolution'=>$reason]);
        self::audit($p,'alert.resolved',$id);return ['id'=>$id];
    });}
    public static function publicItem(string $id,bool $isProduct=false): array {
        $where=$isProduct?'t.product_id=?':'t.id=?';
        $row=Db::one('SELECT t.id,t.product_id,t.gtin14,t.gtin_status,t.verified_at,t.tenant_id,v.id AS info_id,v.version_key,v.snapshot_json,v.snapshot_sha256,v.approved_at,o.display_name AS producer FROM trade_items t JOIN product_info_versions v ON v.id=t.current_info_id AND v.tenant_id=t.tenant_id JOIN organizations o ON o.id=t.tenant_id WHERE '.$where." AND v.state='published' ORDER BY t.created_at,t.id LIMIT 1",[$id]);
        if(!$row)throw new Problem(404,'not_found','Produsul nu a fost găsit.');return self::projection($row);
    }
    public static function publicBatch(string $id): array {
        $r=Db::one("SELECT b.id AS batch_id,b.lot_code,b.state AS batch_state,b.snapshot_json AS batch_json,b.product_id,b.tenant_id,b.trade_item_id AS id,t.gtin14,t.gtin_status,t.verified_at,v.id AS info_id,v.version_key,v.snapshot_json,v.snapshot_sha256,v.approved_at,o.display_name AS producer FROM batches b JOIN trade_items t ON t.id=b.trade_item_id AND t.tenant_id=b.tenant_id JOIN product_info_versions v ON v.id=b.product_info_version_id AND v.tenant_id=b.tenant_id JOIN organizations o ON o.id=b.tenant_id WHERE b.id=? AND b.visibility='public' AND v.state='published'",[$id]);
        if(!$r)throw new Problem(404,'not_found','Lotul nu a fost găsit.');$out=self::projection($r);$out['batch']=['id'=>$r['batch_id'],'lot_code'=>$r['lot_code'],'state'=>$r['batch_state'],'data'=>json_decode($r['batch_json'],true)];
        $out['alerts']=Db::all('SELECT id,message,created_at FROM alerts WHERE batch_id=? AND resolved_at IS NULL ORDER BY created_at DESC',[$id]);return $out;
    }
    private static function projection(array $r): array {
        $d=json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR);$docs=[];
        foreach($d['document_ids']??[]as$id){$doc=Db::one("SELECT id,title,sha256,mime,license_id FROM documents WHERE id=? AND tenant_id=? AND product_id=? AND visibility='public' AND rights_status='cleared'",[$id,$r['tenant_id'],$r['product_id']]);if($doc)$docs[]=$doc;}unset($d['document_ids']);
        return ['product_id'=>$r['product_id'],'trade_item_id'=>$r['id'],'producer'=>$r['producer'],'gtin14'=>$r['verified_at']!==null?$r['gtin14']:null,'identifier_status'=>$r['gtin_status'],'identity_notice'=>in_array($r['gtin_status'],['disputed','withdrawn'],true)?'Dreptul de administrare al identificatorului este în verificare. Informațiile istorice și alertele lotului rămân disponibile.':null,'product_info_version_id'=>$r['info_id'],'version_key'=>$r['version_key'],'approved_at'=>$r['approved_at'],'sha256'=>$r['snapshot_sha256'],'label'=>$d,'documents'=>$docs,'alerts'=>[]];
    }
    public static function qr(array $p,string $type,string $id): array {
        if($type==='batch'){$b=self::owned('batches',$id,$p);$i=self::owned('trade_items',$b['trade_item_id'],$p);if($b['visibility']!=='public')throw new Problem(422,'publication','Publică lotul înainte de generarea QR.');$native='/b/'.$id;$lot=$b['lot_code'];}
        else{$i=self::owned('trade_items',$id,$p);if(!$i['current_info_id'])throw new Problem(422,'publication','Publică eticheta înainte de generarea QR.');$native='/t/'.$id;$lot=null;}
        $eligible=$i['gtin_status']==='verified'&&(Config::get('gs1_transport_verified')||ResolverRegistry::transportReady())&&($lot===null||DigitalLink::canGenerate($lot));
        return ['kind'=>$type,'url'=>url($eligible?DigitalLink::path($i['gtin14'],$lot):$native),'native_url'=>url($native),'digital_link'=>$eligible,'reason'=>$eligible?null:($i['gtin_status']!=='verified'?'GTIN fără drept de administrare verificat.':'Profilul Digital Link nu este verificat pe hosting pentru acest identificator.'),'resolver_level'=>\App\Core\Migrations::resolver()?'gs1_conformant_resolver_1.2.1':'digital_link_landing'];
    }
}
