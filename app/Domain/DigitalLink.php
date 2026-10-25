<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Config,Problem};
final class DigitalLink {
    public static function gtin(string $value): string {
        if(!preg_match('/\A(?:[0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14})\z/D',$value))throw new Problem(422,'gtin','GTIN trebuie să conțină 8, 12, 13 sau 14 cifre.');
        $g=str_pad($value,14,'0',STR_PAD_LEFT);$sum=0;
        for($i=12;$i>=0;$i--)$sum+=(int)$g[$i]*($i%2===0?3:1);
        if(($sum+(int)$g[13])%10!==0||$g==='00000000000000')throw new Problem(422,'gtin','Cifra de control GTIN este incorectă.');return $g;
    }
    public static function lot(string $value): string {
        // GS1 AI (10), X character set; broader native lot codes are stored separately.
        if(!preg_match('/\A[!"%&\x27()*+,\-.\/:;<=>?0-9A-Z_a-z]{1,20}\z/D',$value))throw new Problem(422,'gs1_lot','Lotul nu se încadrează în setul GS1 AI (10).');return $value;
    }
    public static function path(string $gtin,?string $lot=null): string {
        $path='/01/'.self::gtin($gtin);return $lot===null?$path:$path.'/10/'.rawurlencode(self::lot($lot));
    }
    public static function parse(string $path): array {
        if(!preg_match('~\A/01/([0-9]{14})(?:/10/([^/]+))?/?\z~D',$path,$m))throw new Problem(400,'digital_link','Adresa Digital Link nu este acceptată.');
        try{$g=self::gtin($m[1]);}catch(Problem){throw new Problem(400,'gtin','GTIN invalid.');}
        $lot=null;if(isset($m[2])&&$m[2]!==''){
            if(preg_match('/%(?![A-Fa-f0-9]{2})/',$m[2]))throw new Problem(400,'encoding','Codificarea URL este incorectă.');
            $lot=rawurldecode($m[2]);try{self::lot($lot);}catch(Problem){throw new Problem(400,'gs1_lot','Lot GS1 invalid.');}
        }return [$g,$lot];
    }
    public static function canGenerate(string $lot): bool {try{self::lot($lot);}catch(Problem){return false;}if(in_array($lot,['.','..'],true))return false;return ResolverRegistry::transportReady()||((bool)Config::get('gs1_transport_verified',false)&&preg_match(Config::get('gs1_lot_pattern'),$lot)===1);}
}
