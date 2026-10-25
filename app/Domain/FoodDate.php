<?php
declare(strict_types=1);
namespace App\Domain;

use App\Core\Problem;

final class FoodDate {
    public static function validate(mixed $value): ?array {
        if ($value===null) return null;
        if (!is_array($value)||array_is_list($value)||array_diff(array_keys($value),['kind','value','precision','source','raw'])) self::invalid();
        if (!in_array($value['kind']??null,['use_by','best_before','reminder'],true)||!in_array($value['precision']??null,['day','month','year'],true)||!in_array($value['source']??null,['producer','barcode','user','receipt'],true)) self::invalid();
        $date=$value['value']??null;
        if (!is_string($date)) self::invalid();
        $valid=match($value['precision']) {
            'day'=>preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/D',$date,$m)&&checkdate((int)$m[2],(int)$m[3],(int)$m[1]),
            'month'=>preg_match('/\A([0-9]{4})-([0-9]{2})\z/D',$date,$m)&&checkdate((int)$m[2],1,(int)$m[1]),
            'year'=>preg_match('/\A[0-9]{4}\z/D',$date)&&(int)$date>0,
        };
        if (!$valid||substr($date,0,4)<'1900'||substr($date,0,4)>'2200') self::invalid();
        $out=['kind'=>$value['kind'],'value'=>$date,'precision'=>$value['precision'],'source'=>$value['source']];
        if (isset($value['raw'])) $out['raw']=Catalog::text($value,'raw',80);
        return $out;
    }

    /** GS1 General Specifications 26.0: 49 years back / 50 forward; day 00 denotes month end. */
    public static function gs1(string $raw,string $kind,?int $referenceYear=null): array {
        if (!preg_match('/\A[0-9]{6}\z/D',$raw)||!in_array($kind,['use_by','best_before'],true)) self::invalid();
        $referenceYear??=(int)date('Y');$year=intdiv($referenceYear,100)*100+(int)substr($raw,0,2);
        if ($year<$referenceYear-49) $year+=100;
        if ($year>$referenceYear+50) $year-=100;
        $month=(int)substr($raw,2,2);$day=(int)substr($raw,4,2);
        if (!checkdate($month,$day===0?1:$day,$year)) self::invalid();
        return self::validate(['kind'=>$kind,'value'=>sprintf($day===0?'%04d-%02d':'%04d-%02d-%02d',$year,$month,$day),'precision'=>$day===0?'month':'day','source'=>'barcode','raw'=>$raw]);
    }
    private static function invalid(): never { throw new Problem(422,'food_date','Data alimentului este incorectă.'); }
}
