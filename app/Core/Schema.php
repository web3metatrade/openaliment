<?php
declare(strict_types=1);
namespace App\Core;
/** Validator for the deliberately small JSON Schema subset used by AI output. */
final class Schema {
    public static function check(mixed $v,array $s): bool {
        if(isset($s['enum'])&&!in_array($v,$s['enum'],true))return false;
        $valid=false;foreach((array)($s['type']??[])as$t)$valid=$valid||match($t){'object'=>is_array($v)&&(!array_is_list($v)||$v===[]),'array'=>is_array($v)&&array_is_list($v),'string'=>is_string($v),'integer'=>is_int($v),'number'=>is_int($v)||is_float($v),'boolean'=>is_bool($v),'null'=>$v===null,default=>false};if(!$valid)return false;
        if(is_array($v)&&isset($s['properties'])){foreach($s['required']??[]as$k)if(!array_key_exists($k,$v))return false;foreach($v as$k=>$x){if(!isset($s['properties'][$k])){if(($s['additionalProperties']??true)===false)return false;}elseif(!self::check($x,$s['properties'][$k]))return false;}}
        if(is_array($v)&&isset($s['items']))foreach($v as$x)if(!self::check($x,$s['items']))return false;return true;
    }
}
