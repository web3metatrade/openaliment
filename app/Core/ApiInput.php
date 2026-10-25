<?php
declare(strict_types=1);
namespace App\Core;

/** Request-only validator for the JSON Schema vocabulary used by our API contracts. */
final class ApiInput {
    public static function validate(array $input,string $name): void {
        if($name===''||$name==='Upload')return;
        $file=ROOT.'/contracts/platform-openapi.json';
        if(!is_file($file))throw new Problem(503,'contract_unavailable','Contractul API nu este disponibil temporar. Încearcă din nou mai târziu.');
        $root=json_decode(file_get_contents($file),true,128,JSON_THROW_ON_ERROR);
        $schema=$root['components']['schemas'][$name]??null;
        if(!$schema)throw new Problem(503,'contract_unavailable','Schema cererii nu este disponibilă.');
        $error=self::error($input,$schema,$root,'$',0);if($error!==null)throw new Problem(422,'validation',$error);
    }
    private static function error(mixed $v,array $s,array $root,string $path,int $depth): ?string {
        if($depth>40)return $path.': structura depășește adâncimea permisă.';
        if(isset($s['$ref'])){
            $ref=$s['$ref'];if(!str_starts_with($ref,'#/'))return $path.': referință de schemă neacceptată.';
            $resolved=$root;foreach(explode('/',substr($ref,2))as$key){$key=str_replace(['~1','~0'],['/','~'],$key);if(!is_array($resolved)||!array_key_exists($key,$resolved))return $path.': referință de schemă indisponibilă.';$resolved=$resolved[$key];}
            return self::error($v,$resolved,$root,$path,$depth+1);
        }
        if(isset($s['const'])&&$v!==$s['const'])return $path.': confirmare sau valoare obligatorie incorectă.';
        if(isset($s['enum'])&&!in_array($v,$s['enum'],true))return $path.': valoare neacceptată.';
        foreach(['oneOf','anyOf','allOf']as$kind)if(isset($s[$kind])){
            $matches=0;foreach($s[$kind]as$branch)if(self::error($v,$branch,$root,$path,$depth+1)===null)++$matches;
            if(($kind==='oneOf'&&$matches!==1)||($kind==='anyOf'&&$matches===0)||($kind==='allOf'&&$matches!==count($s[$kind])))return $path.': structura nu corespunde variantei declarate.';
        }
        if(isset($s['type'])){
            $valid=false;foreach((array)$s['type']as$type)$valid=$valid||match($type){'object'=>is_array($v)&&(!array_is_list($v)||$v===[]),'array'=>is_array($v)&&array_is_list($v),'string'=>is_string($v),'integer'=>is_int($v),'number'=>(is_int($v)||is_float($v))&&is_finite((float)$v),'boolean'=>is_bool($v),'null'=>$v===null,default=>false};
            if(!$valid)return $path.': tip de valoare incorect.';
        }
        if(is_string($v)){
            $length=mb_strlen($v);if($length<($s['minLength']??0)||$length>($s['maxLength']??PHP_INT_MAX))return $path.': lungime neacceptată.';
            if(isset($s['pattern'])&&preg_match('~'.str_replace('~','\\~',$s['pattern']).'~uD',$v)!==1)return $path.': format incorect.';
            if(($s['format']??'')==='email'&&!filter_var($v,FILTER_VALIDATE_EMAIL))return $path.': adresă email incorectă.';
            if(($s['format']??'')==='date'&&(!preg_match('/\A\d{4}-\d{2}-\d{2}\z/D',$v)||!checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4))))return $path.': dată incorectă.';
        }
        if(is_int($v)||is_float($v))if($v<($s['minimum']??-INF)||$v>($s['maximum']??INF))return $path.': valoare în afara intervalului permis.';
        if(is_array($v)){
            foreach($s['required']??[]as$key)if(!array_key_exists($key,$v))return $path.'.'.$key.': câmp obligatoriu.';
            if(isset($s['properties']))foreach($v as$key=>$value){
                if(array_key_exists($key,$s['properties'])){$error=self::error($value,$s['properties'][$key],$root,$path.'.'.$key,$depth+1);if($error)return $error;}
                elseif(($s['additionalProperties']??true)===false)return $path.'.'.$key.': câmp neacceptat.';
            }
            if(isset($s['items'])){
                if(count($v)<($s['minItems']??0)||count($v)>($s['maxItems']??PHP_INT_MAX))return $path.': număr de elemente neacceptat.';
                if(($s['uniqueItems']??false)&&count(array_unique(array_map(Db::json(...),$v)))!==count($v))return $path.': elemente duplicate.';
                foreach($v as$key=>$value){$error=self::error($value,$s['items'],$root,$path.'['.$key.']',$depth+1);if($error)return $error;}
            }
        }return null;
    }
}
