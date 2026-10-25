<?php
declare(strict_types=1);
namespace App\Domain;

use App\Core\Problem;

/** Structured facts supplied by a producer. Publication still belongs to the label workflow. */
final class FoodData {
    public const VERSION = 'openaliment.food.v1';
    public const ALLERGENS = [
        'gluten'=>'Cereale care conțin gluten', 'crustaceans'=>'Crustacee', 'eggs'=>'Ouă',
        'fish'=>'Pește', 'peanuts'=>'Arahide', 'soy'=>'Soia', 'milk'=>'Lapte',
        'nuts'=>'Fructe cu coajă lemnoasă', 'celery'=>'Țelină', 'mustard'=>'Muștar',
        'sesame'=>'Susan', 'sulphites'=>'Dioxid de sulf și sulfiți', 'lupin'=>'Lupin', 'molluscs'=>'Moluște',
    ];
    public const NUTRIENTS = ['energy_kj','energy_kcal','fat_g','saturates_g','carbohydrate_g','sugars_g','polyols_g','fibre_g','protein_g','salt_g'];

    public static function unknown(): array {
        return ['schema_version'=>self::VERSION,
            'allergens'=>['status'=>'unknown','contains'=>[],'may_contain'=>[]],
            'nutrition'=>['status'=>'unknown','basis'=>null,'preparation'=>null,'jurisdiction'=>null,'values'=>null, 'portion'=>null],
            'after_opening'=>null];
    }

    public static function validate(mixed $value): array {
        if (!is_array($value) || array_is_list($value) || ($value['schema_version']??null)!==self::VERSION) self::invalid('Versiunea datelor alimentare nu este acceptată.');
        self::keys($value,['schema_version','allergens','nutrition','after_opening']);
        $out=self::unknown();
        $a=$value['allergens']??$out['allergens'];
        if (!is_array($a)) self::invalid('Declarația alergenilor este incorectă.');
        self::keys($a,['status','contains','may_contain']);
        if (!in_array($a['status']??null,['unknown','declared'],true)) self::invalid('Starea declarației alergenilor este incorectă.');
        $contains=self::allergenList($a['contains']??[]); $possible=self::allergenList($a['may_contain']??[]);
        if ($a['status']==='unknown' && ($contains||$possible)) self::invalid('Confirmă declarația înainte de completarea alergenilor.');
        if (array_intersect($contains,$possible)) self::invalid('Un alergen declarat nu se introduce și la posibile urme.');
        $out['allergens']=['status'=>$a['status'],'contains'=>$contains,'may_contain'=>$possible];
        $n=$value['nutrition']??$out['nutrition'];
        if (!is_array($n)) self::invalid('Declarația nutrițională este incorectă.');
        self::keys($n,['status','basis','preparation','jurisdiction','values','portion']);
        if (!in_array($n['status']??null,['unknown','declared'],true)) self::invalid('Starea nutriției este incorectă.');
        $values=$n['values']??[];
        if (!is_array($values) || ($values!==[]&&array_is_list($values))) self::invalid('Valorile nutriționale sunt incorecte.');
        self::keys($values,self::NUTRIENTS);
        if ($n['status']==='unknown') {
            if ($values || ($n['basis']??null)!==null || ($n['preparation']??null)!==null || ($n['jurisdiction']??null)!==null || ($n['portion']??null)!==null) self::invalid('Nutriția necunoscută nu poate avea valori presupuse.');
        } else {
            if (!in_array($n['basis']??null,['per_100_g','per_100_ml','per_portion'],true) || !in_array($n['preparation']??null,['as_sold','as_prepared'],true) || !in_array($n['jurisdiction']??null,['EU','US','other'],true)) self::invalid('Precizează baza, prepararea și regimul etichetei nutriționale.');
            $normalized=[];
            foreach ($values as $key=>$number) {
                $max=$n['basis']==='per_portion'?100000:($key==='energy_kj'?10000:($key==='energy_kcal'?2500:($n['basis']==='per_100_g'?100:1000)));
                $normalized[$key]=self::number($number,$max);
            }
            if (!$normalized) self::invalid('Completează cel puțin o valoare nutrițională declarată.');
            if (isset($normalized['carbohydrate_g'],$normalized['sugars_g']) && $normalized['sugars_g']>$normalized['carbohydrate_g']) self::invalid('Zaharurile depășesc carbohidrații declarați.');
            if (isset($normalized['fat_g'],$normalized['saturates_g']) && $normalized['saturates_g']>$normalized['fat_g']) self::invalid('Acizii grași saturați depășesc grăsimile declarate.');
            $portion=null;
            if (($n['portion']??null)!==null) {
                if (!is_array($n['portion'])) self::invalid('Porția este incorectă.');
                self::keys($n['portion'],['amount','unit']);
                if (!in_array($n['portion']['unit']??null,['g','ml'],true)) self::invalid('Unitatea porției trebuie să fie g sau ml.');
                $amount=self::number($n['portion']['amount']??null,100000);
                if ($amount<=0) self::invalid('Porția trebuie să fie mai mare decât zero.');
                $portion=['amount'=>$amount,'unit'=>$n['portion']['unit']];
            }
            if ($n['basis']==='per_portion' && $portion===null) self::invalid('Declarația per porție necesită cantitatea porției.');
            if ($n['basis']==='per_portion'&&$portion['unit']==='g')foreach($normalized as$key=>$v)if(str_ends_with($key,'_g')&&$v>$portion['amount'])self::invalid('Valoarea nutrientului depășește masa porției.');
            $out['nutrition']=['status'=>'declared','basis'=>$n['basis'],'preparation'=>$n['preparation'],'jurisdiction'=>$n['jurisdiction'],'values'=>$normalized,'portion'=>$portion];
        }
        $opening=$value['after_opening']??null;
        if ($opening!==null) {
            if (!is_array($opening)) self::invalid('Instrucțiunea după deschidere este incorectă.');
            self::keys($opening,['hours','conditions']);
            if (!is_int($opening['hours']??null)||$opening['hours']<1||$opening['hours']>8760) self::invalid('Durata după deschidere trebuie să fie între 1 și 8760 de ore.');
            $out['after_opening']=['hours'=>$opening['hours'],'conditions'=>Catalog::text($opening,'conditions',500)];
        }
        return $out;
    }

    public static function fromForm(array $d): array {
        $out=self::unknown();
        if (($d['food_allergens_declared']??'')==='1') $out['allergens']=['status'=>'declared','contains'=>$d['food_contains']??[],'may_contain'=>$d['food_may_contain']??[]];
        else if (!empty($d['food_contains'])||!empty($d['food_may_contain'])) self::invalid('Confirmă că declarația alergenilor a fost verificată.');
        $values=[];
        foreach (self::NUTRIENTS as $key) if (isset($d['food_'.$key])&&$d['food_'.$key]!=='') {
            $v=$d['food_'.$key];
            if (!is_string($v)||!preg_match('/\A[0-9]{1,6}(?:[.,][0-9]{1,4})?\z/D',$v)) self::invalid('Valoare nutrițională incorectă.');
            $values[$key]=(float)str_replace(',','.',$v);
        }
        if ($values) {
            $portion=null;
            if (($d['food_portion_amount']??'')!=='') {if(!is_string($d['food_portion_amount'])||!preg_match('/\A[0-9]{1,6}(?:[.,][0-9]{1,4})?\z/D',$d['food_portion_amount']))self::invalid('Cantitatea porției este incorectă.');$portion=['amount'=>(float)str_replace(',','.',$d['food_portion_amount']),'unit'=>$d['food_portion_unit']??'g'];}
            $out['nutrition']=['status'=>'declared','basis'=>$d['food_basis']??'per_100_g','preparation'=>$d['food_preparation']??'as_sold','jurisdiction'=>$d['food_jurisdiction']??'EU','values'=>$values,'portion'=>$portion];
        }
        if (($d['food_opening_hours']??'')!=='') {
            if (!is_string($d['food_opening_hours'])||!ctype_digit($d['food_opening_hours'])) self::invalid('Durata după deschidere este incorectă.');
            $out['after_opening']=['hours'=>(int)$d['food_opening_hours'],'conditions'=>$d['food_opening_conditions']??''];
        }
        return self::validate($out);
    }

    private static function allergenList(mixed $values): array {
        if (!is_array($values)||!array_is_list($values)||count($values)>14) self::invalid('Lista alergenilor este incorectă.');
        foreach ($values as $v) if (!is_string($v)||!isset(self::ALLERGENS[$v])) self::invalid('Alergen necunoscut.');
        if (count(array_unique($values))!==count($values)) self::invalid('Alergen duplicat.');
        sort($values,SORT_STRING);return $values;
    }
    private static function number(mixed $value,float $max): float {
        if ((!is_int($value)&&!is_float($value))||!is_finite((float)$value)||$value<0||$value>$max) self::invalid('Valoare numerică în afara intervalului.');
        return (float)$value;
    }
    private static function keys(array $value,array $allowed): void { foreach ($value as $k=>$_) if (!in_array($k,$allowed,true)) self::invalid('Câmp alimentar necunoscut: '.(string)$k.'.'); }
    private static function invalid(string $message): never { throw new Problem(422,'food_data',$message); }
}
