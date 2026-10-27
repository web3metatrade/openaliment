<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Auth, Db, Problem};

/** Persistent admission, provider pacing and leased work. No network under a DB lock. */
final class ExternalJobs
{
    public static function lookup(string $input): array
    {
        if (!ExternalCatalog::available()) throw new Problem(503,'external_unavailable','Căutarea externă nu este disponibilă momentan.');
        $gtin=OpenFoodFacts::gtin($input);
        $cached=ExternalCatalog::cached($gtin);
        $product=Db::one('SELECT next_refresh_at,negative_until FROM external_products WHERE provider_id=? AND gtin14=?',[OpenFoodFacts::PROVIDER,$gtin]);
        if ($cached && $product['next_refresh_at']>Db::now()) return ['state'=>'completed','result_code'=>'found','supplement'=>$cached];
        if ($product && $product['negative_until']>Db::now()) return ['state'=>'completed','result_code'=>'not_found','supplement'=>$cached];
        $marker=hash('sha256','external-unique|'.Api::clientIp().'|'.intdiv(time(),3600).'|'.$gtin);
        Db::atomic(static function () use ($marker): void {
            $new=Db::run('INSERT IGNORE INTO rate_limits (id,hits,expires_at) VALUES (?,1,?)',[$marker,gmdate('Y-m-d H:i:s',time()+3600)])->rowCount();
            if ($new) Auth::rate('external-new|'.Api::clientIp(),20,3600);
        });
        $job=self::enqueue('product',$gtin,10);
        self::work($job['id']);
        return self::status($job['id']);
    }

    public static function enqueue(string $kind, string $gtin, int $priority=0): array
    {
        if (!in_array($kind,['product','image','compare'],true)) throw new \LogicException('Unsupported external job.');
        return Db::atomic(static function () use ($kind,$gtin,$priority): array {
            Db::one('SELECT id FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);
            $active=$kind.':'.$gtin;
            $existing=Db::one('SELECT id FROM external_jobs WHERE provider_id=? AND active_key=?',[OpenFoodFacts::PROVIDER,$active]);
            if ($existing) return $existing;
            if ((int)Db::one("SELECT COUNT(*) AS n FROM external_jobs WHERE state IN ('queued','retry','running')")['n']>=(int)ExternalCatalog::settings()['max_active_jobs']) {
                throw new Problem(429,'external_queue_full','Căutarea este ocupată. Poți adăuga alimentul și data de pe ambalaj.');
            }
            $id=Db::id();Db::insert('external_jobs',['id'=>$id,'provider_id'=>OpenFoodFacts::PROVIDER,'kind'=>$kind,'gtin14'=>$gtin,'active_key'=>$active,'priority'=>$priority,'state'=>'queued','available_at'=>Db::now(),'deadline_at'=>gmdate('Y-m-d H:i:s',time()+7200),'created_at'=>Db::now()]);
            return ['id'=>$id];
        });
    }

    public static function status(string $id): array
    {
        ExternalCatalog::required();Api::id($id);
        if (!ExternalCatalog::available()) throw new Problem(503,'external_unavailable','Sursa externă este oprită.');
        $job=Db::one('SELECT id,state,result_code,available_at,gtin14 FROM external_jobs WHERE id=?',[$id]);
        if (!$job) throw new Problem(404,'external_lookup','Căutarea nu a fost găsită.');
        $result=['id'=>$id,'state'=>$job['state'],'result_code'=>$job['result_code'],'retry_after'=>max(1,min(60,strtotime($job['available_at'].' UTC')-time())),'supplement'=>ExternalCatalog::cached($job['gtin14'])];
        return $result;
    }

    private static function claim(string $id): ?array
    {
        return Db::atomic(static function () use ($id): ?array {
            $provider=Db::one('SELECT read_state,source_environment FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);
            if (!$provider || $provider['read_state']!=='public'||$provider['source_environment']!==ExternalCatalog::settings()['source_environment']) return null;
            $job=Db::one('SELECT * FROM external_jobs WHERE id=? FOR UPDATE',[$id]);
            if (!$job || in_array($job['state'],['completed','dead','cancelled'],true) || $job['available_at']>Db::now()) return null;
            if ($job['state']==='running' && $job['lease_until']>Db::now()) return null;
            if ($job['deadline_at']<Db::now()) {
                Db::run("UPDATE external_jobs SET state='dead',active_key=NULL,result_code='deadline',finished_at=? WHERE id=?",[Db::now(),$id]);return null;
            }
            $bucket=$job['kind']==='product'?'product_read':($job['kind']==='image'?'image':null);
            if ($bucket!==null) {
                $limit=Db::one('SELECT * FROM external_provider_limits WHERE provider_id=? AND bucket=? FOR UPDATE',[OpenFoodFacts::PROVIDER,$bucket]);
                $after=max(strtotime(($limit['next_slot_at']??'1970-01-01').' UTC'),strtotime(($limit['cooldown_until']??'1970-01-01').' UTC'));
                if ($after>time()) {
                    Db::run('UPDATE external_jobs SET available_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',$after),$id]);return null;
                }
                $spacing=$bucket==='product_read'?(int)ceil(60/ExternalCatalog::settings()['product_reads_per_minute']):6;
                Db::run("UPDATE external_provider_limits SET next_slot_at=?,breaker_state='closed',updated_at=? WHERE provider_id=? AND bucket=?",[gmdate('Y-m-d H:i:s',time()+$spacing),Db::now(),OpenFoodFacts::PROVIDER,$bucket]);
            }
            $job['lease_token']=Db::id();
            Db::run("UPDATE external_jobs SET state='running',lease_token=?,lease_until=?,attempts=attempts+1 WHERE id=?",[$job['lease_token'],gmdate('Y-m-d H:i:s',time()+45),$id]);
            $job['attempts']=(int)$job['attempts']+1;
            return $job;
        });
    }

    public static function cooldown(int $seconds=60, string $bucket='product_read'): void
    {
        $until=gmdate('Y-m-d H:i:s',time()+max(30,min(86400,$seconds)));
        Db::run("UPDATE external_provider_limits SET cooldown_until=GREATEST(COALESCE(cooldown_until,?),?),breaker_state='open',consecutive_failures=consecutive_failures+1,updated_at=? WHERE provider_id=? AND bucket=?",[$until,$until,Db::now(),OpenFoodFacts::PROVIDER,$bucket]);
    }

    public static function work(string $id): array
    {
        $job=self::claim($id);if (!$job) return ['state'=>'not_claimed'];
        try {
            $result=[];
            if ($job['kind']==='product') {
                $result=OpenFoodFacts::product($job['gtin14']);
                if ($result['found']) {
                    $result=ExternalCatalog::ingest($result['product'],$job['gtin14'],true,$job);$result['code']='found';
                } else {
                    self::negative($job['gtin14'],$job);$result=['code'=>'not_found'];
                }
            } elseif ($job['kind']==='image') {
                $result=ExternalMedia::work($job['gtin14'],$job);
            } else {
                $result=ExternalSources::compare($job['gtin14']);
            }
            Db::run("UPDATE external_jobs SET state='completed',active_key=NULL,result_code=?,result_json=?,lease_token=NULL,lease_until=NULL,finished_at=? WHERE id=? AND lease_token=?",[$result['code']??'completed',Db::json($result),Db::now(),$id,$job['lease_token']]);
            if(in_array($job['kind'],['product','image'],true))Db::run("UPDATE external_provider_limits SET consecutive_failures=0 WHERE provider_id=? AND bucket=?",[OpenFoodFacts::PROVIDER,$job['kind']==='image'?'image':'product_read']);
            if ($job['kind']==='product' && ($result['code']??null)==='found') {
                foreach(['compare','image'] as $kind) try{self::enqueue($kind,$job['gtin14']);}catch(Problem){}
            }
            if (in_array($job['kind'],['image','compare'],true) && ($result['more']??false)) try{self::enqueue($job['kind'],$job['gtin14']);}catch(Problem){}
            return ['state'=>'completed'];
        } catch (Problem $error) {
            $retry=in_array($error->status,[429,502,503],true)&&$job['attempts']<5&&$error->errorCode!=='external_quota';
            if ($error->status===429) self::cooldown(60,$job['kind']==='image'?'image':'product_read');
            elseif(in_array($error->status,[502,503],true)&&in_array($job['kind'],['product','image'],true)){
                $bucket=$job['kind']==='image'?'image':'product_read';Db::run('UPDATE external_provider_limits SET consecutive_failures=consecutive_failures+1 WHERE provider_id=? AND bucket=?',[OpenFoodFacts::PROVIDER,$bucket]);
                if((int)Db::one('SELECT consecutive_failures FROM external_provider_limits WHERE provider_id=? AND bucket=?',[OpenFoodFacts::PROVIDER,$bucket])['consecutive_failures']>=3)self::cooldown(300,$bucket);
            }
            $after=gmdate('Y-m-d H:i:s',time()+min(1800,15*(2**min(6,$job['attempts'])))+random_int(0,10));
            Db::run('UPDATE external_jobs SET state=?,active_key=?,available_at=?,result_code=?,lease_token=NULL,lease_until=NULL,finished_at=? WHERE id=? AND lease_token=?',[$retry?'retry':'dead',$retry?$job['active_key']:null,$after,$error->errorCode,$retry?null:Db::now(),$id,$job['lease_token']]);
            return ['state'=>$retry?'retry':'dead','code'=>$error->errorCode];
        } catch (\Throwable $error) {
            Db::run("UPDATE external_jobs SET state='dead',active_key=NULL,result_code='worker_failed',lease_token=NULL,lease_until=NULL,finished_at=? WHERE id=? AND lease_token=?",[Db::now(),$id,$job['lease_token']]);
            throw $error;
        }
    }

    public static function assertLease(array $lease): void
    {
        if(!Db::one("SELECT id FROM external_jobs WHERE id=? AND state='running' AND lease_token=? AND lease_until>? FOR UPDATE",[$lease['id'],$lease['lease_token'],Db::now()]))throw new Problem(409,'external_lease','Operațiunea a fost preluată de alt proces.');
    }

    private static function negative(string $gtin, array $lease): void
    {
        Db::atomic(static function () use ($gtin,$lease): void {
            Db::one('SELECT id FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);
            self::assertLease($lease);
            $row=Db::one('SELECT id FROM external_products WHERE provider_id=? AND gtin14=?',[OpenFoodFacts::PROVIDER,$gtin]);
            if (!$row) {
                if ((int)Db::one('SELECT COUNT(*) AS n FROM external_products')['n']>=(int)ExternalCatalog::settings()['max_products']) return;
                $row=['id'=>Db::id()];Db::insert('external_products',['id'=>$row['id'],'provider_id'=>OpenFoodFacts::PROVIDER,'gtin14'=>$gtin,'availability'=>'not_found','created_at'=>Db::now()]);
            }
            Db::run("UPDATE external_products SET negative_until=?,next_refresh_at=?,last_attempt_at=?,availability=IF(current_revision_id IS NOT NULL AND failure_count>=2,'withdrawn',availability),failure_count=failure_count+1 WHERE id=?",[gmdate('Y-m-d H:i:s',time()+21600),gmdate('Y-m-d H:i:s',time()+21600),Db::now(),$row['id']]);
            if(Db::one('SELECT availability FROM external_products WHERE id=?',[$row['id']])['availability']==='withdrawn')Db::run("UPDATE external_dataset_runs SET state='invalidated' WHERE kind='export' AND state IN ('completed','queued','running')");
        });
    }

    public static function run(int $seconds=20): array
    {
        if (!ExternalCatalog::available()) return ['state'=>'disabled'];
        $end=microtime(true)+max(1,min(20,$seconds));$results=[];
        foreach(Db::all("SELECT gtin14 FROM external_products WHERE availability='found' AND next_refresh_at<=? ORDER BY next_refresh_at,id LIMIT 10",[Db::now()])as$product){try{self::enqueue('product',$product['gtin14'],-1);}catch(Problem){break;}}
        foreach (Db::all("SELECT id FROM external_jobs WHERE (state IN ('queued','retry') OR state='running' AND lease_until<?) AND available_at<=? ORDER BY priority DESC,available_at,id LIMIT 10",[Db::now(),Db::now()]) as $job) {
            if (microtime(true)>$end-4) break;
            $results[]=self::work($job['id']);
        }
        Operations::record('external.worker','completed',['jobs'=>count($results)]);
        return ['state'=>'completed','jobs'=>$results];
    }

    public static function retry(string $id): array
    {
        Api::id($id);$row=Db::one('SELECT * FROM external_jobs WHERE id=?',[$id]);
        if (!$row) throw new Problem(404,'external_lookup','Operațiunea nu a fost găsită.');
        return self::enqueue($row['kind'],$row['gtin14'],(int)$row['priority']);
    }
}
