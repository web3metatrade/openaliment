<?php
declare(strict_types=1);
namespace App\Core;

/** 256 fixed shards bound inode usage and avoid a database write for every public scan. */
final class RateLimit {
    public static function check(string $identity,int $limit,int $seconds,int $cost=1): void {
        $dir=Config::get('storage').'/cache/api-rates';$hash=hash('sha256',$identity);
        if(is_link($dir)||(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir)))throw new Problem(503,'rate_storage','Serviciul este temporar indisponibil.');
        $path=$dir.'/'.substr($hash,0,2).'.json';
        if(is_link($path))throw new Problem(503,'rate_storage','Serviciul este temporar indisponibil.');
        $file=@fopen($path,'c+');if(!$file||!flock($file,LOCK_EX)){if($file)fclose($file);throw new Problem(503,'rate_storage','Serviciul este temporar indisponibil.');}
        try {
            @chmod($path,0600);$now=time();$window=intdiv($now,$seconds);$raw=stream_get_contents($file,1048577);
            if(strlen($raw)>1048576)throw new Problem(503,'rate_storage','Serviciul este temporar indisponibil.');
            $stored=json_decode($raw?:'{}',true);$entries=is_array($stored)?array_filter($stored,static fn($v)=>is_array($v)&&($v['reset']??0)>$now):[];
            if(!isset($entries[$hash])&&count($entries)>=1024){header('Retry-After: 60');throw new Problem(429,'rate_capacity','Serviciul primește prea multe cereri. Reîncearcă peste un minut.');}
            $entry=$entries[$hash]??null;$hits=is_array($entry)&&($entry['window']??null)===$window?(int)($entry['hits']??0):0;
            $hits+=max(1,min(100,$cost));$reset=($window+1)*$seconds;
            $entries[$hash]=['window'=>$window,'hits'=>$hits,'reset'=>$reset];$encoded=json_encode($entries,JSON_THROW_ON_ERROR);
            rewind($file);ftruncate($file,0);if(fwrite($file,$encoded)!==strlen($encoded))throw new Problem(503,'rate_storage','Serviciul este temporar indisponibil.');fflush($file);
        } finally {flock($file,LOCK_UN);fclose($file);}
        header('RateLimit-Limit: '.$limit);header('RateLimit-Remaining: '.max(0,$limit-$hits));header('RateLimit-Reset: '.max(1,$reset-$now));
        if($hits>$limit){header('Retry-After: '.max(1,$reset-$now));throw new Problem(429,'rate_limit','Limita de cereri a fost atinsă. Reîncearcă după intervalul indicat.');}
    }
    public static function cleanup(int $budget=1000): int {
        $base=Config::get('storage').'/cache/api-rates';if(!is_dir($base)||is_link($base))return 0;$seen=0;$cleared=0;
        foreach(new \DirectoryIterator($base)as$entry){if($entry->isLink()||!$entry->isFile()||!preg_match('/\A[a-f0-9]{2}\.json\z/D',$entry->getFilename()))continue;
            if(++$seen>$budget)break;$file=@fopen($entry->getPathname(),'c+');if(!$file)continue;
            if(flock($file,LOCK_EX|LOCK_NB)){try{if((fstat($file)['mtime']??time())<time()-7200){ftruncate($file,0);fwrite($file,'{}');++$cleared;}}finally{flock($file,LOCK_UN);}}fclose($file);
        }return $cleared;
    }
}
