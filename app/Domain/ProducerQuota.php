<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Problem};

final class ProducerQuota {
    public static function usedStorage(string $tenant): int {return (int)Db::one('SELECT COALESCE(SUM(unique_documents.bytes),0) AS n FROM (SELECT MAX(size_bytes) AS bytes FROM documents WHERE tenant_id=? GROUP BY storage_key) unique_documents',[$tenant])['n'];}
    public const DEFAULTS=['products'=>10000,'trade_items'=>20000,'product_info_versions'=>100000,'batches'=>100000,'recipe_versions'=>5000,'documents'=>2000,'change_proposals'=>10000,'integration_connectors'=>200,'integration_mappings'=>2000];
    public static function limit(string $name,int $default): int {$settings=Config::get('producer_limits',[]);return isset($settings[$name])&&is_int($settings[$name])?max(1,$settings[$name]):$default;}
    public static function count(array $p,string $table,int $additional=1): void {
        if(!isset(self::DEFAULTS[$table])||$additional<0)throw new \LogicException('Unknown quota resource.');
        if($additional===0)return;
        Db::one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$p['tenant']]);
        $used=(int)Db::one('SELECT COUNT(*) AS n FROM '.$table.' WHERE tenant_id=?',[$p['tenant']])['n'];
        if($used+$additional>self::limit($table,self::DEFAULTS[$table]))throw new Problem(413,'producer_quota','Limita organizației pentru '.$table.' a fost atinsă. Contactează operatorul pentru ajustare.');
    }
    public static function storage(array $p,int $bytes,int $documents=1): void {
        if($bytes===0&&$documents===0)return;
        self::count($p,'documents',$documents);$used=self::usedStorage($p['tenant']);
        if($bytes<0||$used+$bytes>self::limit('storage_bytes',268435456))throw new Problem(413,'storage_quota','Spațiul alocat organizației a fost atins. Contactează operatorul pentru ajustare.');
    }
    public static function summary(array $p): array {
        $limits=[];foreach(self::DEFAULTS as$table=>$default)$limits[$table]=['used'=>(int)Db::one('SELECT COUNT(*) AS n FROM '.$table.' WHERE tenant_id=?',[$p['tenant']])['n'],'limit'=>self::limit($table,$default)];
        $limits['storage_bytes']=['used'=>self::usedStorage($p['tenant']),'limit'=>self::limit('storage_bytes',268435456)];return ['resources'=>$limits];
    }
}
