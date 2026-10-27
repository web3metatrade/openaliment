<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Auth, Db, Http, Migrations, Problem};

/** Explicit producer contributions. Remote PATCH is gated; producer packages are usable. */
final class ExternalSharing
{
    public const FIELDS=['name','category','ingredients','allergens','net_quantity','operator','storage','nutrition','origin'];

    private static function ready(): void
    {
        if(!Migrations::externalSharing())throw new Problem(503,'external_sharing_setup','Distribuirea externă nu este activată.');
    }

    private static function owner(array $p): void
    {
        self::ready();Auth::scope($p,'sharing:manage');
        if($p['role']!=='owner')throw new Problem(403,'role','Acordul necesită proprietarul organizației.');
    }

    public static function connection(array $p): array
    {
        self::ready();$row=Db::one('SELECT id,remote_organization_ref,transport,state,verified_at,last_error_code FROM external_connections WHERE tenant_id=? AND provider_id=?',[$p['tenant'],OpenFoodFacts::PROVIDER]);
        return ['connection'=>$row,'remote_write_available'=>false,'supported_transports'=>['producer_package']];
    }

    public static function connect(array $p, array $data): array
    {
        self::owner($p);$name=Catalog::text($data,'organization_ref',180);
        if(($data['transport']??'producer_package')!=='producer_package')throw new Problem(422,'external_transport','Transportul automat necesită validarea separată cu Open Food Facts.');
        return Db::atomic(static function () use ($p,$name): array {
            Db::one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$p['tenant']]);
            $row=Db::one('SELECT id FROM external_connections WHERE tenant_id=? AND provider_id=?',[$p['tenant'],OpenFoodFacts::PROVIDER]);$id=$row['id']??Db::id();
            if($row)Db::run("UPDATE external_connections SET remote_organization_ref=?,transport='producer_package',state='active' WHERE id=? AND tenant_id=?",[$name,$id,$p['tenant']]);
            else Db::insert('external_connections',['id'=>$id,'tenant_id'=>$p['tenant'],'provider_id'=>OpenFoodFacts::PROVIDER,'remote_organization_ref'=>$name,'transport'=>'producer_package','state'=>'active','created_at'=>Db::now()]);
            Catalog::audit($p,'external.connection',$id,['transport'=>'producer_package']);return ['id'=>$id,'state'=>'active','transport'=>'producer_package','remote_write_available'=>false];
        });
    }

    public static function disconnect(array $p): array
    {
        self::owner($p);
        return Db::atomic(static function () use ($p): array {
            $row=Db::one('SELECT id FROM external_connections WHERE tenant_id=? AND provider_id=? FOR UPDATE',[$p['tenant'],OpenFoodFacts::PROVIDER]);
            if(!$row)throw new Problem(404,'external_connection','Conexiunea nu a fost găsită.');
            Db::run("UPDATE external_connections SET state='disconnected' WHERE id=?",[$row['id']]);
            Db::run("UPDATE external_publications SET state='cancelled' WHERE tenant_id=? AND connection_id=? AND state IN ('pending','retry','package_ready')",[$p['tenant'],$row['id']]);
            Catalog::audit($p,'external.connection_revoked',$row['id']);return ['id'=>$row['id'],'state'=>'disconnected'];
        });
    }

    public static function grants(array $p): array
    {
        self::ready();[$limit,$cursor]=Api::page();$rows=Db::all('SELECT * FROM external_sharing_grants WHERE tenant_id=? AND id>? ORDER BY id LIMIT '.($limit+1),[$p['tenant'],$cursor]);
        return Api::collection($rows,$limit,static function($r){foreach(['fields','asset_ids','licenses'] as$key){$r[$key]=json_decode($r[$key.'_json'],true);unset($r[$key.'_json']);}return $r;});
    }

    public static function grant(array $p, array $data): array
    {
        self::owner($p);$itemId=Catalog::text($data,'trade_item_id',32);$item=ExternalSources::item($p,$itemId);
        if($item['gtin_status']!=='verified'||!$item['verified_at'])throw new Problem(422,'external_mandate','Distribuirea necesită dreptul verificat de administrare a GTIN-ului.');
        if(($data['public_data_confirmed']??false)!==true||($data['licenses_confirmed']??false)!==true)throw new Problem(422,'external_consent','Confirmă drepturile de distribuire publică și licențele.');
        $fields=$data['fields']??[];
        if(!is_array($fields)||!array_is_list($fields)||!$fields||array_diff($fields,self::FIELDS)||count($fields)!==count(array_unique($fields)))throw new Problem(422,'external_fields','Lista câmpurilor este incorectă.');
        if(($data['mode']??'manual')!=='manual')throw new Problem(422,'external_transport','Distribuirea automată nu este validată. Folosește pachetul pentru producător.');
        $assets=$data['asset_ids']??[];
        if(!is_array($assets)||!array_is_list($assets)||count($assets)>10)throw new Problem(422,'external_assets','Lista imaginilor este incorectă.');
        foreach($assets as$id){Api::id($id);$doc=Catalog::owned('documents',$id,$p);if($doc['product_id']!==$item['product_id']||$doc['visibility']!=='public'||$doc['rights_status']!=='cleared'||!in_array($doc['mime'],['image/jpeg','image/png','image/webp'],true)||!in_array($doc['license_id'],['CC-BY-SA-3.0','CC-BY-SA-4.0','CC-BY-4.0','CC0-1.0'],true))throw new Problem(422,'external_image_rights','Selectează numai imagini publice cu drepturi și licențe compatibile.');}
        return Db::atomic(static function () use ($p,$itemId,$fields,$assets,$data): array {
            $connection=Db::one("SELECT * FROM external_connections WHERE tenant_id=? AND provider_id=? AND state='active' FOR UPDATE",[$p['tenant'],OpenFoodFacts::PROVIDER]);
            if(!$connection)throw new Problem(422,'external_connection','Configurează mai întâi conexiunea organizației.');
            $previous=self::latest($p,$itemId);
            if((int)($data['expected_version']??0)!==(int)($previous['version']??0))throw new Problem(409,'external_grant_version','Acordul s-a schimbat. Reîncarcă informațiile.');
            $id=Db::id();$version=(int)($previous['version']??0)+1;
            Db::insert('external_sharing_grants',['id'=>$id,'tenant_id'=>$p['tenant'],'connection_id'=>$connection['id'],'trade_item_id'=>$itemId,'grant_key'=>$previous['grant_key']??Db::id(),'version'=>$version,'previous_id'=>$previous['id']??null,'action'=>'grant','fields_json'=>Db::json($fields),'asset_ids_json'=>Db::json($assets),'licenses_json'=>Db::json(OpenFoodFacts::licenses()),'mode'=>'manual','actor_id'=>$p['user'],'created_at'=>Db::now()]);
            Catalog::audit($p,'external.sharing_granted',$id,['trade_item_id'=>$itemId,'version'=>$version,'fields'=>$fields]);return ['id'=>$id,'version'=>$version,'state'=>'granted'];
        });
    }

    private static function latest(array $p, string $item): ?array
    {
        return Db::one('SELECT * FROM external_sharing_grants WHERE tenant_id=? AND trade_item_id=? ORDER BY version DESC LIMIT 1',[$p['tenant'],$item]);
    }

    public static function revoke(array $p, string $id): array
    {
        self::owner($p);Api::id($id);
        return Db::atomic(static function () use ($p,$id): array {
            $grant=Db::one('SELECT * FROM external_sharing_grants WHERE id=? AND tenant_id=?',[$id,$p['tenant']]);
            if(!$grant)throw new Problem(404,'external_grant','Acordul nu a fost găsit.');
            Db::one('SELECT id FROM external_connections WHERE id=? AND tenant_id=? FOR UPDATE',[$grant['connection_id'],$p['tenant']]);
            $latest=self::latest($p,$grant['trade_item_id']);
            if($latest['id']!==$id)throw new Problem(409,'external_grant_version','Folosește ultima versiune a acordului.');
            if($latest['action']==='revoke')return ['id'=>$id,'state'=>'revoked'];
            $next=Db::id();unset($grant['id']);$grant['id']=$next;$grant['previous_id']=$id;$grant['version']=(int)$grant['version']+1;$grant['action']='revoke';$grant['actor_id']=$p['user'];$grant['created_at']=Db::now();Db::insert('external_sharing_grants',$grant);
            Db::run("UPDATE external_publications SET state='cancelled' WHERE tenant_id=? AND trade_item_id=? AND state IN ('pending','retry','package_ready')",[$p['tenant'],$grant['trade_item_id']]);
            Catalog::audit($p,'external.sharing_revoked',$next,['previous_id'=>$id]);return ['id'=>$next,'state'=>'revoked'];
        });
    }

    public static function merge(array $base, array $local, array $remote): array
    {
        $changes=[];$conflicts=[];
        foreach($local as$field=>$value){
            if(array_key_exists($field,$remote)&&$value===$remote[$field])continue;
            $baseValue=$base[$field]??null;$remoteValue=$remote[$field]??null;
            if($value===$baseValue)continue;
            if($remoteValue!==$baseValue&&$remoteValue!==null)$conflicts[$field]=['base'=>$baseValue,'local'=>$value,'remote'=>$remoteValue];
            else $changes[$field]=$value;
        }
        return ['changes'=>$changes,'conflicts'=>$conflicts];
    }

    public static function preview(array $p, string $itemId, array $data): array
    {
        self::ready();$item=ExternalSources::item($p,$itemId);Auth::approve($p,'read');
        $grant=self::latest($p,$itemId);
        if(!$grant||$grant['action']!=='grant')throw new Problem(422,'external_grant','Nu există un acord activ pentru articol.');
        $connection=Db::one("SELECT * FROM external_connections WHERE id=? AND tenant_id=? AND state='active'",[$grant['connection_id'],$p['tenant']]);
        if(!$connection||$item['gtin_status']!=='verified')throw new Problem(422,'external_mandate','Conexiunea sau mandatul nu mai permit distribuirea.');
        $labelId=$data['label_id']??$item['current_info_id'];Api::id((string)$labelId);$label=Catalog::owned('product_info_versions',$labelId,$p);
        if($label['trade_item_id']!==$itemId||$label['state']!=='published')throw new Problem(422,'external_label','Selectează o versiune publicată a articolului.');
        $snapshot=json_decode($label['snapshot_json'],true,40,JSON_THROW_ON_ERROR);$fields=json_decode($grant['fields_json'],true);
        $payload=array_intersect_key($snapshot,array_flip($fields));$payload=array_filter($payload,static fn($value)=>$value!==null&&$value!=='');
        $source=Db::one('SELECT details_json FROM audit_events WHERE tenant_id=? AND action=? AND object_id=? ORDER BY created_at DESC,id DESC LIMIT 1',[$p['tenant'],'external.label_created',$labelId]);
        $record=ExternalCatalog::cached($item['gtin14']);$remote=$record?ExternalSources::candidate($record):[];
        // Imported fields are not echoed back as independent producer contributions.
        if($source){$origin=json_decode($source['details_json'],true);if(($data['independently_verified']??false)!==true)foreach($origin['fields']??[]as$field)unset($payload[$field]);}
        $previous=Db::one("SELECT payload_json FROM external_publications WHERE tenant_id=? AND trade_item_id=? AND state='confirmed' ORDER BY confirmed_at DESC,id DESC LIMIT 1",[$p['tenant'],$itemId]);
        $base=$previous?json_decode($previous['payload_json'],true)['fields']:[];$comparison=self::merge($base,$payload,$remote);$comparison['changes']=(object)$comparison['changes'];$comparison['conflicts']=(object)$comparison['conflicts'];
        return ['trade_item_id'=>$itemId,'label_id'=>$labelId,'grant_id'=>$grant['id'],'connection_id'=>$connection['id'],'gtin14'=>$item['gtin14'],'transport'=>'producer_package','fields'=>(object)$payload,'comparison'=>$comparison,'base_remote_revision'=>$record['revision']??null,'remote_write_available'=>false,'licenses'=>OpenFoodFacts::licenses(),'assets'=>self::assets($p,json_decode($grant['asset_ids_json'],true)),'writes'=>false];
    }

    public static function publication(array $p, string $itemId, array $data): array
    {
        Auth::approve($p,'sharing:write');
        return Db::atomic(static function () use ($p,$itemId,$data): array {
            self::ready();$item=ExternalSources::item($p,$itemId);
            $connection=Db::one('SELECT * FROM external_connections WHERE tenant_id=? AND provider_id=? FOR UPDATE',[$p['tenant'],OpenFoodFacts::PROVIDER]);
            if(!$connection)throw new Problem(422,'external_connection','Conexiunea nu este disponibilă.');
            $preview=self::preview($p,$itemId,$data);if(!(array)$preview['fields']&&!$preview['assets'])throw new Problem(422,'external_empty','Acordul nu include informații originale de distribuit.');$json=Db::json($preview);$hash=hash('sha256',$json);
            $old=Db::one('SELECT id,state FROM external_publications WHERE tenant_id=? AND connection_id=? AND label_id=? AND payload_sha256=?',[$p['tenant'],$connection['id'],$preview['label_id'],$hash]);if($old)return $old;
            $id=Db::id();Db::insert('external_publications',['id'=>$id,'tenant_id'=>$p['tenant'],'connection_id'=>$connection['id'],'trade_item_id'=>$itemId,'label_id'=>$preview['label_id'],'grant_id'=>$preview['grant_id'],'payload_sha256'=>$hash,'payload_json'=>$json,'base_remote_revision'=>$preview['base_remote_revision'],'state'=>'package_ready','available_at'=>Db::now(),'created_at'=>Db::now()]);
            Catalog::audit($p,'external.package_prepared',$id,['label_id'=>$preview['label_id'],'grant_id'=>$preview['grant_id']]);return ['id'=>$id,'state'=>'package_ready','remote_confirmed'=>false];
        });
    }

    public static function publications(array $p): array
    {
        self::ready();[$limit,$cursor]=Api::page();return Api::collection(Db::all('SELECT id,trade_item_id,label_id,grant_id,state,attempts,confirmed_at,created_at FROM external_publications WHERE tenant_id=? AND id>? ORDER BY id LIMIT '.($limit+1),[$p['tenant'],$cursor]),$limit);
    }

    public static function package(array $p, string $id): never
    {
        self::ready();Auth::approve($p,'export');Api::id($id);
        $row=Db::one('SELECT * FROM external_publications WHERE id=? AND tenant_id=?',[$id,$p['tenant']]);
        if(!$row||$row['state']!=='package_ready')throw new Problem(404,'external_publication','Pachetul nu este disponibil.');
        $grant=self::latest($p,$row['trade_item_id']);$connection=Db::one('SELECT state FROM external_connections WHERE id=? AND tenant_id=?',[$row['connection_id'],$p['tenant']]);$item=ExternalSources::item($p,$row['trade_item_id']);
        if(!$grant||$grant['id']!==$row['grant_id']||$grant['action']!=='grant'||($connection['state']??null)!=='active'||$item['gtin_status']!=='verified')throw new Problem(409,'external_revoked','Acordul sau mandatul nu mai permite distribuirea.');
        self::assets($p,json_decode($grant['asset_ids_json'],true));
        $files=self::packageFiles(json_decode($row['payload_json'],true));
        $path=ExternalDatasets::path('runs/'.Db::id().'.json');$zip=new \ZipArchive();
        if($zip->open($path,\ZipArchive::CREATE|\ZipArchive::EXCL)!==true)throw new Problem(503,'external_package','Pachetul nu poate fi creat momentan.');
        try{foreach($files as$name=>$bytes)if(!$zip->addFromString($name,$bytes))throw new Problem(503,'external_package','Pachetul nu poate fi creat momentan.');if(!$zip->close())throw new Problem(503,'external_package','Pachetul nu poate fi închis.');chmod($path,0600);
            header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="openfoodfacts-producer.zip"');header('Cache-Control: no-store');if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')readfile($path);
        }finally{if(is_file($path))unlink($path);}exit;
    }

    private static function assets(array $p,array $ids): array
    {
        $out=[];foreach($ids as$id){$doc=Catalog::owned('documents',$id,$p);if($doc['visibility']!=='public'||$doc['rights_status']!=='cleared'||!in_array($doc['mime'],['image/jpeg','image/png','image/webp'],true)||!in_array($doc['license_id'],['CC-BY-SA-3.0','CC-BY-SA-4.0','CC-BY-4.0','CC0-1.0'],true))throw new Problem(409,'external_image_rights','Drepturile unei imagini au fost retrase.');$out[]=['id'=>$id,'url'=>url('/d/'.$id),'sha256'=>$doc['sha256'],'mime'=>$doc['mime'],'license'=>$doc['license_id']];}return $out;
    }

    public static function packageFiles(array $payload): array
    {
        // Only documented, semantically equivalent OFF columns are mapped automatically.
        $mapping=['name'=>'product_name_ro','ingredients'=>'ingredients_text_ro','net_quantity'=>'quantity'];$csv=['code'=>$payload['gtin14']];$unmapped=[];
        foreach($payload['fields']as$key=>$value){if(!isset($mapping[$key])){$unmapped[]=$key;continue;}$text=(string)$value;if(preg_match('/\A[=+\-@\t\r]/',$text))$text="'".$text;$csv[$mapping[$key]]=$text;}
        $stream=fopen('php://temp','w+b');fputcsv($stream,array_keys($csv),',','"','');fputcsv($stream,array_values($csv),',','"','');rewind($stream);$bytes=stream_get_contents($stream);fclose($stream);
        $json=Db::json(['schema_version'=>'openaliment.off-contribution.v1','payload'=>$payload,'csv_unmapped_fields'=>$unmapped,'remote_confirmed'=>false]);
        return ['products.csv'=>$bytes,'contribution.json'=>$json,'README.txt'=>"OpenAliment → Open Food Facts\nPachet pregătit de producător. Nu a fost transmis automat.\nproducts.csv include numai code, product_name_ro, ingredients_text_ro și quantity, conform selecției.\ncontribution.json păstrează toate câmpurile aprobate, proveniența, licențele și referințele imaginilor. Câmpurile CSV nemapate necesită verificare în instrumentul de import OFF.\nAcest pachet nu înregistrează loturi, expirări sau rețete în OFF. Verificați maparea înainte de import.\n"];
    }
}
