<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Db,Problem};

/** Strict private inventory projection. It does not accept health preferences or recipes. */
final class HomeInventory {
    public static function id(mixed $value): string {if(!is_string($value)||!preg_match('/\A[a-f0-9]{32}\z/D',$value))self::bad('Identificator de ambalaj incorect.');return $value;}
    private static function keys(array $value,array $keys): void {if(array_diff(array_keys($value),$keys))self::bad('Câmp neacceptat în datele personale.');}
    private static function timestamp(mixed $value,bool $nullable=true): ?string {
        if($value===null&&$nullable)return null;if(!is_string($value)||strlen($value)>35||!preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/D',$value))self::bad('Moment incorect în istoric.');
        try{$date=new \DateTimeImmutable($value);}catch(\Exception){self::bad('Moment incorect în istoric.');}if($date->format('Y-m-d')!==substr($value,0,10)||$date->format('H:i:s')!==substr($value,11,8))self::bad('Moment calendaristic incorect.');return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
    public static function profile(array $p): array {
        $strings=['name'=>180,'category'=>80,'producer'=>500,'ingredients'=>5000,'allergen_text'=>1500,'storage_text'=>1500,'after_opening_text'=>1000,'nutrition_text'=>3000,'net_quantity'=>100,'product_id'=>32,'trade_item_id'=>32,'batch_id'=>32,'gtin14'=>14,'lot_code'=>120,'public_url'=>4096,'alert_scope'=>40,'batch_state'=>40];
        self::keys($p,array_merge(array_keys($strings),['schema_version','food_data','expiry','source','alerts','alerts_checked_at','notices','simulation','gs1_qualifiers','observed_dates']));
        if(($p['schema_version']??null)!=='openaliment.consumer-product.v1')self::bad('Profil alimentar incorect.');$out=['schema_version'=>$p['schema_version']];
        foreach($strings as$key=>$max)if(isset($p[$key]))$out[$key]=Catalog::text($p,$key,$max,false);
        $out['gs1_qualifiers']=null;
        if(isset($p['gs1_qualifiers'])){
            $q=$p['gs1_qualifiers'];if($q instanceof \stdClass)$q=(array)$q;
            if(!is_array($q)||array_is_list($q)&&$q!==[])self::bad('Calificatori GS1 incorecți.');
            try{$q=ResolverUri::qualifiers($q);}catch(Problem){self::bad('Calificatori GS1 incorecți.');}
            $out['gs1_qualifiers']=$q?(object)$q:null;
        }
        $out['observed_dates']=[];
        if(isset($p['observed_dates'])){
            if(!is_array($p['observed_dates'])||!array_is_list($p['observed_dates'])||count($p['observed_dates'])>2)self::bad('Date observate incorecte.');
            foreach($p['observed_dates']as$date){$date=FoodDate::validate($date);if(!$date||$date['source']!=='barcode'||$date['kind']==='reminder')self::bad('Dată observată incorectă.');$out['observed_dates'][]=$date;}
        }
        if(!is_array($p['food_data']??null)||!is_array($p['source']??null))self::bad('Date alimentare sau proveniență lipsă.');$out['food_data']=FoodData::validate($p['food_data']);$out['expiry']=isset($p['expiry'])?FoodDate::validate($p['expiry']):null;
        if(!in_array($p['source']['kind']??null,['producer_approved','demonstration','unavailable','external'],true))self::bad('Proveniență alimentară incorectă.');
        self::keys($p['source'],['kind','version_id','version','sha256','approved_at','checked_at','name','license','url']);$source=[];foreach($p['source']as$key=>$value)$source[$key]=Catalog::text([$key=>$value],$key,1000,false);$out['source']=$source;
        $out['notices']=[];if(!is_array($p['notices']??null)||count($p['notices'])>20)self::bad('Lista notelor este incorectă.');foreach($p['notices']as$n)$out['notices'][]=Catalog::text(['n'=>$n],'n',1000);
        // A device copy cannot assert freshness of a safety check; clients re-fetch live alerts.
        $out['alerts']=[];$out['alerts_checked_at']=null;$out['simulation']=($p['simulation']??false)===true;
        return $out;
    }
    public static function item(array $v): array {
        self::keys($v,['id','revision','profile','code','name','photo_id','quantity','unit','location','state','added_at','opened_at','consumed_at','expiry','updated_at','imported','receipt']);
        $id=self::id($v['id']??null);if(!is_int($v['revision']??null)||$v['revision']<1||$v['revision']>2147483647||!is_array($v['profile']??null))self::bad('Versiune sau profil de ambalaj incorect.');
        if(!in_array($v['state']??null,['available','consumed'],true)||!in_array($v['location']??null,['home','fridge','freezer','pantry'],true)||!in_array($v['unit']??null,['pack','g','ml'],true))self::bad('Starea ambalajului este incorectă.');
        $quantity=$v['quantity']??null;if((!is_int($quantity)&&!is_float($quantity))||!is_finite((float)$quantity)||$quantity<=0||$quantity>100000)self::bad('Cantitate incorectă.');
        $out=['id'=>$id,'revision'=>$v['revision'],'profile'=>self::profile($v['profile']),'code'=>Catalog::text($v,'code',4096,false)??'','name'=>Catalog::text($v,'name',180),'photo_id'=>isset($v['photo_id'])?self::id($v['photo_id']):null,'quantity'=>$quantity,'unit'=>$v['unit'],'location'=>$v['location'],'state'=>$v['state'],'expiry'=>isset($v['expiry'])?FoodDate::validate($v['expiry']):null];
        foreach(['added_at','opened_at','consumed_at','updated_at']as$k)$out[$k]=self::timestamp($v[$k]??null,in_array($k,['opened_at','consumed_at'],true));
        if(($v['imported']??false)===true)$out['imported']=true;
        if(isset($v['receipt'])){if(!is_array($v['receipt']))self::bad('Proveniență de bon incorectă.');$r=$v['receipt'];self::keys($r,['receipt_id','line_id','unit_index']);if(!is_int($r['unit_index']??null)||$r['unit_index']<0||$r['unit_index']>2000)self::bad('Proveniență de bon incorectă.');$out['receipt']=['receipt_id'=>self::id($r['receipt_id']??null),'line_id'=>Catalog::text($r,'line_id',80),'unit_index'=>$r['unit_index']];}
        if(strlen(Db::json($out))>30000)self::bad('Informațiile ambalajului sunt prea mari.');return $out;
    }
    public static function history(array $e): array {
        self::keys($e,['id','type','item_id','before','after','created_at','undone_at','undoes']);$out=['id'=>self::id($e['id']??null),'item_id'=>self::id($e['item_id']??null),'type'=>$e['type']??null,'created_at'=>self::timestamp($e['created_at']??null,false)];
        if(!in_array($out['type'],['add','open','consume','edit','undo'],true))self::bad('Tip de eveniment incorect.');
        foreach(['before','after']as$k){if(!array_key_exists($k,$e)||$e[$k]!==null&&!is_array($e[$k]))self::bad('Eveniment incomplet.');$out[$k]=$e[$k]===null?null:self::item($e[$k]);if($out[$k]!==null&&$out[$k]['id']!==$out['item_id'])self::bad('Referință de istoric incorectă.');}
        if(isset($e['undoes']))$out['undoes']=self::id($e['undoes']);return $out;
    }
    private static function bad(string $message): never {throw new Problem(422,'home_inventory',$message);}
}
