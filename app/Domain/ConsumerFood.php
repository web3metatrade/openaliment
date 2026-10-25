<?php
declare(strict_types=1);
namespace App\Domain;

use App\Core\{Config,Db,Problem};

/** The public consumer projection has no access to recipes, private files or household data. */
final class ConsumerFood {
    public const VERSION='openaliment.consumer-product.v1';

    public static function code(string $input): array {
        $input=trim($input);
        if ($input===''||strlen($input)>4096||preg_match('/[\x00-\x20\x7f]/',$input)) throw new Problem(422,'scan','Codul nu poate fi citit.');
        if (preg_match('/\A[0-9]{8,14}\z/D',$input)) return ['kind'=>'gtin','gtin14'=>DigitalLink::gtin($input),'lot_code'=>null,'native'=>null,'dates'=>[]];
        if (str_starts_with($input,'/')) $input=url($input);
        $u=parse_url($input);
        if (!$u||!in_array(strtolower($u['scheme']??''),['https','http'],true)||isset($u['user'])||isset($u['pass'])||isset($u['fragment'])||empty($u['host'])) throw new Problem(422,'scan','Folosește un cod de produs sau o adresă validă.');
        $local=parse_url((string)Config::get('url'));
        $own=strtolower($u['host'])===strtolower($local['host']??'')&&($u['port']??null)===($local['port']??null)&&strtolower($u['scheme'])===strtolower($local['scheme']??'');
        $path=$u['path']??'/';
        if ($own&&preg_match('~\A/(t|p|b)/([a-f0-9]{32})\z~D',$path,$m)) return ['kind'=>'native','native'=>['type'=>$m[1],'id'=>$m[2]],'gtin14'=>null,'lot_code'=>null,'dates'=>[]];
        if ($own&&($path===DemoBread::page()||$path===DemoBread::page(true))) return ['kind'=>'digital_link','native'=>null,'gtin14'=>DemoBread::GTIN14,'lot_code'=>$path===DemoBread::page(true)?DemoBread::LOT:null,'dates'=>[]];
        if (strtolower($u['scheme'])!=='https'&&!$own) throw new Problem(422,'scan','Adresa externă trebuie să folosească HTTPS.');
        $query=$u['query']??'';
        if(preg_match('~/(eh[0-9a-f]+|ex[A-Za-z0-9_-]+)\z~D',$path,$compressed))$suffix='/'.$compressed[1];
        else {if (!preg_match('~/01/[0-9]{14}(?:/|$)~',$path,$m,PREG_OFFSET_CAPTURE)) throw new Problem(422,'scan','Codul nu identifică un aliment recunoscut.');$suffix=substr($path,$m[0][1]);}
        $uri=ResolverUri::parse($suffix,$query);if($uri['epc'])$query=$uri['query'];$dates=[];$seen=[];
        foreach ($query===''?[]:preg_split('/[&;]/',$query) as $pair) {
            if ($pair==='') continue;
            [$key,$val]=array_pad(explode('=',$pair,2),2,'');$key=ResolverUri::decode($key);
            if (!in_array($key,['15','17'],true)) continue;
            if (isset($seen[$key])) throw new Problem(422,'food_date','Codul conține date duplicate.');
            $seen[$key]=true;$dates[]=FoodDate::gs1(ResolverUri::decode($val),$key==='17'?'use_by':'best_before');
        }
        return ['kind'=>'digital_link','native'=>null,'gtin14'=>$uri['gtin'],'lot_code'=>$uri['qualifiers']['10']??null,'qualifiers'=>$uri['qualifiers']?:null,'dates'=>$dates];
    }

    public static function lookup(string $code): array {
        $parsed=self::code($code);
        if ($parsed['gtin14']===DemoBread::GTIN14) {
            $lot=$parsed['lot_code'];$batch=$lot===DemoBread::LOT;
            $out=self::unknown($parsed);$out['name']='Pâine Bună Tehnic';$out['category']='bread';$out['simulation']=true;
            $out['public_url']=url(DemoBread::page($batch));
            $out['source']=['kind'=>'demonstration','checked_at'=>gmdate(DATE_ATOM),'version'=>'DEMO-E1'];
            $out['notices'][]='Exemplu demonstrativ. Datele alimentare și termenul nu sunt validate.';
            if($lot!==null&&!$batch)$out['notices'][]='Lotul nu este înregistrat în demonstrație.';
            return $out;
        }
        $pub=null;$native=$parsed['native'];
        if ($native) $pub=$native['type']==='b'?Catalog::publicBatch($native['id']):Catalog::publicItem($native['id'],$native['type']==='p');
        else {
            $row=Db::one("SELECT t.id FROM gtin_registry g JOIN trade_items t ON t.id=g.trade_item_id AND t.tenant_id=g.tenant_id WHERE g.gtin14=? AND t.verified_at IS NOT NULL",[$parsed['gtin14']]);
            if ($row) {
                if ($parsed['lot_code']!==null) {
                    $lot=Db::one("SELECT id FROM batches WHERE trade_item_id=? AND lot_code=? AND visibility='public'",[$row['id'],$parsed['lot_code']]);
                    if ($lot) $pub=Catalog::publicBatch($lot['id']);
                }
                if (!$pub) { try {$pub=Catalog::publicItem($row['id']);} catch(Problem $e) { if($e->status!==404)throw $e; } }
            }
        }
        if (!$pub) return self::unknown($parsed);
        return self::project($pub,$parsed);
    }

    public static function project(array $pub,?array $parsed=null): array {
        $label=$pub['label'];$batch=$pub['batch']??null;
        $parsed??=['kind'=>'native','gtin14'=>$pub['gtin14'],'lot_code'=>$batch['lot_code']??null,'dates'=>[],'native'=>null];
        $out=self::unknown($parsed);
        $out['name']=$label['name'];$out['category']=$label['category'];$out['producer']=$pub['producer'];
        $out['ingredients']=$label['ingredients']??null;$out['allergen_text']=$label['allergens']??null;
        $out['storage_text']=$label['storage']??null;$out['after_opening_text']=$label['after_opening']??null;
        $out['net_quantity']=$label['net_quantity']??null;$out['nutrition_text']=$label['nutrition']??null;
        $out['food_data']=isset($label['food_data'])?FoodData::validate($label['food_data']):FoodData::unknown();
        $out['product_id']=$pub['product_id'];$out['trade_item_id']=$pub['trade_item_id'];$out['batch_id']=$batch['id']??null;$out['batch_state']=$batch['state']??null;
        $out['gtin14']=$pub['gtin14'];$out['lot_code']=$batch['lot_code']??$parsed['lot_code'];
        $out['public_url']=url($batch?'/b/'.$batch['id']:'/t/'.$pub['trade_item_id']);
        $out['source']=['kind'=>'producer_approved','version_id'=>$pub['product_info_version_id'],'version'=>$pub['version_key'],'sha256'=>$pub['sha256'],'approved_at'=>$pub['approved_at'],'checked_at'=>gmdate(DATE_ATOM)];
        $out['alerts']=$pub['alerts']??[];$out['alerts_checked_at']=gmdate(DATE_ATOM);
        $out['alert_scope']=$batch?'batch':'product_only';
        if ($batch) {
            foreach (['best_before','use_by'] as $kind) if (!empty($batch['data'][$kind])) $out['expiry']=FoodDate::validate(['kind'=>$kind,'value'=>$batch['data'][$kind],'precision'=>'day','source'=>'producer']);
            if (isset($batch['data']['expiry'])) $out['expiry']=FoodDate::validate($batch['data']['expiry']);
            foreach ($parsed['dates'] as $observed) if ($out['expiry']&&($observed['kind']!==$out['expiry']['kind']||$observed['value']!==$out['expiry']['value'])) $out['notices'][]='Data din cod diferă de informațiile lotului. Verifică ambalajul.';
            if (in_array($batch['state'],['recalled','on_hold'],true)) $out['notices'][]=$batch['state']==='recalled'?'Lot retras. Nu folosi produsul.':'Lot în așteptarea clarificărilor producătorului.';
        } elseif ($parsed['lot_code']!==null) $out['notices'][]='Lotul scanat nu are o pagină publicată. Verifică informațiile de pe ambalaj.';
        if (!$batch&&$pub['gtin14']) {
            $active=Db::all("SELECT a.id,a.message,a.created_at,b.lot_code FROM alerts a JOIN batches b ON b.id=a.batch_id AND b.tenant_id=a.tenant_id WHERE b.trade_item_id=? AND b.visibility='public' AND a.resolved_at IS NULL ORDER BY a.created_at DESC LIMIT 30",[$pub['trade_item_id']]);
            if ($parsed['lot_code']!==null) $active=array_values(array_filter($active,fn($a)=>$a['lot_code']===$parsed['lot_code']));
            $out['alerts']=$active;
            if ($active) $out['notices'][]='Există alerte pentru loturi ale acestui produs. Verifică lotul ambalajului.';
        }
        if ($pub['identity_notice']??null) $out['notices'][]=$pub['identity_notice'];
        return $out;
    }

    private static function unknown(array $parsed): array {
        $dates=$parsed['dates'];$expiry=null;foreach ($dates as $d) if ($expiry===null||$d['kind']==='use_by')$expiry=$d;
        return ['schema_version'=>self::VERSION,'name'=>'Aliment','category'=>null,'producer'=>null,'product_id'=>null,'trade_item_id'=>null,'batch_id'=>null,
            'gtin14'=>$parsed['gtin14'],'lot_code'=>$parsed['lot_code'],'gs1_qualifiers'=>empty($parsed['qualifiers'])?null:(object)$parsed['qualifiers'],'ingredients'=>null,'allergen_text'=>null,'storage_text'=>null,'after_opening_text'=>null,'nutrition_text'=>null,'net_quantity'=>null,
            'food_data'=>FoodData::unknown(),'expiry'=>$expiry,'observed_dates'=>$dates,'alerts'=>[],'alerts_checked_at'=>null,'alert_scope'=>'unknown','public_url'=>null,'source'=>['kind'=>'unavailable','checked_at'=>gmdate(DATE_ATOM)],'simulation'=>false,
            'notices'=>count($dates)>1?['Codul conține mai multe tipuri de termen. Verifică data de pe ambalaj.']:[]];
    }
}
