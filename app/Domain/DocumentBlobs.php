<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Migrations,Problem};

/** Content-addressed private storage, isolated by organization, with independent source records. */
final class DocumentBlobs {
    public static function bytes(array $p,string $bytes,string $mime): array {
        AccountChallenges::ready();$sha=hash('sha256',$bytes);$size=strlen($bytes);if($size>15000000)throw new Problem(413,'document_size','Documentul depășește 15 MB.');
        return Db::atomic(function()use($p,$bytes,$mime,$sha,$size){
            Db::one('SELECT id FROM organizations WHERE id=? FOR UPDATE',[$p['tenant']]);
            $blob=Db::one('SELECT * FROM document_blobs WHERE tenant_id=? AND sha256=?',[$p['tenant'],$sha]);
            if(!$blob){$legacy=Db::one('SELECT storage_key,mime,size_bytes,created_at FROM documents WHERE tenant_id=? AND sha256=? ORDER BY created_at,id LIMIT 1',[$p['tenant'],$sha]);
                if($legacy&&self::valid($legacy['storage_key'],$sha)){$blob=$legacy+['tenant_id'=>$p['tenant'],'sha256'=>$sha];Db::insert('document_blobs',$blob);}
            }
            if($blob){if(!self::valid($blob['storage_key'],$sha))throw new Problem(503,'document_integrity','Documentul păstrat necesită verificare.');return ['storage_key'=>$blob['storage_key'],'sha256'=>$sha,'reused'=>true];}
            ProducerQuota::storage($p,$size,0);$key='blobs/'.$p['tenant'].'/'.$sha;$base=rtrim((string)Config::get('storage'),'/\\').'/private';$dir=$base.'/blobs/'.$p['tenant'];
            if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('Document storage unavailable.');if(is_link($dir)||is_link($base.'/'.$key))throw new \RuntimeException('Invalid document storage.');
            $temporary=$dir.'/.'.Db::id().'.tmp';$file=$base.'/'.$key;
            try{if(file_put_contents($temporary,$bytes,LOCK_EX)!==$size)throw new \RuntimeException('Document write failed.');@chmod($temporary,0600);if(!rename($temporary,$file))throw new \RuntimeException('Document write failed.');}finally{if(is_file($temporary))unlink($temporary);}
            Db::onRollback(static function()use($file,$sha){if(is_file($file)&&hash_file('sha256',$file)===$sha)@unlink($file);});
            Db::insert('document_blobs',['tenant_id'=>$p['tenant'],'sha256'=>$sha,'storage_key'=>$key,'mime'=>$mime,'size_bytes'=>$size,'created_at'=>Db::now()]);
            Db::run("INSERT IGNORE INTO document_scans (tenant_id,sha256,state,details_json) VALUES (?,?,'pending','{}')",[$p['tenant'],$sha]);return ['storage_key'=>$key,'sha256'=>$sha,'reused'=>false];
        });
    }
    public static function provenance(string $document,array $details): void {
        if(!Migrations::readiness())return;$json=Db::json($details);Db::run('INSERT IGNORE INTO document_provenance (id,document_id,source_sha256,details_json,created_at) VALUES (?,?,?,?,?)',[Db::id(),$document,hash('sha256',$json),$json,Db::now()]);
    }
    public static function valid(string $key,string $sha): bool {try{$path=self::path($key);return hash_equals($sha,hash_file('sha256',$path));}catch(Problem){return false;}}
    public static function path(string $key): string {
        $base=realpath(rtrim((string)Config::get('storage'),'/\\').'/private');$file=$base?realpath($base.'/'.$key):false;
        if(!$base||!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file)||is_link($base.'/'.$key))throw new Problem(404,'document_file','Documentul nu este disponibil.');return $file;
    }
}
