<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Config, Db, Problem};

/** Bounded, resumable NDJSON artifacts. All filesystem keys are generated internally. */
final class ExternalDatasets
{
    public static function path(string $key): string
    {
        if (!preg_match('~\A(?:assets|archives|runs)/[a-f0-9]{32}\.(?:json|ndjson|jpg|png|webp)\z~D',$key)) throw new Problem(422,'external_path','Referință de fișier incorectă.');
        $base=rtrim((string)Config::get('storage'),'/\\').'/private/external';
        foreach ([$base,$base.'/'.explode('/',$key)[0]] as $dir) {
            if (is_link($dir)||(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))) throw new Problem(503,'external_storage','Stocarea externă nu este disponibilă.');
        }
        $path=$base.'/'.$key;
        if (is_link($path)) throw new Problem(503,'external_storage','Referința de stocare nu este permisă.');
        return $path;
    }

    public static function write(string $folder, string $bytes, string $extension='json'): string
    {
        $key=$folder.'/'.Db::id().'.'.$extension;$path=self::path($key);
        return Db::atomic(static function () use ($key,$path,$bytes): string {
            $row=Db::one('SELECT storage_bytes FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);
            if ((int)$row['storage_bytes']+strlen($bytes)>(int)ExternalCatalog::settings()['max_storage_bytes']) throw new Problem(503,'external_storage_quota','Cota de fișiere externe este ocupată.');
            Db::onRollback(static fn()=>is_file($path)?unlink($path):null);
            if (file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)) throw new Problem(503,'external_storage','Fișierul nu a putut fi păstrat.');
            chmod($path,0600);Db::run('UPDATE external_providers SET storage_bytes=storage_bytes+? WHERE id=?',[strlen($bytes),OpenFoodFacts::PROVIDER]);
            return $key;
        });
    }

    public static function readArchive(string $key): string
    {
        $path=self::path($key);
        if (!is_file($path)||filesize($path)>262144) throw new Problem(503,'external_archive','Arhiva nu este disponibilă.');
        $bytes=file_get_contents($path);
        if ($bytes===false) throw new Problem(503,'external_archive','Arhiva nu poate fi citită.');
        return $bytes;
    }

    public static function maintain(): array
    {
        ExternalCatalog::required();$archived=0;
        // Immutable referenced snapshots are moved, never discarded. Verify before compacting DB.
        foreach(Db::all("SELECT r.* FROM external_product_revisions r JOIN external_products p ON p.id=r.external_product_id WHERE r.id<>p.current_revision_id AND r.normalized_json IS NOT NULL AND r.fetched_at<? ORDER BY r.fetched_at LIMIT 5",[gmdate('Y-m-d H:i:s',time()-30*86400)])as$row){
            Db::atomic(static function()use($row,&$archived):void{
                Db::one('SELECT id FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);
                $current=Db::one('SELECT normalized_json FROM external_product_revisions WHERE id=? FOR UPDATE',[$row['id']]);if($current['normalized_json']===null)return;
                $key=self::write('archives',$current['normalized_json']);
                if(!hash_equals($row['normalized_sha256'],hash('sha256',self::readArchive($key))))throw new Problem(503,'external_archive','Verificarea arhivei a eșuat.');
                Db::run('UPDATE external_product_revisions SET normalized_json=NULL,normalized_archive_key=? WHERE id=?',[$key,$row['id']]);
                Db::run('UPDATE external_providers SET data_bytes=GREATEST(0,data_bytes-?) WHERE id=?',[strlen($current['normalized_json']),OpenFoodFacts::PROVIDER]);$archived++;
            });
        }
        Db::atomic(static function():void{
            Db::one('SELECT id FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);
            $rows=Db::all('SELECT id,OCTET_LENGTH(raw_json) AS bytes FROM external_product_revisions WHERE raw_json IS NOT NULL AND fetched_at<? LIMIT 100',[gmdate('Y-m-d H:i:s',time()-30*86400)]);
            foreach($rows as$row)Db::run('UPDATE external_product_revisions SET raw_json=NULL WHERE id=?',[$row['id']]);
            Db::run('UPDATE external_providers SET data_bytes=GREATEST(0,data_bytes-?) WHERE id=?',[array_sum(array_column($rows,'bytes')),OpenFoodFacts::PROVIDER]);
        });
        Db::run("DELETE FROM external_jobs WHERE state IN ('completed','dead') AND finished_at<? LIMIT 100",[gmdate('Y-m-d H:i:s',time()-30*86400)]);
        return ['archived'=>$archived];
    }

    public static function prepare(array $data, bool $dryRun=true): array
    {
        ExternalCatalog::required();$manifest=$data['manifest']??null;$encoded=$data['content_base64']??null;
        if (!is_array($manifest)||($manifest['provider']??null)!==OpenFoodFacts::PROVIDER||($manifest['license']??null)!=='ODbL-1.0'||!is_string($encoded)||strlen($encoded)>11500000) throw new Problem(422,'external_manifest','Manifestul și conținutul NDJSON sunt necesare.');
        if(($manifest['source_environment']??ExternalCatalog::settings()['source_environment'])!==ExternalCatalog::settings()['source_environment'])throw new Problem(422,'external_environment','Pachetul provine din alt mediu OFF. Folosește un catalog configurat pentru mediul acelui export.');
        $bytes=base64_decode($encoded,true);
        if ($bytes===false||strlen($bytes)>8388608||!hash_equals((string)($manifest['sha256']??''),hash('sha256',$bytes))) throw new Problem(422,'external_manifest','Amprenta pachetului nu corespunde.');
        $count=0;
        foreach (explode("\n",$bytes) as $line) {
            if ($line==='') continue;
            if (strlen($line)>262144||++$count>10000) throw new Problem(413,'external_dataset','Pachetul depășește limitele acceptate.');
            try {$product=json_decode($line,true,40,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(422,'external_dataset','Pachetul conține un rând JSON incorect.');}
            if (!is_array($product)) throw new Problem(422,'external_dataset','Înregistrare incorectă.');
            ExternalFood::normalize($product,OpenFoodFacts::gtin((string)($product['code']??'')));
        }
        if (!$count||($manifest['record_count']??null)!==$count) throw new Problem(422,'external_manifest','Numărul înregistrărilor nu corespunde.');
        if ($dryRun) return ['writes'=>false,'record_count'=>$count,'sha256'=>$manifest['sha256']];
        $key=self::write('runs',$bytes,'ndjson');$id=Db::id();
        Db::insert('external_dataset_runs',['id'=>$id,'provider_id'=>OpenFoodFacts::PROVIDER,'kind'=>'import','state'=>'queued','manifest_json'=>Db::json($manifest),'manifest_sha256'=>hash('sha256',Db::json($manifest)),'artifact_key'=>$key,'record_count'=>$count,'started_at'=>Db::now()]);
        return ['id'=>$id,'state'=>'queued','record_count'=>$count];
    }

    public static function export(): array
    {
        ExternalCatalog::required();$ids=array_column(Db::all("SELECT r.id FROM external_product_revisions r JOIN external_products p ON p.id=r.external_product_id WHERE r.visibility='public' AND p.availability<>'withdrawn' ORDER BY r.id LIMIT 100001"),'id');
        if (count($ids)>100000) throw new Problem(413,'external_export','Exportul necesită împărțirea în pachete.');
        $manifest=['schema_version'=>'openaliment.external-dataset.v1','provider'=>OpenFoodFacts::PROVIDER,'source_environment'=>ExternalCatalog::provider()['source_environment'],'license'=>'ODbL-1.0','licenses'=>OpenFoodFacts::licenses(),'revision_ids'=>$ids,'created_at'=>gmdate(DATE_ATOM)];
        $key=self::write('runs',Db::json(['manifest'=>array_diff_key($manifest,['revision_ids'=>true])])."\n",'ndjson');$id=Db::id();
        Db::insert('external_dataset_runs',['id'=>$id,'provider_id'=>OpenFoodFacts::PROVIDER,'kind'=>'export','state'=>'queued','manifest_json'=>Db::json($manifest),'manifest_sha256'=>hash('sha256',Db::json($manifest)),'artifact_key'=>$key,'record_count'=>count($ids),'started_at'=>Db::now()]);
        return ['id'=>$id,'state'=>'queued'];
    }

    public static function step(string $id): array
    {
        $lock=Db::one('SELECT GET_LOCK(?,0) AS acquired',['oa_external_dataset_'.$id]);
        if (!$lock['acquired']) return ['state'=>'busy'];
        try {
            $run=Db::one('SELECT * FROM external_dataset_runs WHERE id=?',[$id]);
            if (!$run||!in_array($run['state'],['queued','running'],true)) return ['state'=>'idle'];
            $path=self::path($run['artifact_key']);$manifest=json_decode($run['manifest_json'],true,40,JSON_THROW_ON_ERROR);
            if (!hash_equals($run['manifest_sha256'],hash('sha256',Db::json($manifest)))) throw new Problem(422,'external_manifest','Manifestul păstrat a fost modificat.');
            $count=0;$offset=(int)$run['byte_offset'];$accepted=(int)$run['accepted_count'];
            if ($run['kind']==='import') {
                if (!hash_equals($manifest['sha256'],hash_file('sha256',$path))) throw new Problem(422,'external_manifest','Conținutul pachetului a fost modificat.');
                $stream=fopen($path,'rb');fseek($stream,$offset);
                try {
                    while ($count++<10 && ($line=fgets($stream,262146))!==false) {
                        if (trim($line)==='')continue;
                        $product=json_decode($line,true,40,JSON_THROW_ON_ERROR);
                        ExternalCatalog::ingest($product,OpenFoodFacts::gtin($product['code']),false);$accepted++;
                    }
                    $offset=ftell($stream);$done=feof($stream);
                }finally{fclose($stream);}
            } else {
                $stream=fopen($path,'r+b');
                if (!$stream||!flock($stream,LOCK_EX|LOCK_NB)) throw new Problem(409,'external_export_busy','Exportul este deja procesat.');
                try {
                    $base=$offset?:filesize($path);ftruncate($stream,$base);fseek($stream,$base);
                    $chunk='';
                    foreach (array_slice($manifest['revision_ids'],$accepted,10) as $revision) {
                        $record=ExternalCatalog::revision($revision,true);
                        foreach($record['images']as&$image)unset($image['local_url']);unset($image);
                        $chunk.=Db::json($record)."\n";
                    }
                    $total=count($manifest['revision_ids']);
                    if($accepted+10>=$total&&($accepted<$total||(int)$run['byte_offset']===0)){
                        $digest=hash_init('sha256');rewind($stream);while(!feof($stream)){$part=fread($stream,65536);if($part===false)throw new Problem(503,'external_export','Exportul nu poate fi verificat.');hash_update($digest,$part);}fseek($stream,$base);hash_update($digest,$chunk);
                        $chunk.=Db::json(['end'=>true,'records'=>$total,'sha256'=>hash_final($digest)])."\n";
                    }
                    Db::atomic(static function () use ($stream,$chunk,$accepted,$manifest,$id,$base): void {
                        $quota=Db::one('SELECT storage_bytes FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);
                        $state=Db::one('SELECT state FROM external_dataset_runs WHERE id=? FOR UPDATE',[$id]);if(!in_array($state['state'],['queued','running'],true))throw new Problem(409,'external_dataset_invalidated','Exportul a fost retras. Pregătește un export nou.');
                        if ((int)$quota['storage_bytes']+strlen($chunk)>(int)ExternalCatalog::settings()['max_storage_bytes'])throw new Problem(503,'external_storage_quota','Cota de fișiere externe este ocupată.');
                        Db::onRollback(static function () use ($stream,$base): void {ftruncate($stream,$base);});
                        if(fwrite($stream,$chunk)!==strlen($chunk))throw new Problem(503,'external_storage','Exportul nu a putut fi păstrat.');
                        fflush($stream);$n=min(count($manifest['revision_ids']),$accepted+10);
                        Db::run('UPDATE external_providers SET storage_bytes=storage_bytes+? WHERE id=?',[strlen($chunk),OpenFoodFacts::PROVIDER]);
                        Db::run('UPDATE external_dataset_runs SET accepted_count=?,byte_offset=? WHERE id=?',[$n,$base+strlen($chunk),$id]);
                    });
                    $accepted=min(count($manifest['revision_ids']),$accepted+10);$offset=ftell($stream);$done=$accepted===count($manifest['revision_ids']);
                }finally{flock($stream,LOCK_UN);fclose($stream);}
            }
            Db::run("UPDATE external_dataset_runs SET state=?,byte_offset=?,accepted_count=?,finished_at=?,result_json=? WHERE id=? AND state IN ('queued','running')",[$done?'completed':'running',$offset,$accepted,$done?Db::now():null,$done?Db::json(['sha256'=>hash_file('sha256',$path)]):null,$id]);
            return ['id'=>$id,'state'=>Db::one('SELECT state FROM external_dataset_runs WHERE id=?',[$id])['state'],'accepted_count'=>$accepted];
        } catch (\Throwable $error) {
            Db::run("UPDATE external_dataset_runs SET state='failed',result_json=? WHERE id=? AND state IN ('queued','running')",[Db::json(['code'=>$error instanceof Problem?$error->errorCode:'dataset_failed']),$id]);throw $error;
        } finally { Db::run('SELECT RELEASE_LOCK(?)',['oa_external_dataset_'.$id]); }
    }

    public static function retry(string $id): array
    {
        \App\Core\Api::id($id);ExternalCatalog::required();
        $run=Db::one('SELECT id,state FROM external_dataset_runs WHERE id=?',[$id]);
        if(!$run)throw new Problem(404,'external_dataset','Pachetul nu a fost găsit.');
        if($run['state']!=='failed')throw new Problem(409,'external_dataset','Numai un pachet eșuat poate fi reluat.');
        Db::run("UPDATE external_dataset_runs SET state='queued',result_json=NULL,finished_at=NULL WHERE id=?",[$id]);
        return ['id'=>$id,'state'=>'queued'];
    }

    public static function download(): never
    {
        if (!ExternalCatalog::available())throw new Problem(404,'external_export','Catalogul nu este public.');
        $row=Db::one("SELECT artifact_key,result_json FROM external_dataset_runs WHERE kind='export' AND state='completed' ORDER BY finished_at DESC,id DESC LIMIT 1");
        if (!$row) throw new Problem(404,'external_export','Nu există încă un export complet. Fișele normalizate sunt descărcabile prin API-ul public.');
        $path=self::path($row['artifact_key']);$result=json_decode($row['result_json'],true);
        if(!is_file($path)||!hash_equals($result['sha256'],hash_file('sha256',$path)))throw new Problem(503,'external_export','Exportul nu poate fi verificat momentan.');
        header('Content-Type: application/x-ndjson; charset=utf-8');header('Content-Disposition: attachment; filename="openaliment-openfoodfacts.ndjson"');header('Link: <https://opendatacommons.org/licenses/odbl/1-0/>; rel="license"');header('Cache-Control: public, max-age=300');
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD') readfile($path);exit;
    }
}
