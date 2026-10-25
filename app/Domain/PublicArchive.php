<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Api,Config,Db,FilePolicy,Problem};
use ZipArchive;

/** Standalone copy made only from explicitly public, licensed materials. */
final class PublicArchive {
    public static function build(string $recipeId): string {
        if(!class_exists(ZipArchive::class))throw new Problem(503,'archive_unavailable','Arhiva nu este disponibilă momentan.');
        $recipe=Db::one("SELECT id,product_id,version_key,snapshot_json,snapshot_sha256,license_id,technical_status FROM recipe_versions WHERE id=? AND visibility='public' AND rights_status='cleared' AND license_id IS NOT NULL",[$recipeId]);
        if(!$recipe)throw new Problem(404,'not_found','Documentația nu a fost găsită.');
        if(!hash_equals($recipe['snapshot_sha256'],hash('sha256',$recipe['snapshot_json'])))throw new Problem(503,'archive_integrity','Documentația necesită verificare.');
        $documents=Db::all("SELECT id,title,sha256,mime,size_bytes,license_id,storage_key,provenance_json FROM documents WHERE product_id=? AND visibility='public' AND rights_status='cleared' AND license_id IS NOT NULL ORDER BY id LIMIT 101",[$recipe['product_id']]);
        $documents=array_values(array_filter($documents,static fn($doc)=>FilePolicy::publishable($doc['mime'])&&!str_contains($doc['provenance_json'],'quarantined_corrupt_original')));
        if(count($documents)>100||array_sum(array_column($documents,'size_bytes'))>50000000)throw new Problem(413,'archive_size','Documentația depășește limita unei arhive publice. Folosește descărcările individuale.');
        $identity=['recipe'=>$recipe,'documents'=>array_map(static function($doc){unset($doc['storage_key']);return $doc;},$documents)];$key=hash('sha256',Db::json($identity));$directory=rtrim((string)Config::get('storage'),'/\\').'/private/public-archives';
        if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new \RuntimeException('Archive storage unavailable.');$file=$directory.'/'.$key.'.zip';
        if(is_file($file)&&!is_link($file))return $file;$lock=fopen($directory.'/.build.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new Problem(503,'archive_busy','Arhiva se pregătește. Încearcă din nou.');
        $temporary=$directory.'/'.Db::id().'.partial';$zip=new ZipArchive();
        try{
            if(is_file($file))return $file;self::prune($directory,array_sum(array_column($documents,'size_bytes'))+3*strlen($recipe['snapshot_json'])+100000);if($zip->open($temporary,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new \RuntimeException('Archive write failed.');
            $snapshot=json_decode($recipe['snapshot_json'],true,64,JSON_THROW_ON_ERROR);$readme="# Documentație alimentară deschisă\n\nVersiune: ".$recipe['version_key']."\n\nStadiu declarat: ".$recipe['technical_status']."\n\nLicența rețetei și procesului: ".$recipe['license_id']."\n\nAceastă copie nu este o certificare a alimentului sau o validare a estimărilor. Licențele documentelor sunt indicate individual în manifest.json.\n\n## Rețetă și proces\n\n```json\n".json_encode($snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n```\n\n## Proveniență\n\nSursa: ".url('/p/'.$recipe['product_id'].'/v/'.rawurlencode($recipe['version_key']))."\n\nAmprenta snapshotului: ".$recipe['snapshot_sha256']."\n";
            $zip->addFromString('README.md',$readme);$zip->addFromString('recipe.json',$recipe['snapshot_json']);$manifest=['schema_version'=>'openaliment.public-archive.v1','recipe'=>array_diff_key($recipe,['snapshot_json'=>true]),'files'=>['recipe.json'=>['sha256'=>$recipe['snapshot_sha256'],'license_id'=>$recipe['license_id']]],'documents'=>[]];
            foreach($documents as$doc){$path=DocumentBlobs::path($doc['storage_key']);if(!hash_equals($doc['sha256'],hash_file('sha256',$path)))throw new Problem(503,'archive_integrity','Un document necesită verificare.');$extension=['application/pdf'=>'pdf','text/plain'=>'txt','application/json'=>'json','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$doc['mime']];$name='documents/'.$doc['id'].'.'.$extension;
                if(!$zip->addFile($path,$name))throw new \RuntimeException('Archive document write failed.');unset($doc['storage_key']);$manifest['documents'][]=$doc;$manifest['files'][$name]=['sha256'=>$doc['sha256'],'license_id'=>$doc['license_id']];
            }
            $zip->addFromString('manifest.json',Db::json($manifest));if(!$zip->close())throw new \RuntimeException('Archive commit failed.');@chmod($temporary,0600);if(!rename($temporary,$file))throw new \RuntimeException('Archive commit failed.');return $file;
        }finally{try{$zip->close();}catch(\Throwable){}if(is_file($temporary))unlink($temporary);flock($lock,LOCK_UN);fclose($lock);}
    }
    private static function prune(string $directory,int $reserve=0): int {
        $entries=[];$total=0;$deleted=0;$budget=268435456;
        foreach(new \DirectoryIterator($directory)as$file)if($file->isFile()&&!$file->isLink()&&preg_match('/\A[a-f0-9]{64}\.zip\z/D',$file->getFilename())){$entries[]=['path'=>$file->getPathname(),'bytes'=>$file->getSize(),'time'=>$file->getMTime()];$total+=$file->getSize();}
        usort($entries,static fn($a,$b)=>$a['time']<=>$b['time']);$count=count($entries);
        foreach($entries as$entry)if($entry['time']<time()-86400||$total+$reserve>$budget||($reserve>0&&$count>=200)){if(@unlink($entry['path'])){$total-=$entry['bytes'];$count--;$deleted++;}}
        if($total+$reserve>$budget)throw new Problem(503,'archive_capacity','Arhiva nu este disponibilă momentan. Documentele pot fi descărcate individual.');return $deleted;
    }
    public static function cleanup(): int {
        $directory=rtrim((string)Config::get('storage'),'/\\').'/private/public-archives';if(!is_dir($directory))return 0;$lock=fopen($directory.'/.build.lock','c');if(!$lock)return 0;
        try{if(!flock($lock,LOCK_EX|LOCK_NB))return 0;return self::prune($directory);}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function download(string $id): never {
        Api::limit('download');$file=self::build($id);header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="openaliment-documentatie-'.$id.'.zip"');header('Content-Length: '.filesize($file));header('Content-Security-Policy: sandbox');if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')readfile($file);exit;
    }
}
