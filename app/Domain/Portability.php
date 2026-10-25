<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Config,Db,Problem,Migrations};
final class Portability {
    private const TABLES=['products','trade_items','product_info_versions','recipe_versions','batches','batch_events','alerts','documents','change_proposals','url_mappings','imports'];
    private static function tables(): array { $tables=Migrations::resolver()?array_merge(self::TABLES,['resolver_links']):self::TABLES;return Migrations::integrations()?array_merge($tables,['integration_connectors','integration_mappings','integration_bindings']):$tables; }
    private static function order(string $table): string {return $table==='integration_bindings'?'connector_id,external_id':'id';}
    public static function export(array $p): array {
        Auth::approve($p,'export');$data=[];
        if((int)Db::one('SELECT COALESCE(SUM(OCTET_LENGTH(payload_json)),0) AS n FROM imports WHERE tenant_id=?',[$p['tenant']])['n']>8000000)throw new Problem(413,'export_size','Istoricul necesită exportul complet NDJSON.');
        foreach(self::tables() as$table)if((int)Db::one('SELECT COUNT(*) AS n FROM '.$table.' WHERE tenant_id=?',[$p['tenant']])['n']>10000)throw new Problem(413,'export_size','Pentru acest volum folosește exportul complet NDJSON.');
        if((int)Db::one('SELECT COALESCE(SUM(size_bytes),0) AS n FROM documents WHERE tenant_id=?',[$p['tenant']])['n']>8000000)throw new Problem(413,'export_size','Documentele necesită exportul complet NDJSON.');
        foreach(self::tables() as$table){$data[$table]=Db::all('SELECT * FROM '.$table.' WHERE tenant_id=? ORDER BY '.self::order($table),[$p['tenant']]);}
        foreach($data['documents']as&$doc){
            $path=Config::get('storage').'/private/'.$doc['storage_key'];$doc['download_url']=url('/manage/documents/'.$doc['id']);unset($doc['storage_key']);
            $doc['sources']=Migrations::readiness()?Db::all('SELECT source_sha256,details_json,created_at FROM document_provenance WHERE document_id=? ORDER BY created_at,id',[$doc['id']]):[];
            $doc['content_base64']=is_file($path)&&filesize($path)<=15000000?base64_encode(file_get_contents($path)):null;
        }unset($doc);
        // No accounts, API credentials, raw reviews, private contacts or AI prompts in a transfer export.
        $data['feedback']=Db::all("SELECT id,product_id,batch_id,product_info_version_id,kind,rating,public_text,created_at FROM feedback WHERE tenant_id=? AND status='published' ORDER BY id",[$p['tenant']]);
        $data['audit_events']=Db::all('SELECT id,action,object_id,details_json,created_at FROM audit_events WHERE tenant_id=? ORDER BY created_at,id',[$p['tenant']]);
        $json=Db::json($data);
        return ['schema_version'=>'openaliment.transfer.v1','exported_at'=>gmdate(DATE_ATOM),'source_base_url'=>Config::get('url'),'organization'=>Db::one('SELECT id,display_name FROM organizations WHERE id=?',[$p['tenant']]),'data'=>$data,'data_sha256'=>hash('sha256',$json)];
    }
    public static function stream(array $p): never {
        Auth::approve($p,'export');$org=Db::one('SELECT id,display_name FROM organizations WHERE id=?',[$p['tenant']]);
        header('Content-Type: application/x-ndjson; charset=utf-8');header('Content-Disposition: attachment; filename="openaliment-transfer.ndjson"');
        $hash=hash_init('sha256');$count=0;
        $emit=static function(array $value)use($hash):void{$line=Db::json($value)."\n";hash_update($hash,$line);echo $line;};
        $provenance=[];if(Migrations::readiness())foreach(Db::all('SELECT s.document_id,s.source_sha256,s.details_json,s.created_at FROM document_provenance s JOIN documents d ON d.id=s.document_id WHERE d.tenant_id=? ORDER BY s.created_at,s.id',[$p['tenant']])as$source){$id=$source['document_id'];unset($source['document_id']);$provenance[$id][]=$source;}
        $pdo=Db::pdo();$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->beginTransaction();
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
        try{
            $emit(['schema_version'=>'openaliment.transfer.stream.v1','source_base_url'=>Config::get('url'),'exported_at'=>gmdate(DATE_ATOM),'organization'=>$org]);
            foreach(array_merge(self::tables(),['feedback','audit_events'])as$table){
                $columns=match($table){'feedback'=>'id,product_id,batch_id,product_info_version_id,kind,rating,public_text,created_at','audit_events'=>'id,action,object_id,details_json,created_at',default=>'*'};
                $statement=Db::run('SELECT '.$columns.' FROM '.$table.' WHERE tenant_id=?'.($table==='feedback'?" AND status='published'":'').' ORDER BY '.self::order($table),[$p['tenant']]);
                while($row=$statement->fetch()){
                    if($table==='documents'){$row['sources']=$provenance[$row['id']]??[];$file=Config::get('storage').'/private/'.$row['storage_key'];unset($row['storage_key']);$row['content_base64']=is_file($file)?base64_encode(file_get_contents($file)):null;}
                    $emit(['table'=>$table,'row'=>$row]);$count++;if($count%100===0)flush();
                }$statement->closeCursor();
            }
            $pdo->commit();echo Db::json(['end'=>true,'records'=>$count,'sha256'=>hash_final($hash)])."\n";
        }finally{if($pdo->inTransaction())$pdo->rollBack();$pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);}
        exit;
    }
    public static function inspect(array $d): array {
        if(($d['schema_version']??'')!=='openaliment.transfer.v1'||!is_array($d['data']??null))throw new Problem(422,'schema','Schema de transfer nu este acceptată.');
        if(!hash_equals((string)($d['data_sha256']??''),hash('sha256',Db::json($d['data']))))throw new Problem(422,'checksum','Amprenta transferului nu corespunde.');
        foreach(['products','trade_items','product_info_versions','recipe_versions','batches','alerts','documents']as$t)if(!isset($d['data'][$t])||!is_array($d['data'][$t]))throw new Problem(422,'schema','Lipsește colecția '.$t.'.');
        foreach($d['data']['trade_items']as$item)if(!empty($item['gtin14']))DemoBread::assertCommercial(DigitalLink::gtin($item['gtin14']));
        if((!empty($d['data']['integration_connectors'])||!empty($d['data']['integration_mappings'])||!empty($d['data']['integration_bindings']))&&!Migrations::integrations())throw new Problem(422,'integration_schema','Activează conectoarele înainte de importul mapărilor.');
        $counts=[];foreach($d['data']as$k=>$rows){if(!is_array($rows)||count($rows)>10000)throw new Problem(422,'size','Colecție prea mare.');$counts[$k]=count($rows);}
        return ['schema_version'=>$d['schema_version'],'counts'=>$counts,'publication'=>'private','verification'=>'requires_reverification'];
    }
    public static function import(array $p,array $d,bool $dryRun=true): array {
        Auth::approve($p,'import');$report=self::inspect($d);if($dryRun)return $report;
        return Db::atomic(function()use($p,$d,$report){
            $hash=$d['data_sha256'];$source='transfer:'.substr(hash('sha256',(string)($d['source_base_url']??'')),0,32);
            $old=Db::one('SELECT result_json FROM imports WHERE tenant_id=? AND source_key=? AND package_sha256=?',[$p['tenant'],$source,$hash]);if($old)return json_decode($old['result_json'],true);
            $maps=[];$data=$d['data'];
            foreach(['products','trade_items','product_info_versions','recipe_versions','batches','integration_connectors','integration_mappings']as$table)if(!empty($data[$table]))ProducerQuota::count($p,$table,count($data[$table]));
            $documentBytes=0;$documentHashes=[];foreach($data['documents']as$doc){$sha=(string)($doc['sha256']??'');if(!isset($documentHashes[$sha])){$documentHashes[$sha]=true;if(!Migrations::readiness()||!Db::one('SELECT id FROM documents WHERE tenant_id=? AND sha256=? LIMIT 1',[$p['tenant'],$sha]))$documentBytes+=(int)ceil(strlen((string)($doc['content_base64']??''))*3/4);}}
            ProducerQuota::storage($p,$documentBytes,count($data['documents']));
            foreach(['products','trade_items','product_info_versions','recipe_versions','batches','documents','integration_connectors','integration_mappings']as$t){foreach($data[$t]??[]as$r){if(!is_array($r)||!preg_match('/\A[a-f0-9]{32}\z/D',(string)($r['id']??''))||isset($maps[$r['id']]))throw new Problem(422,'id','Identificatori de transfer nevalizi sau duplicați.');$maps[$r['id']]=Db::id();}}
            $map=static fn(?string $id):?string=>$id===null?null:($maps[$id]??throw new Problem(422,'reference','Referință de transfer lipsă.'));
            foreach($data['products']as$r)Db::insert('products',['id'=>$map($r['id']),'tenant_id'=>$p['tenant'],'name'=>Catalog::text($r,'name'),'category'=>Catalog::text($r,'category',80),'created_at'=>Db::now()]);
            foreach($data['trade_items']as$r){$g=$r['gtin14']?DigitalLink::gtin($r['gtin14']):null;if($g&&Db::one('SELECT trade_item_id FROM gtin_registry WHERE gtin14=?',[$g]))throw new Problem(409,'gtin_claim','Un GTIN din import este deja administrat. Este necesară clarificarea mandatului.');
                Db::insert('trade_items',['id'=>$map($r['id']),'tenant_id'=>$p['tenant'],'product_id'=>$map($r['product_id']),'name'=>Catalog::text($r,'name'),'gtin14'=>$g,'gtin_original'=>$r['gtin_original'],'gtin_status'=>'unverified','created_at'=>Db::now()]);}
            foreach($data['documents']as$r){
                $bytes=isset($r['content_base64'])?base64_decode($r['content_base64'],true):false;if($bytes===false||strlen($bytes)>15000000||!hash_equals((string)$r['sha256'],hash('sha256',$bytes)))throw new Problem(422,'document_hash','Conținutul unui document lipsește sau este deteriorat.');
                $id=$map($r['id']);if(Migrations::readiness()){$blob=DocumentBlobs::bytes($p,$bytes,(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes));$key=$blob['storage_key'];}else{$key=$p['tenant'].'/'.$id;$dir=Config::get('storage').'/private/'.$p['tenant'];if(!is_dir($dir))mkdir($dir,0700,true);$stored=Config::get('storage').'/private/'.$key;Db::onRollback(static function()use($stored){if(is_file($stored))@unlink($stored);});if(file_put_contents($stored,$bytes,LOCK_EX)!==strlen($bytes))throw new \RuntimeException('Import file write failed.');@chmod(Config::get('storage').'/private/'.$key,0600);}
                Db::insert('documents',['id'=>$id,'tenant_id'=>$p['tenant'],'product_id'=>$map($r['product_id']),'title'=>Catalog::text($r,'title'),'sha256'=>hash('sha256',$bytes),'storage_key'=>$key,'mime'=>(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes),'size_bytes'=>strlen($bytes),'license_id'=>Catalog::text($r,'license_id',80,false),'provenance_json'=>Db::json(['method'=>'transfer','source_document_id'=>$r['id'],'source_provenance'=>$r['provenance_json']??null]),'created_at'=>Db::now()]);DocumentBlobs::provenance($id,['method'=>'transfer','source_document_id'=>$r['id'],'source_provenance'=>$r['provenance_json']??null]);foreach($r['sources']??[]as$source){$details=json_decode((string)($source['details_json']??''),true,32,JSON_THROW_ON_ERROR);if(!is_array($details)||!hash_equals((string)($source['source_sha256']??''),hash('sha256',Db::json($details))))throw new Problem(422,'provenance','Proveniență cu amprentă incorectă.');DocumentBlobs::provenance($id,$details);}
            }
            foreach($data['recipe_versions']as$r){$json=(string)$r['snapshot_json'];json_decode($json,true,512,JSON_THROW_ON_ERROR);if(!hash_equals((string)$r['snapshot_sha256'],hash('sha256',$json)))throw new Problem(422,'hash','Rețetă cu amprentă incorectă.');Db::insert('recipe_versions',['id'=>$map($r['id']),'tenant_id'=>$p['tenant'],'product_id'=>$map($r['product_id']),'version_key'=>Catalog::text($r,'version_key',60),'snapshot_json'=>$json,'snapshot_sha256'=>hash('sha256',$json),'technical_status'=>'experimental','license_id'=>Catalog::text($r,'license_id',80,false),'created_at'=>Db::now()]);}
            foreach($data['product_info_versions']as$r){$json=(string)$r['snapshot_json'];if(!hash_equals((string)$r['snapshot_sha256'],hash('sha256',$json)))throw new Problem(422,'hash','Etichetă cu amprentă incorectă.');$s=Catalog::labelData(json_decode($json,true,512,JSON_THROW_ON_ERROR));$s['document_ids']=array_map($map,$s['document_ids']);$json=Db::json($s);Db::insert('product_info_versions',['id'=>$map($r['id']),'tenant_id'=>$p['tenant'],'product_id'=>$map($r['product_id']),'trade_item_id'=>$map($r['trade_item_id']),'version_key'=>Catalog::text($r,'version_key',60),'snapshot_json'=>$json,'snapshot_sha256'=>hash('sha256',$json),'created_at'=>Db::now()]);}
            foreach($data['batches']as$r){$json=(string)$r['snapshot_json'];if(!hash_equals((string)$r['snapshot_sha256'],hash('sha256',$json)))throw new Problem(422,'hash','Lot cu amprentă incorectă.');$decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);if(!is_array($decoded))throw new Problem(422,'batch_snapshot','Snapshotul lotului trebuie să fie un obiect.');$json=Db::json(Catalog::batchData($decoded));if(Catalog::text($r,'lot_code',120)!==$r['lot_code'])throw new Problem(422,'lot_code','Lotul importat nu poate fi normalizat.');Db::insert('batches',['id'=>$map($r['id']),'tenant_id'=>$p['tenant'],'product_id'=>$map($r['product_id']),'trade_item_id'=>$map($r['trade_item_id']),'product_info_version_id'=>$map($r['product_info_version_id']),'recipe_version_id'=>$map($r['recipe_version_id']),'lot_code'=>Catalog::text($r,'lot_code',120),'state'=>in_array($r['state'],['planned','in_production','on_hold','released','recalled','destroyed'],true)?$r['state']:'on_hold','snapshot_json'=>$json,'snapshot_sha256'=>hash('sha256',$json),'created_at'=>Db::now()]);}
            foreach($data['alerts']as$r){Db::insert('alerts',['id'=>Db::id(),'tenant_id'=>$p['tenant'],'batch_id'=>$map($r['batch_id']),'message'=>Catalog::text($r,'message',3000),'created_by'=>$p['user'],'created_at'=>$r['created_at'],'resolved_at'=>$r['resolved_at'],'resolution'=>$r['resolution']??null]);}
            if(!empty($data['resolver_links'])){
                if(!Migrations::resolver())throw new Problem(422,'resolver_schema','Instalează actualizarea resolverului înainte de importarea destinațiilor.');
                $linkKeys=[];
                foreach($data['resolver_links']as$r){
                    $scope=ResolverRegistry::qualifiers(json_decode((string)$r['qualifiers_json'],true,16,JSON_THROW_ON_ERROR));
                    $target=ResolverRegistry::target(json_decode((string)$r['target_json'],true,16,JSON_THROW_ON_ERROR));
                    $key=(string)$r['link_key'];if(!preg_match('/\A[a-f0-9]{32}\z/D',$key))throw new Problem(422,'resolver_reference','Referință de resolver incorectă.');
                    $linkKeys[$key]??=Db::id();
                    Db::insert('resolver_links',['id'=>Db::id(),'tenant_id'=>$p['tenant'],'trade_item_id'=>$map($r['trade_item_id']),'link_key'=>$linkKeys[$key],'version_no'=>max(1,(int)$r['version_no']),'scope_sha256'=>ResolverUri::scopeHash($scope),'qualifiers_json'=>Db::json((object)$scope),'target_json'=>Db::json($target),'is_default'=>(int)in_array(ResolverRegistry::NS.'defaultLink',$target['types'],true),'state'=>'draft','is_current'=>0,'created_by'=>$p['user'],'created_at'=>Db::now(),'decision'=>'Import privat: drepturile și publicarea necesită reverificare.']);
                }
            }
            foreach($data['integration_connectors']??[]as$r){if(!in_array($r['kind']??null,['catalog','retail_receipt'],true))throw new Problem(422,'connector_kind','Conector importat incorect.');Db::insert('integration_connectors',['id'=>$map($r['id']),'tenant_id'=>$p['tenant'],'name'=>Catalog::text($r,'name',180),'kind'=>$r['kind'],'created_at'=>Db::now()]);}
            foreach($data['integration_mappings']??[]as$r){$json=(string)($r['mapping_json']??'');if(!hash_equals((string)($r['sha256']??''),hash('sha256',$json)))throw new Problem(422,'mapping_hash','Mapare cu amprentă incorectă.');$mapping=PartnerMapping::validate(json_decode($json,true,32,JSON_THROW_ON_ERROR));$json=Db::json($mapping);Db::insert('integration_mappings',['id'=>$map($r['id']),'tenant_id'=>$p['tenant'],'connector_id'=>$map($r['connector_id']),'version_key'=>Catalog::text($r,'version_key',60),'mapping_json'=>$json,'sha256'=>hash('sha256',$json),'created_at'=>Db::now()]);}
            foreach($data['integration_bindings']??[]as$r)Db::insert('integration_bindings',['tenant_id'=>$p['tenant'],'connector_id'=>$map($r['connector_id']),'external_id'=>Catalog::text($r,'external_id',120),'product_id'=>$map($r['product_id']),'trade_item_id'=>$map($r['trade_item_id'])]);
            foreach($data['batch_events']??[]as$r)Catalog::event($p,$map($r['batch_id']),'imported_event',['source_event'=>$r]);
            foreach($data['batches']as$r)Db::insert('url_mappings',['id'=>Db::id(),'tenant_id'=>$p['tenant'],'source_url'=>rtrim((string)$d['source_base_url'],'/').'/b/'.$r['id'],'local_object_id'=>$map($r['id']),'created_at'=>Db::now()]);
            $report['id_map']=$maps?:new \stdClass();$report['history_preserved_in_import']=true;
            Db::insert('imports',['id'=>Db::id(),'tenant_id'=>$p['tenant'],'source_key'=>$source,'package_sha256'=>$hash,'payload_json'=>Db::json(self::historyPayload($d)),'result_json'=>Db::json($report),'created_at'=>Db::now()]);Catalog::audit($p,'transfer.imported',Db::id(),['sha256'=>$hash]);return $report;
        });
    }
    public static function pilot(array $p,bool $dryRun=true): array {
        Auth::approve($p,'import');
        $root = Config::get('handover_path');
        if (!is_string($root) || !is_readable($root . '/HASHES.sha256')) {
            throw new Problem(422, 'pilot_package', 'Pachetul pilot PBT nu este disponibil pentru import.');
        }
        $manifest=file($root.'/HASHES.sha256',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);if(!$manifest)throw new Problem(422,'manifest','Manifest indisponibil.');
        foreach($manifest as$line){if(!preg_match('/\A([a-fA-F0-9]{64})\s+\*?(.+)\z/D',$line,$m))throw new Problem(422,'manifest','Manifest invalid.');$rel=str_replace('\\','/',$m[2]);if(str_contains($rel,'..')||str_starts_with($rel,'/')||str_contains($rel,':'))throw new Problem(422,'manifest','Cale invalidă.');if(!is_file($root.'/'.$rel)||!hash_equals(strtolower($m[1]),hash_file('sha256',$root.'/'.$rel)))throw new Problem(422,'manifest','Integritatea pachetului nu corespunde.');}
        $read=static fn($file)=>json_decode(file_get_contents($root.'/'.$file),true,512,JSON_THROW_ON_ERROR);
        $seed=$read('04_import/product_pbt.json');$recipe=$read('04_import/recipe_r1_general.json');$cost=$read('04_import/cost3_scenarios.json');$catalog=$read('04_import/document_catalog.json');
        if($seed['schema_version']!=='openbread.product_seed.v0.1')throw new Problem(422,'schema','Seed istoric nerecunoscut.');
        $hash=hash_file('sha256',$root.'/HASHES.sha256');$report=['name'=>$seed['name'],'visibility'=>'private','technical_status'=>'experimental','recipe_identity'=>'pbt:R1','nominal_input_mass_g'=>3790,'originals'=>18,'recovered'=>7,'quarantine'=>'PBT-SRC-06','cost'=>'COST3 estimated','manifest_sha256'=>$hash];if($dryRun)return $report;
        return Db::atomic(function()use($p,$root,$seed,$recipe,$cost,$catalog,$hash,$report,$read){
            $old=Db::one("SELECT result_json FROM imports WHERE tenant_id=? AND source_key='pbt:R1' AND package_sha256=?",[$p['tenant'],$hash]);if($old)return json_decode($old['result_json'],true);
            if(Db::one("SELECT id FROM products WHERE tenant_id=? AND source_key='pbt'",[$p['tenant']]))throw new Problem(409,'pilot_revision','Pilotul există. Un pachet schimbat necesită o revizie explicită.');
            ProducerQuota::count($p,'products');ProducerQuota::count($p,'recipe_versions');$pid=Db::id();Db::insert('products',['id'=>$pid,'tenant_id'=>$p['tenant'],'name'=>$seed['name'],'category'=>'bread','source_key'=>'pbt','created_at'=>Db::now()]);$json=Db::json($recipe);$rid=Db::id();Db::insert('recipe_versions',['id'=>$rid,'tenant_id'=>$p['tenant'],'product_id'=>$pid,'version_key'=>'R1','snapshot_json'=>$json,'snapshot_sha256'=>hash('sha256',$json),'created_at'=>Db::now()]);
            // Keep both representations, cost models, provenance, original submission and research as source data, not a second R1.
            $payload=['seed'=>$seed,'recipe_r1'=>$read('04_import/recipe_r1.json'),'recipe_general'=>$recipe,'cost3'=>$cost,'catalog'=>$catalog,'submission'=>$read('04_import/submission_pbt_draft.json'),'field_mapping'=>$read('04_import/field_mapping.json')];
            $dir=Config::get('storage').'/private/'.$p['tenant'];if(!is_dir($dir))mkdir($dir,0700,true);
            foreach(['01_originale','02_extrase_text','03_bt01_recuperat']as$folder){
                $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$folder,\FilesystemIterator::SKIP_DOTS));
                foreach($it as$file)if($file->isFile()&&!$file->isLink())self::pilotDocument($p,$pid,$root,$file);
            }
            $report['product_id']=$pid;$report['recipe_id']=$rid;Db::insert('imports',['id'=>Db::id(),'tenant_id'=>$p['tenant'],'source_key'=>'pbt:R1','package_sha256'=>$hash,'payload_json'=>Db::json($payload),'result_json'=>Db::json($report),'created_at'=>Db::now()]);Catalog::audit($p,'pilot.imported',$pid,['manifest_sha256'=>$hash]);return $report;
        });
    }
    private static function historyPayload(array $package): array {
        foreach($package['data']['documents']??[]as$index=>$document)unset($package['data']['documents'][$index]['content_base64']);
        $package['document_bytes_preserved']='documents_by_sha256';return $package;
    }
    private static function pilotDocument(array $p,string $product,string $root,\SplFileInfo $file): void {
        $id=Db::id();$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if(Migrations::readiness()){ProducerQuota::count($p,'documents');$blob=DocumentBlobs::bytes($p,file_get_contents($file->getPathname()),$mime);$key=$blob['storage_key'];}
        else{$key=$p['tenant'].'/'.$id;ProducerQuota::storage($p,$file->getSize());$stored=Config::get('storage').'/private/'.$key;Db::onRollback(static function()use($stored){if(is_file($stored))@unlink($stored);});if(!copy($file->getPathname(),$stored))throw new \RuntimeException('Pilot file copy failed.');@chmod($stored,0600);}
        $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));$bad=$file->getFilename()==='Paine_Buna_Tehnic_BT01_Pachet_tipografie.zip';
        $provenance=['source_package'=>'openbread-handover-2026-10-23-v1','source_path'=>$relative,'integrity'=>$bad?'quarantined_corrupt_original':'preserved','catalog_ref'=>'04_import/document_catalog.json'];
        Db::insert('documents',['id'=>$id,'tenant_id'=>$p['tenant'],'product_id'=>$product,'title'=>mb_substr($file->getFilename(),0,180),'sha256'=>hash_file('sha256',$file->getPathname()),'storage_key'=>$key,'mime'=>$mime,'size_bytes'=>$file->getSize(),'provenance_json'=>Db::json($provenance),'created_at'=>Db::now()]);DocumentBlobs::provenance($id,$provenance);
    }

}
