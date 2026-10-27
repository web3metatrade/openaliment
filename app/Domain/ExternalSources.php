<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Auth, Db, Problem};

/** Private associations and proposals. The external catalog never owns a trade item. */
final class ExternalSources
{
    public const FIELDS=['name','ingredients','allergens','net_quantity','storage','nutrition'];

    public static function portable(array $p): array
    {
        if(!\App\Core\Migrations::external())return [];
        $out=[];$bytes=0;
        if(\App\Core\Migrations::external())foreach(Db::all("SELECT object_id,details_json FROM audit_events WHERE tenant_id=? AND action='external.label_created' ORDER BY created_at,id LIMIT 10001",[$p['tenant']])as$row){
            $details=json_decode($row['details_json'],true);$record=ExternalCatalog::revision($details['revision_id'],true);
            $out[]=['label_id'=>$row['object_id'],'revision'=>$record,'fields'=>$details['fields'],'source_base_url'=>\App\Core\Config::get('url')];$bytes+=strlen(Db::json(end($out)));
        }
        // Imported provenance is carried as evidence, never as a mandate or active sharing grant.
        if((int)Db::one("SELECT COALESCE(SUM(OCTET_LENGTH(payload_json)),0) AS n FROM imports WHERE tenant_id=? AND JSON_LENGTH(payload_json,'$.data.external_sources')>0",[$p['tenant']])['n']>8000000)throw new Problem(413,'external_export_size','Proveniența importată necesită împărțirea transferului.');
        foreach(Db::all("SELECT payload_json FROM imports WHERE tenant_id=? AND JSON_LENGTH(payload_json,'$.data.external_sources')>0 ORDER BY id",[$p['tenant']])as$row){
            $package=json_decode($row['payload_json'],true,64);foreach($package['data']['external_sources']??[]as$source){$out[]=$source;$bytes+=strlen(Db::json($source));}
            if(count($out)>10000||$bytes>8000000)throw new Problem(413,'external_export_size','Proveniența externă depășește limita unui pachet. Împarte transferul în pachete.');
        }
        if(count($out)>10000||$bytes>8000000)throw new Problem(413,'external_export_size','Proveniența externă depășește limita unui pachet.');
        return array_values(array_column(array_map(static fn($r)=>['key'=>hash('sha256',Db::json($r)),'row'=>$r],$out),'row','key'));
    }

    public static function item(array $p, string $id): array
    {
        ExternalCatalog::required();Api::id($id);
        return Catalog::owned('trade_items',$id,$p);
    }

    public static function list(array $p, ?string $itemId=null): array
    {
        ExternalCatalog::required();if ($itemId!==null) self::item($p,$itemId);
        [$limit,$cursor]=Api::page();
        $params=[$p['tenant'],$cursor];$where='b.tenant_id=? AND b.id>?';
        if ($itemId!==null) {$where.=' AND b.trade_item_id=?';$params[]=$itemId;}
        $rows=Db::all('SELECT b.*,e.gtin14,e.current_revision_id,t.name FROM external_product_bindings b JOIN external_products e ON e.id=b.external_product_id JOIN trade_items t ON t.id=b.trade_item_id AND t.tenant_id=b.tenant_id WHERE '.$where.' ORDER BY b.id LIMIT '.($limit+1),$params);
        return Api::collection($rows,$limit);
    }

    public static function bind(array $p, string $itemId, array $data): array
    {
        Auth::write($p);$item=self::item($p,$itemId);$external=Catalog::text($data,'external_product_id',32);Api::id($external);
        $product=Db::one('SELECT * FROM external_products WHERE id=?',[$external]);
        if (!$product||!$item['gtin14']||$product['gtin14']!==$item['gtin14'])throw new Problem(422,'external_identity','Asocierea cere exact GTIN-ul articolului.');
        return Db::atomic(static function () use ($p,$itemId,$external): array {
            Db::one('SELECT id FROM trade_items WHERE id=? AND tenant_id=? FOR UPDATE',[$itemId,$p['tenant']]);
            $old=Db::one('SELECT id FROM external_product_bindings WHERE tenant_id=? AND trade_item_id=? AND external_product_id=?',[$p['tenant'],$itemId,$external]);
            $id=$old['id']??Db::id();
            if ($old)Db::run("UPDATE external_product_bindings SET state='active' WHERE id=? AND tenant_id=?",[$id,$p['tenant']]);
            else Db::insert('external_product_bindings',['id'=>$id,'tenant_id'=>$p['tenant'],'trade_item_id'=>$itemId,'external_product_id'=>$external,'created_by'=>$p['user'],'created_at'=>Db::now()]);
            Catalog::audit($p,'external.binding',$id,['trade_item_id'=>$itemId,'external_product_id'=>$external]);return ['id'=>$id,'state'=>'active'];
        });
    }

    public static function disconnect(array $p, string $itemId, string $bindingId): array
    {
        Auth::write($p);self::item($p,$itemId);Api::id($bindingId);
        if (!Db::one('SELECT id FROM external_product_bindings WHERE id=? AND trade_item_id=? AND tenant_id=?',[$bindingId,$itemId,$p['tenant']]))throw new Problem(404,'external_binding','Asocierea nu a fost găsită.');
        Db::run("UPDATE external_product_bindings SET state='disconnected' WHERE id=? AND tenant_id=?",[$bindingId,$p['tenant']]);Catalog::audit($p,'external.disconnected',$bindingId);
        return ['id'=>$bindingId,'state'=>'disconnected'];
    }

    public static function candidate(array $record): array
    {
        $a=$record['allergens'];$parts=[];
        if ($a['contains'])$parts[]='Conține: '.implode(', ',array_map(static fn($key)=>FoodData::ALLERGENS[$key],$a['contains']));
        if ($a['may_contain'])$parts[]='Posibile urme: '.implode(', ',array_map(static fn($key)=>FoodData::ALLERGENS[$key],$a['may_contain']));
        if ($a['negative_declaration'])$parts[]='Sursa externă raportează o declarație fără alergeni; verifică eticheta.';
        $nutrition=[];
        $names=['energy_kj'=>'Energie (kJ)','energy_kcal'=>'Energie (kcal)','fat_g'=>'Grăsimi','saturates_g'=>'Acizi grași saturați','carbohydrate_g'=>'Carbohidrați','sugars_g'=>'Zaharuri','polyols_g'=>'Polioli','fibre_g'=>'Fibre','protein_g'=>'Proteine','salt_g'=>'Sare'];
        foreach ($record['nutrition']['sets'] as $set) {
            if ($set['kind']!=='reported')continue;
            $values=[];$modifiers=(array)$set['modifiers'];foreach($set['values']as$key=>$value)$values[]=($names[$key]??$key).': '.($modifiers[$key]??'').str_replace('.',',',(string)$value).(str_ends_with($key,'_g')?' g':'');
            $nutrition[]=(['per_100_g'=>'Per 100 g','per_100_ml'=>'Per 100 ml','per_portion'=>'Per porție (cantitate neconfirmată)'][$set['basis']]).' · '.($set['preparation']==='as_sold'?'produs ca atare':'produs preparat').': '.implode('; ',$values);
        }
        return ['name'=>$record['name'],'ingredients'=>$record['ingredients'],'allergens'=>$parts?implode('. ',$parts):null,'net_quantity'=>$record['net_quantity'],'storage'=>$record['storage_text'],'nutrition'=>$nutrition?implode("\n",$nutrition):null];
    }

    public static function preview(array $p, string $itemId, array $data): array
    {
        $item=self::item($p,$itemId);$revision=Catalog::text($data,'revision_id',32);$record=ExternalCatalog::revision($revision);
        if (!$item['gtin14']||$record['gtin14']!==$item['gtin14'])throw new Problem(422,'external_identity','Fișa externă identifică alt articol.');
        $fields=$data['fields']??self::FIELDS;
        if (!is_array($fields)||!array_is_list($fields)||!$fields||array_diff($fields,self::FIELDS)||count($fields)!==count(array_unique($fields)))throw new Problem(422,'external_fields','Selectează câmpurile acceptate pentru precompletare.');
        $label=$item['current_info_id']?Catalog::owned('product_info_versions',$item['current_info_id'],$p):null;
        $base=$label?json_decode($label['snapshot_json'],true,40,JSON_THROW_ON_ERROR):[];
        $candidate=self::candidate($record);$diff=[];
        foreach($fields as$field)if($candidate[$field]!==null)$diff[$field]=['current'=>$base[$field]??null,'external'=>$candidate[$field]];
        return ['writes'=>false,'revision_id'=>$revision,'external_product_id'=>$record['external_product_id'],'trade_item_id'=>$itemId,'current_label_id'=>$label['id']??null,'fields'=>$fields,'changes'=>(object)$diff,'licenses'=>$record['licenses'],'source_url'=>$record['source_url'],'publication'=>'requires_separate_approval','structured_facts'=>'require_producer_confirmation'];
    }

    public static function draft(array $p, string $itemId, array $data): array
    {
        Auth::write($p);
        return Db::atomic(static function () use ($p,$itemId,$data): array {
            ExternalCatalog::required();Api::id($itemId);
            $item=Db::one('SELECT * FROM trade_items WHERE id=? AND tenant_id=? FOR UPDATE',[$itemId,$p['tenant']]);
            if(!$item)throw new Problem(404,'not_found','Articolul nu a fost găsit.');
            $preview=self::preview($p,$itemId,$data);
            if (array_key_exists('expected_label_id',$data)&&$data['expected_label_id']!==$preview['current_label_id'])throw new Problem(409,'external_stale','Versiunea articolului s-a schimbat. Compară din nou.');
            $snapshot=$item['current_info_id']?json_decode(Catalog::owned('product_info_versions',$item['current_info_id'],$p)['snapshot_json'],true,40,JSON_THROW_ON_ERROR):[];
            foreach($preview['changes']as$field=>$change)$snapshot[$field]=$change['external'];
            if (array_intersect(array_keys((array)$preview['changes']),['ingredients','allergens','nutrition']))$snapshot['food_data']=FoodData::unknown();
            $snapshot['version_key']=Catalog::text($data,'version_key',60);
            // Required legal operator/category remain the producer's responsibility.
            foreach(['category','operator','ingredients','allergens','net_quantity'] as $field)if(isset($data['completion'][$field]))$snapshot[$field]=Catalog::text($data['completion'],$field,$field==='ingredients'?5000:1500);
            $label=Catalog::label($p,$itemId,$snapshot);
            $binding=self::bind($p,$itemId,['external_product_id'=>$preview['external_product_id']]);
            Db::run('UPDATE external_product_bindings SET last_import_revision_id=?,last_import_label_id=? WHERE tenant_id=? AND id=?',[$preview['revision_id'],$label['id'],$p['tenant'],$binding['id']]);
            Catalog::audit($p,'external.label_created',$label['id'],['revision_id'=>$preview['revision_id'],'fields'=>$preview['fields'],'source_url'=>$preview['source_url'],'licenses'=>$preview['licenses']]);
            return ['id'=>$label['id'],'state'=>'draft','revision_id'=>$preview['revision_id']];
        });
    }

    public static function compare(string $gtin): array
    {
        $product=Db::one('SELECT id,current_revision_id FROM external_products WHERE gtin14=? AND provider_id=?',[$gtin,OpenFoodFacts::PROVIDER]);
        if (!$product||!$product['current_revision_id'])return ['code'=>'no_revision'];
        $record=ExternalCatalog::revision($product['current_revision_id'],true);$candidate=self::candidate($record);$count=0;
        $bindings=Db::all("SELECT b.*,t.current_info_id FROM external_product_bindings b JOIN trade_items t ON t.id=b.trade_item_id AND t.tenant_id=b.tenant_id WHERE b.external_product_id=? AND b.state='active' AND (b.last_seen_revision_id IS NULL OR b.last_seen_revision_id<>?) ORDER BY b.id LIMIT 50",[$product['id'],$product['current_revision_id']]);
        foreach($bindings as $binding) {
            if($binding['current_info_id']){
                $label=Db::one('SELECT snapshot_json FROM product_info_versions WHERE id=? AND tenant_id=? AND trade_item_id=?',[$binding['current_info_id'],$binding['tenant_id'],$binding['trade_item_id']]);
                $base=$label?json_decode($label['snapshot_json'],true,40,JSON_THROW_ON_ERROR):[];$diff=[];
                foreach($candidate as$field=>$value)if($value!==null&&$value!==($base[$field]??null))$diff[$field]=['local'=>$base[$field]??null,'external'=>$value];
                if($diff){
                    $fingerprint=hash('sha256',Db::json([$binding['current_info_id'],$record['id'],$diff]));
                    Db::run("INSERT IGNORE INTO external_conflicts (id,tenant_id,binding_id,external_product_id,trade_item_id,external_revision_id,local_label_id,severity,fingerprint,diff_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)",[Db::id(),$binding['tenant_id'],$binding['id'],$product['id'],$binding['trade_item_id'],$record['id'],$binding['current_info_id'],array_intersect(array_keys($diff),['ingredients','allergens','nutrition'])?'important':'informational',$fingerprint,Db::json($diff),Db::now()]);$count++;
                }
            }
            Db::run('UPDATE external_product_bindings SET last_seen_revision_id=? WHERE id=?',[$record['id'],$binding['id']]);
        }
        return ['code'=>'compared','count'=>$count,'more'=>count($bindings)===50];
    }

    public static function conflicts(array $p): array
    {
        ExternalCatalog::required();[$limit,$cursor]=Api::page();
        $rows=Db::all('SELECT * FROM external_conflicts WHERE tenant_id=? AND id>? ORDER BY id LIMIT '.($limit+1),[$p['tenant'],$cursor]);
        return Api::collection($rows,$limit,static function($row){$row['differences']=json_decode($row['diff_json'],true);unset($row['diff_json']);return $row;});
    }

    public static function decide(array $p, string $id, array $data): array
    {
        Auth::approve($p);Api::id($id);
        return Db::atomic(static function () use ($p,$id,$data): array {
            $row=Db::one('SELECT * FROM external_conflicts WHERE id=? AND tenant_id=? FOR UPDATE',[$id,$p['tenant']]);
            if(!$row)throw new Problem(404,'external_conflict','Diferența nu a fost găsită.');
            if($row['state']!=='open')throw new Problem(409,'external_conflict','Diferența are deja o decizie.');
            $state=$data['state']??null;if(!in_array($state,['accepted','rejected','different_market'],true))throw new Problem(422,'external_decision','Decizie incorectă.');
            $reason=Catalog::text($data,'reason',1500);$item=self::item($p,$row['trade_item_id']);
            if($item['current_info_id']!==$row['local_label_id'])throw new Problem(409,'external_stale','Articolul a fost actualizat. Compară versiunea nouă.');
            $result=['id'=>$id,'state'=>$state];
            if($state==='accepted')$result['draft']=self::draft($p,$row['trade_item_id'],array_replace($data,['revision_id'=>$row['external_revision_id'],'expected_label_id'=>$row['local_label_id']]));
            Db::run('UPDATE external_conflicts SET state=?,decision=?,decided_by=?,decided_at=? WHERE tenant_id=? AND id=?',[$state,$reason,$p['user'],Db::now(),$p['tenant'],$id]);
            Catalog::audit($p,'external.conflict_decided',$id,['state'=>$state,'reason'=>$reason]);return $result;
        });
    }
}
