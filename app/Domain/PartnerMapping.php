<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\Problem;

/** A bounded declarative mapper. No executable expressions, plugins or outgoing requests. */
final class PartnerMapping {
    public const VERSION='openaliment.mapping.v1';
    private const OPS=['copy','text','decimal','integer','gtin','date_iso','date_dmy','boolean'];
    public static function validate(array $mapping): array {
        try{$serialized=json_encode($mapping,JSON_THROW_ON_ERROR,32);}catch(\JsonException){self::bad('Maparea depășește adâncimea acceptată.');}if(strlen($serialized)>100000)self::bad('Maparea este prea mare.');
        if(($mapping['schema_version']??null)!==self::VERSION||!is_array($mapping['fields']??null)||count($mapping['fields'])>100)self::bad('Mapare incorectă.');
        if(array_diff(array_keys($mapping),['schema_version','fields','arrays']))self::bad('Câmp necunoscut în mapare.');
        self::rules($mapping['fields']);
        $arrays=$mapping['arrays']??[];if(!is_array($arrays)||!array_is_list($arrays)||count($arrays)>2)self::bad('Prea multe liste mapate.');
        foreach($arrays as $a){if(!is_array($a)||array_diff(array_keys($a),['to','from','fields'])||!is_string($a['to']??null)||!is_string($a['from']??null)||!is_array($a['fields']??null))self::bad('Listă mapată incorect.');self::pointer($a['to']);self::pointer($a['from']);self::rules($a['fields']);}
        $targets=array_keys($mapping['fields']);foreach($arrays as$a)$targets[]=$a['to'];
        self::distinct($targets);
        return ['schema_version'=>self::VERSION,'fields'=>$mapping['fields'],'arrays'=>array_values($arrays)];
    }
    private static function rules(array $rules): void {
        if(count($rules)>100)self::bad('Prea multe câmpuri mapate.');self::distinct(array_keys($rules));
        foreach($rules as$to=>$r){self::pointer($to);if(!is_array($r)||array_diff(array_keys($r),['from','value','op','required']))self::bad('Regulă incorectă.');
            if(array_key_exists('from',$r)===array_key_exists('value',$r))self::bad('Regula necesită o sursă sau o constantă.');
            if(array_key_exists('from',$r))self::pointer($r['from']);if(array_key_exists('op',$r)&&!is_string($r['op'])||!in_array($r['op']??'copy',self::OPS,true)||array_key_exists('required',$r)&&!is_bool($r['required']))self::bad('Transformare nepermisă.');
            if(array_key_exists('value',$r)&&strlen(json_encode($r['value'],JSON_THROW_ON_ERROR))>20000)self::bad('Constanta este prea mare.');
        }
    }
    private static function distinct(array $targets): void {foreach($targets as$i=>$a)foreach($targets as$j=>$b)if($i<$j&&($a===$b||str_starts_with($a,$b.'/')||str_starts_with($b,$a.'/')))self::bad('Destinații suprapuse în mapare.');}
    private static function pointer(mixed $pointer): array {
        if(!is_string($pointer)||strlen($pointer)>240||!mb_check_encoding($pointer,'UTF-8')||!str_starts_with($pointer,'/')||preg_match('/~(?![01])/',$pointer))self::bad('Cale JSON Pointer incorectă.');
        $parts=explode('/',substr($pointer,1));if(count($parts)>8)self::bad('Cale prea adâncă.');
        foreach($parts as&$p){$p=str_replace(['~1','~0'],['/','~'],$p);if($p===''||in_array($p,['__proto__','prototype','constructor'],true)||strlen($p)>100||preg_match('/[\x00-\x1F\x7F]/',$p))self::bad('Segment de cale nepermis.');}return $parts;
    }
    private static function get(array $data,string $path,bool &$found): mixed {$value=$data;foreach(self::pointer($path)as$part){if(!is_array($value)||!array_key_exists($part,$value)){$found=false;return null;}$value=$value[$part];}$found=true;return $value;}
    private static function put(array &$out,string $path,mixed $value): void {$parts=self::pointer($path);$last=array_pop($parts);$at=&$out;foreach($parts as$p){$at[$p]??=[];$at=&$at[$p];}$at[$last]=$value;}
    public static function apply(array $mapping,array $payload): array {
        $m=self::validate($mapping);if(strlen(json_encode($payload,JSON_THROW_ON_ERROR))>1000000)self::bad('Datele partenerului sunt prea mari.');
        $out=self::map($m['fields'],$payload);
        foreach($m['arrays']as$a){$found=false;$list=self::get($payload,$a['from'],$found);if(!$found||!is_array($list)||!array_is_list($list)||count($list)>500)self::bad('Lista sursă lipsește sau este prea mare.');$rows=[];foreach($list as$row){if(!is_array($row))self::bad('Element de listă incorect.');$rows[]=self::map($a['fields'],$row);}self::put($out,$a['to'],$rows);}
        return $out;
    }
    private static function map(array $rules,array $payload): array {
        $out=[];foreach($rules as$to=>$r){$found=true;$value=array_key_exists('value',$r)?$r['value']:self::get($payload,$r['from'],$found);if(!$found){if($r['required']??false)self::bad('Lipsește o valoare obligatorie pentru '.$to.'.');continue;}
            $op=$r['op']??'copy';
            if($op==='text'){if(!is_string($value)&&!is_int($value))self::bad('Text incorect pentru '.$to.'.');$value=trim((string)$value);}
            elseif(in_array($op,['decimal','integer'],true)){
                if(is_string($value)){if(!preg_match('/\A-?[0-9]+(?:[.,][0-9]+)?\z/D',$value))self::bad('Număr ambiguu pentru '.$to.'.');$value=(float)str_replace(',','.',$value);}
                if((!is_int($value)&&!is_float($value))||!is_finite((float)$value)||abs($value)>1e12||$op==='integer'&&floor((float)$value)!==(float)$value)self::bad('Număr incorect pentru '.$to.'.');if($op==='integer')$value=(int)$value;
            }elseif($op==='gtin'){if(!is_string($value))self::bad('GTIN trebuie transmis ca text, pentru a păstra zerourile.');$value=DigitalLink::gtin($value);}
            elseif(in_array($op,['date_iso','date_dmy'],true)){
                if(!is_string($value))self::bad('Data trebuie transmisă ca text.');$format=$op==='date_iso'?'Y-m-d':'d.m.Y';$date=\DateTimeImmutable::createFromFormat('!'.$format,$value);if(!$date||$date->format($format)!==$value)self::bad('Dată calendaristică incorectă pentru '.$to.'.');$value=$date->format('Y-m-d');
            }elseif($op==='boolean'&&!is_bool($value))self::bad('Valoare booleană incorectă.');
            self::put($out,$to,$value);
        }return $out;
    }
    private static function bad(string $message): never {throw new Problem(422,'partner_mapping',$message);}
}
