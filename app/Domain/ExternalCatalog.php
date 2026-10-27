<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Config, Db, Migrations, Problem};

final class ExternalCatalog
{
    public static function settings(): array
    {
        $staging=parse_url((string)Config::get('url'),PHP_URL_HOST)==='dev.openaliment.ro';
        $values=Config::get('external');
        if ($values===null && Config::get('environment')!=='test' && is_file(ROOT.'/config/external.local.php')) $values=require ROOT.'/config/external.local.php';
        $out=array_replace(['network_enabled'=>true,'source_environment'=>($staging||Config::get('environment')==='test')?'staging':'production','product_reads_per_minute'=>$staging?2:8,'max_products'=>10000,'max_database_bytes'=>209715200,'max_storage_bytes'=>524288000,'max_active_jobs'=>500,'fresh_hours'=>24,'stale_days'=>7,'sensitive_days'=>30,'contact'=>'alex@patrut.com','remote_write_enabled'=>false],is_array($values)?$values:[]);
        $out['product_reads_per_minute']=max(1,min($staging?2:8,(int)$out['product_reads_per_minute']));
        $out['source_environment']=in_array($out['source_environment'],['staging','production'],true)?$out['source_environment']:'staging';
        $out['contact']=filter_var($out['contact'],FILTER_VALIDATE_EMAIL)?$out['contact']: (preg_match('~\Ahttps://[A-Za-z0-9./_-]+\z~D',(string)$out['contact'])?$out['contact']:'https://openaliment.ro');
        return $out;
    }

    public static function seed(): void
    {
        Db::run('INSERT IGNORE INTO external_providers (id,name,api_version,mapping_version,source_environment,dataset_license_json,updated_at) VALUES (?,?,?,?,?,?,?)',[OpenFoodFacts::PROVIDER,'Open Food Facts',OpenFoodFacts::API,OpenFoodFacts::MAPPING,self::settings()['source_environment'],Db::json(OpenFoodFacts::licenses()),Db::now()]);
        foreach (['product_read','image','search','write'] as $bucket) Db::run('INSERT IGNORE INTO external_provider_limits (provider_id,bucket,updated_at) VALUES (?,?,?)',[OpenFoodFacts::PROVIDER,$bucket,Db::now()]);
    }

    public static function provider(): ?array
    {
        return Migrations::external()?Db::one('SELECT * FROM external_providers WHERE id=?',[OpenFoodFacts::PROVIDER]):null;
    }

    public static function available(): bool
    {
        $provider=self::provider();
        return $provider && $provider['read_state']==='public' && $provider['policy_reviewed_at']!==null;
    }

    public static function required(): void
    {
        if (!Migrations::external()) throw new Problem(503,'external_setup','Catalogul extern nu este activat.');
    }

    public static function sources(): array
    {
        return ['schema_version'=>'openaliment.external-sources.v1','items'=>self::available()?[['id'=>OpenFoodFacts::PROVIDER,'name'=>'Open Food Facts','api_version'=>OpenFoodFacts::API,'mapping_version'=>OpenFoodFacts::MAPPING,'source_environment'=>self::provider()['source_environment'],'licenses'=>OpenFoodFacts::licenses(),'write_available'=>false,'source_url'=>'https://world.openfoodfacts.org','attribution_url'=>url('/surse/open-food-facts')]]:[]];
    }

    public static function revisionIndex(): array
    {
        if(!self::available())throw new Problem(404,'external_unavailable','Catalogul nu este disponibil.');
        [$limit,$cursor]=Api::page();
        return Api::collection(Db::all("SELECT r.id,p.gtin14,r.content_sha256 AS sha256,r.fetched_at,r.mapping_version FROM external_product_revisions r JOIN external_products p ON p.id=r.external_product_id WHERE r.visibility='public' AND p.availability<>'withdrawn' AND r.id>? ORDER BY r.id LIMIT ".($limit+1),[$cursor]),$limit);
    }

    public static function state(array $actor, array $data): array
    {
        self::required();$state=$data['state']??null;
        if (!in_array($state,['disabled','paused','public'],true)) throw new Problem(422,'external_state','Stare de sursă incorectă.');
        if ($state==='public' && ($data['license_policy_confirmed']??false)!==true) throw new Problem(422,'external_policy','Confirmă atribuirea, licențele și exportul reutilizabil înainte de activare.');
        if($state==='public'&&self::provider()['source_environment']==='production'&&($data['api_usage_registered_confirmed']??false)!==true)throw new Problem(422,'external_usage_registration','Confirmă înregistrarea utilizării API-ului la Open Food Facts înainte de activarea sursei de producție.');
        Db::run('UPDATE external_providers SET read_state=?,policy_version=COALESCE(?,policy_version),policy_reviewed_at=COALESCE(?,policy_reviewed_at),updated_at=? WHERE id=?',[$state,$state==='public'?'openaliment.off-policy.v1':null,$state==='public'?Db::now():null,Db::now(),OpenFoodFacts::PROVIDER]);
        self::adminAudit($actor,'external.source_state',OpenFoodFacts::PROVIDER,['state'=>$state,'api_usage_registered_confirmed'=>($data['api_usage_registered_confirmed']??false)===true]);
        return ['state'=>$state,'remote_write_enabled'=>false];
    }

    public static function adminAudit(array $actor, string $action, string $id, array $data): void
    {
        Db::insert('external_provider_events',['id'=>Db::id(),'provider_id'=>OpenFoodFacts::PROVIDER,'actor_id'=>$actor['id'],'action'=>$action,'object_id'=>$id,'details_json'=>Db::json($data),'created_at'=>Db::now()]);
    }

    public static function ingest(array $product, string $gtin, bool $keepRaw=true, ?array $lease=null): array
    {
        self::required();$gtin=OpenFoodFacts::gtin($gtin);$normalized=ExternalFood::normalize($product,$gtin);$json=Db::json($normalized);
        $raw=$keepRaw?Db::json(array_intersect_key($product,array_flip(explode(',',OpenFoodFacts::FIELDS)))):null;
        if (strlen($json)>100000 || ($raw!==null && strlen($raw)>262144)) throw new Problem(413,'external_size','Fișa externă depășește limita acceptată.');
        $hash=hash('sha256',OpenFoodFacts::MAPPING.'|'.Db::json([$product['rev']??null,$product['schema_version']??null,$normalized]));
        return Db::atomic(static function () use ($product,$gtin,$normalized,$json,$raw,$hash,$lease): array {
            $provider=Db::one('SELECT * FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);$settings=self::settings();
            if($provider['source_environment']!==$settings['source_environment'])throw new Problem(409,'external_environment','Mediul OFF configurat diferă de cel al catalogului instalat.');
            if($lease)ExternalJobs::assertLease($lease);
            $row=Db::one('SELECT * FROM external_products WHERE provider_id=? AND gtin14=?',[OpenFoodFacts::PROVIDER,$gtin]);
            if (!$row) {
                if ((int)Db::one('SELECT COUNT(*) AS n FROM external_products WHERE provider_id=?',[OpenFoodFacts::PROVIDER])['n']>=(int)$settings['max_products']) throw new Problem(503,'external_quota','Catalogul extern a atins cota disponibilă.');
                $id=Db::id();Db::insert('external_products',['id'=>$id,'provider_id'=>OpenFoodFacts::PROVIDER,'gtin14'=>$gtin,'created_at'=>Db::now()]);$row=['id'=>$id];
            }
            $old=Db::one('SELECT id FROM external_product_revisions WHERE external_product_id=? AND content_sha256=?',[$row['id'],$hash]);
            if (!$old) {
                $bytes=strlen($json)+strlen($raw??'')+1000;
                if ((int)$provider['data_bytes']+$bytes>(int)$settings['max_database_bytes']) throw new Problem(503,'external_quota','Catalogul extern a atins cota disponibilă.');
                $old=['id'=>Db::id()];$time=static fn($value)=>is_numeric($value)&&$value>0&&$value<=time()+86400?gmdate('Y-m-d H:i:s',(int)$value):null;
                Db::insert('external_product_revisions',['id'=>$old['id'],'external_product_id'=>$row['id'],'remote_revision'=>isset($product['rev'])?(string)$product['rev']:null,'remote_schema_version'=>$product['schema_version']??1000,'source_modified_at'=>$time($product['last_modified_t']??null),'source_updated_at'=>$time($product['last_updated_t']??null),'fetched_at'=>Db::now(),'mapping_version'=>OpenFoodFacts::MAPPING,'content_sha256'=>$hash,'normalized_json'=>$json,'normalized_sha256'=>hash('sha256',$json),'provenance_json'=>Db::json($normalized['provenance']),'raw_json'=>$raw]);
                Db::run('UPDATE external_providers SET data_bytes=data_bytes+? WHERE id=?',[$bytes,OpenFoodFacts::PROVIDER]);
            }
            Db::run("UPDATE external_products SET source_code=?,availability='found',current_revision_id=?,last_fetched_at=?,last_attempt_at=?,next_refresh_at=?,failure_count=0,negative_until=NULL WHERE id=?",[$normalized['source_code'],$old['id'],Db::now(),Db::now(),gmdate('Y-m-d H:i:s',time()+(int)$settings['fresh_hours']*3600),$row['id']]);
            foreach($normalized['images'] as $image) Db::run('INSERT IGNORE INTO external_assets (id,external_product_id,source_asset_key,source_url,license_json,credit_json) VALUES (?,?,?,?,?,?)',[Db::id(),$row['id'],$image['source_asset_key'],$image['url'],Db::json($image['license']),Db::json(['credit'=>$image['credit'],'source_url'=>$normalized['source_url']])]);
            return ['id'=>$old['id'],'external_product_id'=>$row['id'],'sha256'=>$hash];
        });
    }

    public static function cached(string $gtin): ?array
    {
        if (!self::available()) return null;
        $row=Db::one("SELECT r.id FROM external_products p JOIN external_product_revisions r ON r.external_product_id=p.id AND r.id=p.current_revision_id WHERE p.provider_id=? AND p.gtin14=? AND p.availability='found' AND r.visibility='public'",[OpenFoodFacts::PROVIDER,$gtin]);
        return $row?self::revision($row['id']):null;
    }

    public static function revision(string $id, bool $private=false): array
    {
        self::required();Api::id($id);
        if (!$private && !self::available()) throw new Problem(404,'external_unavailable','Fișa externă nu este disponibilă.');
        $row=Db::one('SELECT r.*,p.gtin14,p.availability,p.last_fetched_at,p.current_revision_id FROM external_product_revisions r JOIN external_products p ON p.id=r.external_product_id WHERE r.id=?',[$id]);
        if (!$row || (!$private && ($row['visibility']!=='public'||$row['availability']==='withdrawn'))) throw new Problem(404,'external_unavailable','Fișa externă nu este disponibilă.');
        $json=$row['normalized_json'];
        if ($json===null && $row['normalized_archive_key']) $json=ExternalDatasets::readArchive($row['normalized_archive_key']);
        if (!is_string($json)) throw new Problem(503,'external_archive','Copia istorică nu poate fi citită momentan.');
        if(!hash_equals($row['normalized_sha256'],hash('sha256',$json)))throw new Problem(503,'external_archive','Integritatea fișei istorice nu poate fi confirmată.');
        $out=json_decode($json,true,40,JSON_THROW_ON_ERROR);
        foreach($out['nutrition']['sets']as&$set)$set['modifiers']=(object)$set['modifiers'];unset($set);
        $out+=['id'=>$id,'external_product_id'=>$row['external_product_id'],'revision'=>$row['remote_revision'],'sha256'=>$row['content_sha256'],'fetched_at'=>gmdate(DATE_ATOM,strtotime($row['fetched_at'].' UTC')),'source_modified_at'=>$row['source_modified_at'],'source_updated_at'=>$row['source_updated_at']];
        $observed=$row['current_revision_id']===$id?($row['last_fetched_at']??$row['fetched_at']):$row['fetched_at'];$out['observed_at']=gmdate(DATE_ATOM,strtotime($observed.' UTC'));
        $age=max(0,time()-strtotime($observed.' UTC'));$out['stale']=$age>(int)self::settings()['stale_days']*86400;$out['sensitive_usable']=$age<=(int)self::settings()['sensitive_days']*86400;
        foreach ($out['images'] as &$image) {
            $asset=Db::one('SELECT id,state FROM external_assets WHERE external_product_id=? AND source_asset_key=?',[$row['external_product_id'],$image['source_asset_key']]);
            $image['id']=$asset['id']??null;$image['local_url']=$asset&&$asset['state']==='ready'?url('/api/v2/public/external-assets/'.$asset['id']):null;
        }unset($image);
        return $out;
    }

    public static function resolve(string $code): array
    {
        $profile=ConsumerFood::lookup($code);$out=['schema_version'=>'openaliment.consumer-resolution.v1','profile'=>$profile,'supplement'=>null,'external_state'=>'not_requested','policy_version'=>'openaliment.source-selection.v1'];$gtin=$profile['gtin14']??null;
        if (!$gtin || ($profile['simulation']??false)) { $out['external_state']='not_applicable';return $out; }
        if (!self::available()) { $out['external_state']='temporarily_unavailable';return $out; }
        try { $gtin=OpenFoodFacts::gtin($gtin); } catch (Problem) { $out['external_state']='not_applicable';return $out; }
        $supplement=self::cached($gtin);if (!$supplement) return $out;
        $out['supplement']=$supplement;$out['external_state']=$supplement['stale']?'stale':'available';
        if (($profile['source']['kind']??null)==='unavailable') {
            $out['profile']['name']=$supplement['name']??$profile['name'];$out['profile']['net_quantity']=$supplement['net_quantity'];
            $out['profile']['source']=['kind'=>'external','checked_at'=>$supplement['observed_at'],'name'=>'Open Food Facts','url'=>$supplement['source_url'],'license'=>'ODbL-1.0 / DbCL-1.0','version_id'=>$supplement['id'],'version'=>$supplement['revision']??$supplement['id'],'sha256'=>$supplement['sha256']];
        }
        return $out;
    }

    public static function status(): array
    {
        if (!Migrations::external()) return ['installed'=>false,'available'=>false];
        $provider=self::provider();unset($provider['dataset_license_json']);
        return ['installed'=>true,'available'=>self::available(),'provider'=>$provider,'settings'=>array_diff_key(self::settings(),array_flip(['remote_write_enabled'])),'products'=>(int)Db::one('SELECT COUNT(*) AS n FROM external_products')['n'],'pending_jobs'=>(int)Db::one("SELECT COUNT(*) AS n FROM external_jobs WHERE state IN ('queued','retry','running')")['n'],'limits'=>Db::all('SELECT bucket,next_slot_at,cooldown_until,breaker_state FROM external_provider_limits'),'remote_write_available'=>false];
    }

    public static function visibility(string $id, array $data, array $actor): array
    {
        self::required();Api::id($id);$value=$data['visibility']??null;
        if(!in_array($value,['public','suspended'],true))throw new Problem(422,'external_visibility','Vizibilitate incorectă.');
        $reason=Catalog::text($data,'reason',1000);
        if(!Db::one('SELECT id FROM external_product_revisions WHERE id=?',[$id]))throw new Problem(404,'external_unavailable','Fișa nu a fost găsită.');
        Db::atomic(static function()use($value,$id,$actor,$reason):void{
            Db::one('SELECT id FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);
            Db::run('UPDATE external_product_revisions SET visibility=? WHERE id=?',[$value,$id]);
            Db::run("UPDATE external_dataset_runs SET state='invalidated' WHERE kind='export' AND state IN ('completed','running','queued')");
            self::adminAudit($actor,'external.visibility',$id,['visibility'=>$value,'reason'=>$reason]);
        });
        return ['id'=>$id,'visibility'=>$value];
    }
}
