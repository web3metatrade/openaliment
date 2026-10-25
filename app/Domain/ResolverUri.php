<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\Problem;

final class ResolverUri {
    public static function parse(string $path, string $query = ''): array {
        if (strlen($path) > 4096 || strlen($query) > 8192) throw new Problem(400,'uri_size','Adresa este prea lungă.');
        $path = rtrim($path,'/');
        $epc = null;
        if (preg_match('~\A/(eh[0-9a-f]+|ex[A-Za-z0-9_-]+)\z~D',$path,$compressed)) {
            $epc = EpcDecoder::decode($compressed[1]);
            $path = self::path($epc['01'],array_intersect_key($epc,array_flip(['22','10','21','235'])));
            $extras = array_diff_key($epc,array_flip(['01','22','10','21','235','_hostname']));
            if ($extras) $query = http_build_query($extras,'','&',PHP_QUERY_RFC3986).($query !== ''?'&'.$query:'');
        }
        if (!preg_match('~\A/01/([0-9]{14})((?:/[0-9]{2,3}/[^/]+)*)\z~D',$path,$matches)) throw new Problem(400,'digital_link','Adresă GS1 Digital Link incorectă.');
        try { $gtin = DigitalLink::gtin($matches[1]); } catch (Problem) { throw new Problem(400,'gtin','GTIN invalid.'); }
        $parts = $matches[2] === '' ? [] : explode('/',ltrim($matches[2],'/'));
        $qualifiers = []; $last = -1; $order = ['22'=>0,'10'=>1,'21'=>2,'235'=>3];
        for ($i=0;$i<count($parts);$i+=2) {
            $ai=$parts[$i];
            if (!isset($order[$ai]) || $order[$ai] <= $last) throw new Problem(400,'qualifier','Ordinea calificatorilor este incorectă.');
            $value=self::decode($parts[$i+1]); self::qualifier($ai,$value); $qualifiers[$ai]=$value; $last=$order[$ai];
        }
        if (isset($qualifiers['235']) && count($qualifiers)>1) throw new Problem(400,'qualifier','TPX se utilizează separat de ceilalți calificatori.');
        $parameters=[];
        foreach ($query === ''?[]:preg_split('/[&;]/',$query) as $pair) {
            if ($pair === '') continue;
            $values=explode('=',$pair,2);
            if (count($values)!==2) throw new Problem(400,'query','Parametru fără valoare.');
            [$key,$value]=array_map(self::decode(...),$values);
            if ($key==='' || preg_match('/[\x00-\x1F\x7F]/',$key.$value)) throw new Problem(400,'query','Parametru incorect.');
            if (isset($parameters[$key]) && in_array($key,['linkType','context'],true)) throw new Problem(400,'query','Parametru de selecție duplicat.');
            $parameters[$key]=$value;
        }
        return ['gtin'=>$gtin,'qualifiers'=>$qualifiers,'parameters'=>$parameters,'query'=>$query,'path'=>self::path($gtin,$qualifiers),'epc'=>$epc!==null,'hostname'=>$epc['_hostname']??null];
    }
    public static function anchor(array $uri): string { return $uri['hostname']?'https://'.$uri['hostname'].$uri['path']:url($uri['path']); }
    public static function decode(string $value): string {
        if (preg_match('/%(?![0-9A-Fa-f]{2})/',$value)) throw new Problem(400,'encoding','Codificare URL incorectă.');
        return rawurldecode($value);
    }
    public static function qualifier(string $ai,string $value): void {
        $max=$ai==='235'?28:20;
        if (!in_array($ai,['22','10','21','235'],true) || !preg_match('/\A[!"%&\x27()*+,\-.\/:;<=>?0-9A-Z_a-z]{1,'.$max.'}\z/D',$value)) throw new Problem(400,'qualifier','Calificator GS1 invalid.');
    }
    public static function qualifiers(array $values): array {
        foreach ($values as $ai=>$value) {
            if (!is_string($value)) throw new Problem(400,'qualifier','Calificatorul GS1 trebuie transmis ca text.');
            self::qualifier((string)$ai,$value);
        }
        if (isset($values['235'])&&count($values)>1) throw new Problem(400,'qualifier','TPX se utilizează separat de ceilalți calificatori.');
        $ordered=[];foreach(['22','10','21','235'] as $ai)if(isset($values[$ai]))$ordered[$ai]=$values[$ai];
        return $ordered;
    }
    public static function path(string $gtin,array $qualifiers=[]): string {
        $gtin=DigitalLink::gtin($gtin);$qualifiers=self::qualifiers($qualifiers);
        $path='/01/'.$gtin;
        foreach (['22','10','21','235'] as $ai) if (isset($qualifiers[$ai])) $path.='/'.$ai.'/'.rawurlencode($qualifiers[$ai]);
        return $path;
    }
    public static function scopes(array $qualifiers): array {
        $scopes=[[]];
        foreach (['22','10','21','235'] as $ai) if (isset($qualifiers[$ai])) $scopes[]=[(string)$ai=>$qualifiers[$ai]];
        if (isset($qualifiers['22'],$qualifiers['10'])) $scopes[]=['22'=>$qualifiers['22'],'10'=>$qualifiers['10']];
        return $scopes;
    }
    public static function scopeHash(array $qualifiers): string {
        $ordered=[]; foreach (['22','10','21','235'] as $ai) if (isset($qualifiers[$ai])) $ordered[$ai]=$qualifiers[$ai];
        return hash('sha256',json_encode((object)$ordered,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
}
