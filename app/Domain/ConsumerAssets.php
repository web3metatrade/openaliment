<?php
declare(strict_types=1);
namespace App\Domain;

use App\Core\{Config,Db,Problem};

/** Installs only the two pinned, non-executable OCR resources in the release manifest. */
final class ConsumerAssets {
    private const PATHS=['public/assets/ocr/tesseract-core-lstm.wasm','public/assets/ocr/lang/eng.traineddata.gz'];
    public static function manifest(): array {
        $manifest=json_decode((string)file_get_contents(ROOT.'/contracts/consumer-assets.json'),true,16,JSON_THROW_ON_ERROR);
        if (($manifest['schema_version']??'')!=='openaliment.consumer-assets.v1'||count($manifest['assets']??[])!==2) throw new Problem(503,'assets_manifest','Resursele pentru citirea fotografiilor nu sunt disponibile.');
        $seen=[];
        foreach ($manifest['assets'] as $a) {
            if (!in_array($a['path']??null,self::PATHS,true)||isset($seen[$a['path']])||!preg_match('/\A[a-f0-9]{64}\z/D',(string)($a['sha256']??''))||!is_int($a['bytes']??null)||$a['bytes']<1||$a['bytes']>8000000) throw new Problem(503,'assets_manifest','Manifest incorect.');
            $u=parse_url($a['url']??'');
            if (!$u||($u['scheme']??'')!=='https'||($u['host']??'')!=='cdn.jsdelivr.net'||isset($u['user'])||isset($u['pass'])||isset($u['port'])||isset($u['query'])||isset($u['fragment'])) throw new Problem(503,'assets_manifest','Sursa resurselor nu este permisă.');
            $expected=$a['path']===self::PATHS[0]?'/npm/tesseract.js-core@7.0.0/tesseract-core-lstm.wasm':'/npm/@tesseract.js-data/eng@1.0.0/4.0.0_best_int/eng.traineddata.gz';
            if (($u['path']??'')!==$expected) throw new Problem(503,'assets_manifest','Sursa resurselor nu este permisă.');
            $seen[$a['path']]=true;
        }
        return $manifest['assets'];
    }
    public static function ready(): bool {
        foreach (self::manifest() as $a) if (!is_file(ROOT.'/'.$a['path'])||filesize(ROOT.'/'.$a['path'])!==$a['bytes'])return false;
        return true;
    }
    public static function install(): array {
        if (!extension_loaded('curl')) throw new Problem(503,'curl','Conexiunea securizată pentru descărcare nu este disponibilă.');
        $installed=[];$tmp=rtrim((string)Config::get('storage'),'/\\').'/tmp';
        if (!is_dir($tmp)&&!mkdir($tmp,0700,true)) throw new Problem(503,'storage','Directorul temporar nu este disponibil.');
        foreach (self::manifest() as $a) {
            $target=ROOT.'/'.$a['path'];
            for ($p=$target;str_starts_with($p,ROOT.'/public/');$p=dirname($p)) if (is_link($p)) throw new Problem(503,'assets_path','Calea resurselor nu este permisă.');
            if (is_file($target)&&filesize($target)===$a['bytes']&&hash_equals($a['sha256'],hash_file('sha256',$target))) {$installed[]=$a['path'];continue;}
            $part=$tmp.'/ocr-'.Db::id().'.part';$fp=fopen($part,'xb');if(!$fp)throw new Problem(503,'storage','Resursa temporară nu poate fi creată.');@chmod($part,0600);
            $handle=curl_init($a['url']);$received=0;
            try {
                curl_setopt_array($handle,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'OpenAliment-AssetInstaller/1.0',CURLOPT_PROXY=>'',CURLOPT_WRITEFUNCTION=>static function($h,string $chunk)use($fp,$a,&$received):int{$n=strlen($chunk);$received+=$n;if($received>$a['bytes'])return 0;return fwrite($fp,$chunk)?:0;}]);
                $ok=curl_exec($handle);$status=curl_getinfo($handle,CURLINFO_RESPONSE_CODE);fclose($fp);$fp=null;
                if ($ok===false||$status!==200||$received!==$a['bytes']||!hash_equals($a['sha256'],hash_file('sha256',$part))) throw new Problem(503,'assets_download','Resursa nu a putut fi verificată. Poți relua instalarea; fișierele neconfirmate nu sunt activate.');
                $parent=dirname($target);if(!is_dir($parent)&&!mkdir($parent,0755,true))throw new Problem(503,'storage','Resursele nu pot fi instalate.');
                if(!rename($part,$target))throw new Problem(503,'storage','Resursa nu a putut fi activată.');@chmod($target,0644);$installed[]=$a['path'];
            } finally {if(is_resource($fp))fclose($fp);curl_close($handle);if(is_file($part))unlink($part);}
        }
        return $installed;
    }
}
