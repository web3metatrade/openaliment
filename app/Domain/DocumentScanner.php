<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Db,Migrations,Problem};

final class DocumentScanner {
    public static function status(array $doc): array {
        if(!Migrations::readiness())return ['state'=>'legacy_unverified'];
        return Db::one('SELECT state,engine,scanned_at FROM document_scans WHERE tenant_id=? AND sha256=?',[$doc['tenant_id'],$doc['sha256']])??['state'=>'legacy_unverified','engine'=>null,'scanned_at'=>null];
    }
    public static function ensure(array $doc): void {
        if(!Migrations::readiness())return;$state=self::status($doc);
        // Scan results must commit independently; a refused publication must not roll them back.
        if($state['state']!=='clean'&&!Db::pdo()->inTransaction())$state=self::scan($doc);
        if($state['state']!=='clean')throw new Problem(422,'document_scan',$state['state']==='rejected'?'Documentul nu a trecut verificarea de securitate.':'Documentul așteaptă verificarea de securitate înainte de publicare.');
        if(!DocumentBlobs::valid($doc['storage_key'],$doc['sha256']))throw new Problem(409,'document_integrity','Conținutul documentului diferă de copia verificată.');
    }
    public static function scan(array $doc): array {
        $state='error';$engine='unavailable';$details=['reason'=>'scanner_unavailable'];
        try{
            $file=DocumentBlobs::path($doc['storage_key']);if(!hash_equals($doc['sha256'],hash_file('sha256',$file)))throw new \RuntimeException('integrity');
            $options=Config::get('document_scanner',[]);$transport=$options['transport']??'disabled';if($transport==='disabled')$state='pending';
            if($transport==='test_clean'&&Config::get('environment')==='test'&&str_contains((string)(Config::get('db',[])['dsn']??''),'dbname=openaliment_tests;')){$state='clean';$engine='isolated-test-only';$details=['simulated'=>true];}
            elseif($transport==='clamd'){
                $endpoint=$options['endpoint']??null;if(!is_string($endpoint)||strlen($endpoint)>250||!preg_match('~\A(?:unix:///[A-Za-z0-9_./-]+|tcp://(?:127\.0\.0\.1|localhost|\[::1\]):[0-9]{1,5})\z~D',$endpoint))throw new \RuntimeException('endpoint');
                $socket=@stream_socket_client($endpoint,$error,$message,3,STREAM_CLIENT_CONNECT);if(!$socket)throw new \RuntimeException('connection');stream_set_timeout($socket,2);$handle=fopen($file,'rb');if(!$handle){fclose($socket);throw new \RuntimeException('file');}$deadline=microtime(true)+15;
                try{self::write($socket,"zINSTREAM\0");while(!feof($handle)){if(microtime(true)>$deadline)throw new \RuntimeException('timeout');$chunk=fread($handle,65536);if($chunk===false)throw new \RuntimeException('read');self::write($socket,pack('N',strlen($chunk)).$chunk);}self::write($socket,pack('N',0));$response='';
                    while(!feof($socket)&&strlen($response)<8192){$part=fread($socket,1024);if($part===false||$part==='')break;$response.=$part;if(str_contains($response,"\0"))break;}
                    if(stream_get_meta_data($socket)['timed_out'])throw new \RuntimeException('timeout');$response=trim($response,"\0\r\n ");$engine='ClamAV INSTREAM';
                    if(str_ends_with($response,': OK')){$state='clean';$details=[];}elseif(str_ends_with($response,' FOUND')){$state='rejected';$details=['reason'=>'malware_detected'];}else throw new \RuntimeException('scan_response');
                }finally{fclose($handle);fclose($socket);}
            }
            if(!hash_equals($doc['sha256'],hash_file('sha256',$file))){$state='error';$details=['reason'=>'content_changed'];}
        }catch(\Throwable){$state='error';$details=['reason'=>'verification_failed'];}
        Db::run('INSERT INTO document_scans (tenant_id,sha256,state,engine,scanned_at,details_json) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE state=VALUES(state),engine=VALUES(engine),scanned_at=VALUES(scanned_at),details_json=VALUES(details_json)',[$doc['tenant_id'],$doc['sha256'],$state,$engine,Db::now(),Db::json($details)]);
        if($state==='rejected')Db::run("UPDATE documents SET visibility='private' WHERE tenant_id=? AND sha256=?",[$doc['tenant_id'],$doc['sha256']]);
        return ['state'=>$state,'engine'=>$engine,'scanned_at'=>Db::now()];
    }
    private static function write($socket,string $bytes): void {$length=strlen($bytes);$at=0;while($at<$length){$written=fwrite($socket,substr($bytes,$at));if($written===false||$written===0)throw new \RuntimeException('scan_write');$at+=$written;}}
    public static function work(int $limit=3): array {
        if(!Migrations::readiness()||(Config::get('document_scanner',[])['transport']??'disabled')==='disabled')return ['processed'=>0,'available'=>false];$count=0;
        foreach(Db::all("SELECT d.* FROM documents d LEFT JOIN document_scans s ON s.tenant_id=d.tenant_id AND s.sha256=d.sha256 WHERE d.mime IN ('application/pdf','text/plain','application/json','image/jpeg','image/png','image/webp') AND d.id=(SELECT MIN(x.id) FROM documents x WHERE x.tenant_id=d.tenant_id AND x.sha256=d.sha256) AND (s.sha256 IS NULL OR (s.state IN ('pending','error') AND (s.scanned_at IS NULL OR s.scanned_at<?))) ORDER BY d.created_at LIMIT ".max(1,min(5,$limit)),[gmdate('Y-m-d H:i:s',time()-3600)])as$doc){self::scan($doc);$count++;}return ['processed'=>$count,'available'=>true];
    }
}
