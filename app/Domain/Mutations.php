<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Db,Problem};
final class Mutations {
    public static function run(array $p,string $action,string $id,array $d): array {
        return match($action){
            'resolver.draft'=>ResolverRegistry::draft($p,$id,$d),'resolver.publish'=>ResolverRegistry::publish($p,$id,$d),'resolver.withdraw'=>ResolverRegistry::withdraw($p,$id,$d),
            'product.create'=>Catalog::product($p,$d),'trade.create'=>Catalog::trade($p,$id,$d),'label.create'=>Catalog::label($p,$id,$d),
            'label.publish'=>Catalog::publishLabel($p,$id),'batch.create'=>Catalog::batch($p,$id,$d),'batch.publish'=>Catalog::publishBatch($p,$id),'batch.release'=>Catalog::releaseBatch($p,$id),
            'alert.create'=>Catalog::alert($p,$id,$d),'alert.resolve'=>Catalog::resolveAlert($p,$id,$d),
            'document.publish'=>Documents::publish($p,$id,$d),'feedback.moderate'=>Community::moderate($p,$id,$d),
            'proposal.create'=>Community::proposal($p,$id,$d),'proposal.decide'=>Community::decide($p,$id,$d),
            'recipe.create'=>self::recipe($p,$id,$d),
            default=>throw new Problem(404,'action','Operațiunea nu a fost găsită.')
        };
    }
    public static function recipe(array $p,string $product,array $d): array {Auth::write($p);Catalog::owned('products',$product,$p);return Db::atomic(function()use($p,$product,$d){
        $key=Catalog::text($d,'version_key',60);$snapshot=$d['snapshot']??null;if(!is_array($snapshot)||!isset($snapshot['formula'],$snapshot['process']))throw new Problem(422,'recipe','Rețeta necesită formula și procesul.');
        $public=($d['visibility']??'private')==='public';if($public)Auth::publicWrite($p);$license=Catalog::text($d,'license_id',80,false);
        if($public&&!in_array($license,['CC-BY-4.0','CC-BY-SA-4.0','CC0-1.0'],true))throw new Problem(422,'license','Alege o licență deschisă acceptată: CC-BY-4.0, CC-BY-SA-4.0 sau CC0-1.0.');
        if($public&&(!$license||($d['rights_confirmed']??false)!==true||($d['license_confirmed']??false)!==true||($d['public_projection_confirmed']??false)!==true))throw new Problem(422,'license','Publicarea documentației cere drepturi și licență alese explicit.');
        $id=Db::id();$json=Db::json($snapshot);if(strlen($json)>1000000)throw new Problem(422,'size','Rețeta este prea mare.');
        ProducerQuota::count($p,'recipe_versions');Db::insert('recipe_versions',['id'=>$id,'tenant_id'=>$p['tenant'],'product_id'=>$product,'version_key'=>$key,'snapshot_json'=>$json,'snapshot_sha256'=>hash('sha256',$json),'visibility'=>$public?'public':'private','license_id'=>$public?$license:null,'rights_status'=>$public?'cleared':'unconfirmed','created_at'=>Db::now()]);Catalog::audit($p,'recipe.created',$id,['visibility'=>$public?'public':'private','license_id'=>$public?$license:null]);return ['id'=>$id];
    });}
    public static function idempotent(array $p,string $key,string $fingerprint,callable $fn): array {
        if(!preg_match('/\A[A-Za-z0-9_.:\-]{8,128}\z/D',$key))throw new Problem(422,'idempotency','Idempotency-Key trebuie să conțină 8–128 caractere ASCII.');
        return Db::atomic(function()use($p,$key,$fingerprint,$fn){
            $principal=$p['token']??$p['user'];
            Db::run('INSERT IGNORE INTO idempotency_keys (tenant_id,principal_id,request_key,request_hash,created_at) VALUES (?,?,?,?,?)',[$p['tenant'],$principal,$key,$fingerprint,Db::now()]);
            $row=Db::one('SELECT * FROM idempotency_keys WHERE tenant_id=? AND principal_id=? AND request_key=? FOR UPDATE',[$p['tenant'],$principal,$key]);
            if(!hash_equals($row['request_hash'],$fingerprint))throw new Problem(409,'idempotency_conflict','Cheia a fost utilizată pentru altă cerere.');
            if($row['response_json'])return json_decode($row['response_json'],true,512,JSON_THROW_ON_ERROR);
            $result=$fn();Db::run('UPDATE idempotency_keys SET response_json=? WHERE tenant_id=? AND principal_id=? AND request_key=?',[Db::json($result),$p['tenant'],$principal,$key]);return $result;
        });
    }
}
