<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Api, Db, Problem};

final class ExternalMedia
{
    public static function work(string $gtin, ?array $lease=null): array
    {
        $asset=Db::one("SELECT a.* FROM external_assets a JOIN external_products p ON p.id=a.external_product_id WHERE p.gtin14=? AND a.state='pending' ORDER BY a.id LIMIT 1",[$gtin]);
        if (!$asset) return ['code'=>'no_image'];
        try {
            $response=OpenFoodFacts::image($asset['source_url']);$bytes=$response['body'];
            $image=@getimagesizefromstring($bytes);$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if (!$image || !in_array($mime,['image/jpeg','image/png','image/webp'],true) || $image[0]<1 || $image[1]<1 || $image[0]*$image[1]>16000000) throw new Problem(422,'external_image','Imaginea externă nu este acceptată.');
            $extension=match($mime){'image/png'=>'png','image/webp'=>'webp',default=>'jpg'};
            Db::atomic(static function()use($asset,$bytes,$extension,$mime,$image,$lease):void{
                Db::one('SELECT id FROM external_providers WHERE id=? FOR UPDATE',[OpenFoodFacts::PROVIDER]);
                if($lease)ExternalJobs::assertLease($lease);
                $current=Db::one('SELECT state FROM external_assets WHERE id=? FOR UPDATE',[$asset['id']]);if($current['state']!=='pending')return;
                $key=ExternalDatasets::write('assets',$bytes,$extension);
                Db::run("UPDATE external_assets SET state='ready',storage_key=?,sha256=?,mime=?,width=?,height=?,size_bytes=?,fetched_at=?,last_error_code=NULL WHERE id=?",[$key,hash('sha256',$bytes),$mime,$image[0],$image[1],strlen($bytes),Db::now(),$asset['id']]);
            });
        } catch (Problem $error) {
            if ($error->status<500 && $error->status!==429) Db::run("UPDATE external_assets SET state='rejected',last_error_code=? WHERE id=?",[$error->errorCode,$asset['id']]);
            throw $error;
        }
        $more=Db::one("SELECT a.id FROM external_assets a JOIN external_products p ON p.id=a.external_product_id WHERE p.gtin14=? AND a.state='pending' LIMIT 1",[$gtin])!==null;
        return ['code'=>'image_ready','more'=>$more];
    }

    public static function serve(string $id): never
    {
        Api::id($id);
        if (!ExternalCatalog::available()) throw new Problem(404,'external_image','Imaginea nu este disponibilă.');
        $row=Db::one("SELECT a.* FROM external_assets a JOIN external_products p ON p.id=a.external_product_id JOIN external_product_revisions r ON r.external_product_id=p.id AND r.id=p.current_revision_id WHERE a.id=? AND a.state='ready' AND p.availability='found' AND r.visibility='public'",[$id]);
        if (!$row) throw new Problem(404,'external_image','Imaginea nu este disponibilă.');
        $path=ExternalDatasets::path($row['storage_key']);
        if (!is_file($path)||!hash_equals($row['sha256'],hash_file('sha256',$path))) throw new Problem(404,'external_image','Imaginea nu este disponibilă.');
        header('Content-Type: '.$row['mime']);header('Content-Length: '.$row['size_bytes']);header('Cache-Control: public, max-age=3600');header('ETag: "'.$row['sha256'].'"');header('Link: <https://creativecommons.org/licenses/by-sa/3.0/>; rel="license"');
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD') readfile($path);
        exit;
    }
}
