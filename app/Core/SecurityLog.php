<?php
declare(strict_types=1);
namespace App\Core;

/** Bounded, allowlisted security events. Never log headers, request bodies or secrets. */
final class SecurityLog {
    public static function record(string $event,array $context=[]): void {
        try {
            if(!preg_match('/\A[a-z][a-z0-9_.]{1,60}\z/D',$event))return;
            $base=Config::get('storage');if(!is_string($base)||$base==='')return;$dir=$base.'/logs/security';
            if(is_link($dir)||(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir)))return;
            $lockPath=$dir.'/write.lock';if(is_link($lockPath))return;$lock=@fopen($lockPath,'c');if(!$lock)return;
            if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return;}
            try {
                @chmod($lockPath,0600);$path=$dir.'/'.gmdate('Y-m-d').'.ndjson';if(is_link($path))return;
                clearstatcache(true,$path);if(is_file($path)&&filesize($path)>=2097152)return;
                $row=['at'=>gmdate(DATE_ATOM),'event'=>$event];
                $request=$_SERVER['OA_REQUEST_ID']??null;if(is_string($request)&&preg_match('/\A[a-f0-9]{16}\z/D',$request))$row['request_id']=$request;
                $method=$_SERVER['REQUEST_METHOD']??'';if(is_string($method)&&preg_match('/\A[A-Z]{2,10}\z/D',$method))$row['method']=$method;
                // Daily pseudonym for correlation; no raw IP address or URL/lot is retained.
                $row['network']=substr(hash('sha256','openaliment-security|'.gmdate('Y-m-d').'|'.Api::clientIp()),0,32);
                foreach(['user_id','tenant_id','credential_id','client_id','grant_id','code']as$key)if(is_string($context[$key]??null)&&preg_match('/\A[A-Za-z0-9_.-]{1,64}\z/D',$context[$key]))$row[$key]=$context[$key];
                if(is_int($context['status']??null)&&$context['status']>=400&&$context['status']<=599)$row['status']=$context['status'];
                @file_put_contents($path,Db::json($row)."\n",FILE_APPEND);@chmod($path,0600);
            } finally {flock($lock,LOCK_UN);fclose($lock);}
        } catch(\Throwable) { /* Security telemetry must not break QR or authentication. */ }
    }
    public static function cleanup(): int {
        $dir=Config::get('storage').'/logs/security';if(!is_dir($dir)||is_link($dir))return 0;$n=0;$before=gmdate('Y-m-d',time()-7*86400);
        foreach(new \DirectoryIterator($dir)as$file)if(!$file->isLink()&&$file->isFile()&&preg_match('/\A(\d{4}-\d{2}-\d{2})\.ndjson\z/D',$file->getFilename(),$m)&&$m[1]<$before){if(@unlink($file->getPathname()))++$n;}
        return $n;
    }
}
