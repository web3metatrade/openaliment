<?php
declare(strict_types=1);
namespace App\Core;

/** Application-local encryption key. Never copied into a public release or response. */
final class PrivateKey {
    public static function key(): string {
        $dir=rtrim((string)Config::get('storage'),'/\\').'/private/keys';
        if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new \RuntimeException('Private key storage unavailable.');
        $path=$dir.'/application.key';$lock=fopen($dir.'/key.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('Private key lock unavailable.');
        try{
            if(!is_file($path)){$handle=fopen($path,'x+b');if(!$handle)throw new \RuntimeException('Private key creation failed.');$key=random_bytes(32);if(fwrite($handle,$key)!==32){fclose($handle);throw new \RuntimeException('Private key write failed.');}fflush($handle);fclose($handle);@chmod($path,0600);}
            $key=file_get_contents($path);if(!is_string($key)||strlen($key)!==32)throw new \RuntimeException('Invalid private application key.');return $key;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function seal(string $text): string {
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($text,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag,'openaliment-private-v1',16);
        if($cipher===false)throw new \RuntimeException('Encryption failed.');return 'v1:'.base64_encode($iv.$tag.$cipher);
    }
    public static function open(string $value): string {
        if(!str_starts_with($value,'v1:'))throw new \RuntimeException('Invalid encrypted value.');$raw=base64_decode(substr($value,3),true);
        if($raw===false||strlen($raw)<28)throw new \RuntimeException('Invalid encrypted value.');
        $text=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'openaliment-private-v1');
        if($text===false)throw new \RuntimeException('Decryption failed.');return $text;
    }
    public static function subject(string $email): string {return hash_hmac('sha256',strtolower($email),self::key());}
}
