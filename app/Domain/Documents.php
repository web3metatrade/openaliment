<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Config,Db,Problem};
final class Documents {
    public static function upload(array $p,string $product,array $file,array $d): array {
        Auth::write($p);Catalog::owned('products',$product,$p);
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name'])||$file['size']>15000000)throw new Problem(422,'upload','Fișierul nu poate fi încărcat. Limita este 15 MB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!\App\Core\FilePolicy::publishable($mime))throw new Problem(422,'mime','Sunt acceptate PDF, text, JSON și imagini JPEG, PNG sau WebP.');
        return Db::atomic(function()use($p,$product,$file,$d,$mime){
        ProducerQuota::count($p,'documents');
        $id=Db::id();$sha=hash_file('sha256',$file['tmp_name']);$dir=Config::get('storage').'/private/'.$p['tenant'];if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new \RuntimeException('Storage unavailable');
        if(\App\Core\Migrations::readiness()){$blob=DocumentBlobs::bytes($p,file_get_contents($file['tmp_name']),$mime);$key=$blob['storage_key'];}
        else{
        ProducerQuota::storage($p,(int)$file['size'],0);
        $key=$p['tenant'].'/'.$id;$stored=Config::get('storage').'/private/'.$key;Db::onRollback(static function()use($stored){if(is_file($stored))@unlink($stored);});
        if(!move_uploaded_file($file['tmp_name'],$stored))throw new \RuntimeException('Upload failed');
        @chmod(Config::get('storage').'/private/'.$key,0600);
        }
        $provenance=['method'=>'upload','original_name'=>mb_substr(basename($file['name']),0,180)];Db::insert('documents',['id'=>$id,'tenant_id'=>$p['tenant'],'product_id'=>$product,'title'=>Catalog::text($d,'title'),'sha256'=>$sha,'storage_key'=>$key,'mime'=>$mime,'size_bytes'=>$file['size'],'provenance_json'=>Db::json($provenance),'created_at'=>Db::now()]);DocumentBlobs::provenance($id,$provenance);Catalog::audit($p,'document.uploaded',$id);return ['id'=>$id,'visibility'=>'private','scan'=>\App\Core\Migrations::readiness()?'pending':'legacy_unverified'];});
    }
    public static function publish(array $p,string $id,array $d): array {Auth::approve($p);return Db::atomic(function()use($p,$id,$d){
        $doc=Catalog::owned('documents',$id,$p);if(($d['rights_confirmed']??false)!==true||($d['public_projection_confirmed']??false)!==true)throw new Problem(422,'rights','Confirmă drepturile și copia publicabilă.');
        if(!\App\Core\FilePolicy::publishable($doc['mime']))throw new Problem(422,'document_type','Originalul rămâne în arhiva privată. Încarcă o copie publicabilă PDF, text, JSON sau imagine.');
        if(str_contains($doc['provenance_json'],'quarantined_corrupt_original'))throw new Problem(422,'quarantine','Originalul deteriorat rămâne în carantină.');
        DocumentScanner::ensure($doc);
        $license=Catalog::text($d,'license_id',80,false); // Hosting does not require an open licence.
        Db::run("UPDATE documents SET visibility='public',rights_status='cleared',license_id=? WHERE id=? AND tenant_id=?",[$license,$id,$p['tenant']]);Catalog::audit($p,'document.published',$id,['license'=>$license,'rights_declaration'=>'v1','sha256'=>$doc['sha256']]);return ['id'=>$id];
    });}
}
