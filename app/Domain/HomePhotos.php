<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Problem};

final class HomePhotos {
    public static function put(array $p,string $id,string $bytes): array {
        HomeInventory::id($id);HomeSync::member($p,true);$size=strlen($bytes);$image=@getimagesizefromstring($bytes);
        if($size<4||$size>1000000||!$image||$image[2]!==IMAGETYPE_JPEG||$image[0]>6000||$image[1]>6000||(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes)!=='image/jpeg')throw new Problem(422,'home_photo','Fotografia trebuie să fie JPEG și să aibă cel mult 1 MB.');
        $sourceSha=hash('sha256',$bytes);$bytes=\App\Core\FilePolicy::jpegWithoutMetadata($bytes);$size=strlen($bytes);
        $sha=hash('sha256',$bytes);
        return Db::atomic(function()use($p,$id,$bytes,$size,$sha,$sourceSha){
            Db::one('SELECT id FROM home_households WHERE id=? FOR UPDATE',[$p['household_id']]);HomeSync::member($p,true);$old=Db::one('SELECT sha256 FROM home_photos WHERE household_id=? AND id=?',[$p['household_id'],$id]);if($old){if(!hash_equals($old['sha256'],$sha)&&!hash_equals($old['sha256'],$sourceSha))throw new Problem(409,'home_photo_conflict','Fotografia cu acest identificator diferă.');return ['id'=>$id,'sha256'=>$old['sha256']];}
            if((int)Db::one('SELECT COUNT(*) AS n FROM home_photos WHERE household_id=?',[$p['household_id']])['n']>=5000)throw new Problem(413,'home_photo_quota','A fost atinsă limita de fotografii a familiei.');
            if((int)Db::one('SELECT COALESCE(SUM(size_bytes),0) AS n FROM home_photos WHERE household_id=?',[$p['household_id']])['n']+$size>32000000)throw new Problem(413,'home_photo_quota','Fotografiile familiei au atins limita de 32 MB. Fotografia rămâne pe dispozitiv.');
            $key='home/'.$p['household_id'].'/'.$id.'.jpg';$dir=rtrim((string)Config::get('storage'),'/\\').'/private/home/'.$p['household_id'];if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new Problem(503,'home_photo_storage','Fotografia nu a putut fi păstrată.');
            if(is_link($dir)||is_link($dir.'/'.$id.'.jpg'))throw new Problem(503,'home_photo_storage','Fotografia nu a putut fi păstrată.');$temporary=$dir.'/.'.Db::id().'.tmp';$file=$dir.'/'.$id.'.jpg';
            try{
                if(file_put_contents($temporary,$bytes,LOCK_EX)!==$size)throw new Problem(503,'home_photo_storage','Fotografia nu a putut fi păstrată.');@chmod($temporary,0600);
                if(!rename($temporary,$file))throw new Problem(503,'home_photo_storage','Fotografia nu a putut fi păstrată.');
                Db::insert('home_photos',['household_id'=>$p['household_id'],'id'=>$id,'sha256'=>$sha,'size_bytes'=>$size,'storage_key'=>$key,'created_at'=>Db::now()]);
            }catch(\Throwable $e){if(is_file($temporary))unlink($temporary);if(is_file($file)&&hash_file('sha256',$file)===$sha)unlink($file);throw $e;}
            return ['id'=>$id,'sha256'=>$sha];
        });
    }
    public static function read(array $p,string $id): array {
        HomeInventory::id($id);HomeSync::member($p);$r=Db::one('SELECT * FROM home_photos WHERE household_id=? AND id=?',[$p['household_id'],$id])??throw new Problem(404,'home_photo','Fotografia nu a fost găsită.');
        $base=realpath(rtrim((string)Config::get('storage'),'/\\').'/private/home');$file=realpath(rtrim((string)Config::get('storage'),'/\\').'/private/'.$r['storage_key']);if(!$base||!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file))throw new Problem(404,'home_photo','Fotografia nu a fost găsită.');return ['path'=>$file,'size'=>(int)$r['size_bytes'],'sha256'=>$r['sha256']];
    }
}
