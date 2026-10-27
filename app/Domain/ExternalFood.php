<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Problem;

/** External observations deliberately do not use the producer's FoodData status. */
final class ExternalFood
{
    public const VERSION = 'openaliment.external-food.v1';
    private const NUTRIENTS = ['energy-kj'=>'energy_kj','energy-kcal'=>'energy_kcal','fat'=>'fat_g','saturated-fat'=>'saturates_g','carbohydrates'=>'carbohydrate_g','sugars'=>'sugars_g','polyols'=>'polyols_g','fiber'=>'fibre_g','proteins'=>'protein_g','salt'=>'salt_g'];
    private const ALLERGENS = ['gluten'=>'gluten','wheat'=>'gluten','barley'=>'gluten','rye'=>'gluten','oats'=>'gluten','crustaceans'=>'crustaceans','eggs'=>'eggs','fish'=>'fish','peanuts'=>'peanuts','soybeans'=>'soy','soy'=>'soy','milk'=>'milk','nuts'=>'nuts','almonds'=>'nuts','hazelnuts'=>'nuts','walnuts'=>'nuts','cashew-nuts'=>'nuts','pistachio-nuts'=>'nuts','celery'=>'celery','mustard'=>'mustard','sesame-seeds'=>'sesame','sulphur-dioxide-and-sulphites'=>'sulphites','lupin'=>'lupin','molluscs'=>'molluscs'];

    public static function text(mixed $value, int $maximum=180): ?string
    {
        if (!is_string($value) || !mb_check_encoding($value,'UTF-8')) return null;
        $value=trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u','',$value));
        return $value===''?null:mb_substr($value,0,$maximum);
    }

    public static function normalize(array $product, string $gtin): array
    {
        $code=$product['code']??null;
        if (!is_string($code) || OpenFoodFacts::gtin($code)!==$gtin) throw new Problem(422,'external_identity','Identificatorul returnat de sursă este diferit.');
        if(isset($product['rev'])&&!preg_match('/\A[0-9]{1,20}\z/D',(string)(is_scalar($product['rev'])?$product['rev']:'')))throw new Problem(422,'external_schema','Revizia sursei este incorectă.');
        $schema=$product['schema_version']??1000;
        if (!is_int($schema) || $schema<1000 || $schema>1004) throw new Problem(422,'external_schema','Versiunea sursei necesită verificarea mapării.');
        if (isset($product['product_type']) && $product['product_type']!=='food') throw new Problem(422,'external_type','Înregistrarea nu este un aliment.');
        foreach(['nutrition','images','selected_images','tags_sources']as$key)if(isset($product[$key])&&!is_array($product[$key]))throw new Problem(422,'external_schema','Structura sursei necesită verificare.');
        if(isset($product['nutrition']['input_sets'])&&(!is_array($product['nutrition']['input_sets'])||!array_is_list($product['nutrition']['input_sets'])))throw new Problem(422,'external_schema','Structura nutrițională necesită verificare.');
        $language=self::text($product['lang']??null,2)??'und';
        $nameRo=self::text($product['product_name_ro']??null);
        $ingredientsRo=self::text($product['ingredients_text_ro']??null,5000);
        $out=[
            'source_environment'=>ExternalCatalog::settings()['source_environment'],'schema_version'=>self::VERSION,'provider'=>OpenFoodFacts::PROVIDER,'gtin14'=>$gtin,'source_code'=>$code,
            'name'=>$nameRo??self::text($product['product_name']??null),'name_language'=>$nameRo?'ro':$language,
            'brand'=>self::text($product['brands']??null,500),'net_quantity'=>self::text($product['quantity']??null,100),
            'ingredients'=>$ingredientsRo??self::text($product['ingredients_text']??null,5000),'ingredients_language'=>$ingredientsRo?'ro':$language,
            'storage_text'=>self::text($product['conservation_conditions']??null,1500),
            'categories'=>self::tags($product['categories_tags']??[]),
            'allergens'=>self::allergens($product),'nutrition'=>self::nutrition($product),
            'scores'=>['nutriscore'=>self::text($product['nutriscore_grade']??null,20),'nova'=>is_numeric($product['nova_group']??null)?(int)$product['nova_group']:null,'environment'=>self::text($product['ecoscore_grade']??null,20)],
            'origins'=>self::text($product['origins']??null,500),'manufacturing_places'=>self::text($product['manufacturing_places']??null,500),
            'markets'=>self::tags($product['countries_tags']??[]),'quality_errors'=>self::tags($product['data_quality_errors_tags']??[]),
            'images'=>self::images($product),'source_url'=>OpenFoodFacts::page($code),'licenses'=>OpenFoodFacts::licenses(),
            'provenance'=>[],
        ];
        foreach (['name','brand','net_quantity','ingredients','categories','allergens','nutrition','scores','origins','manufacturing_places'] as $field) {
            $out['provenance'][$field]=['provider'=>OpenFoodFacts::PROVIDER,'basis'=>$field==='scores'?'derived':(in_array($field,['nutrition','allergens'],true)?'mixed':'reported'),'source_url'=>$out['source_url'],'language'=>str_starts_with($field,'ingredients')?$out['ingredients_language']:$out['name_language']];
        }
        return $out;
    }

    public static function tags(mixed $tags): array
    {
        if (!is_array($tags) || !array_is_list($tags)) return [];
        $out=[];
        foreach (array_slice($tags,0,100) as $tag) if (($value=self::text($tag,150))!==null) $out[]=$value;
        return array_values(array_unique($out));
    }

    private static function allergenMap(array $tags): array
    {
        $mapped=[];$unmapped=[];
        foreach ($tags as $tag) {
            $key=str_starts_with($tag,'en:')?substr($tag,3):$tag;
            if ($key==='none') continue;
            if (isset(self::ALLERGENS[$key])) $mapped[]=self::ALLERGENS[$key]; else $unmapped[]=$tag;
        }
        return ['values'=>array_values(array_unique($mapped)),'unmapped'=>$unmapped];
    }

    private static function allergens(array $product): array
    {
        $packaging=$product['tags_sources']['allergens']['packaging']['tags']??null;
        $contains=self::allergenMap(self::tags(is_array($packaging)?$packaging:($product['allergens_tags']??[])));
        $traces=self::allergenMap(self::tags($product['tags_sources']['traces']['packaging']['tags']??$product['traces_tags']??[]));
        return ['status'=>is_array($packaging)?'reported':($contains['values']?'derived':'unknown'),
            'contains'=>$contains['values'],'may_contain'=>$traces['values'],'unmapped'=>array_values(array_unique(array_merge($contains['unmapped'],$traces['unmapped']))),
            'negative_declaration'=>!$contains['values']&&!$contains['unmapped']&&is_array($packaging)&&in_array('en:none',$packaging,true),'source'=>'openfoodfacts'];
    }

    private static function nutrition(array $product): array
    {
        $sets=[];
        // Schema 1003/1004 aggregates may mix estimates and packaging values.
        // Only explicit input sets are mapped; estimates stay marked as estimates.
        foreach (array_slice($product['nutrition']['input_sets']??[],0,12) as $index=>$input) {
            if (!is_array($input)) continue;
            $per=$input['per']??null;
            $basis=match($per){'100g'=>'per_100_g','100ml'=>'per_100_ml','serving','portion'=>'per_portion',default=>null};
            $preparation=$input['preparation']??null;
            if (!$basis || !in_array($preparation,['as_sold','as_prepared'],true)) continue;
            $values=[];$modifiers=[];
            foreach (self::NUTRIENTS as $remote=>$local) {
                $v=$input['nutrients'][$remote]??null;
                if (!is_array($v) || !is_numeric($v['value']??null)) continue;
                $number=(float)$v['value'];$unit=$v['unit']??'';
                if (!is_finite($number) || $number<0 || $number>100000) continue;
                if (str_ends_with($local,'_g')) {if ($unit==='mg') $number/=1000;elseif($unit!=='g')continue;}
                elseif ($unit!==($local==='energy_kj'?'kJ':'kcal')) continue;
                $values[$local]=$number;
                if (!empty($v['modifier'])) $modifiers[$local]=self::text($v['modifier'],10);
            }
            if (!$values) continue;
            $source=self::text($input['source']??null,40)??'unknown';
            $sets[]=['basis'=>$basis,'preparation'=>$preparation,'values'=>$values,'modifiers'=>(object)$modifiers,'basis_source'=>$source,'input_index'=>$index,'kind'=>in_array($source,['packaging','manufacturer'],true)?'reported':'estimated','portion'=>null];
        }
        // Legacy snapshots have no new nutrition structure. They remain explicitly legacy.
        if (!isset($product['nutrition']) && is_array($product['nutriments']??null)) {
            $per=$product['nutrition_data_per']??null;$values=[];
            if (in_array($per,['100g','100ml'],true)) foreach(self::NUTRIENTS as$remote=>$local) {
                $value=$product['nutriments'][$remote.'_100g']??null;
                if (is_numeric($value)&&is_finite((float)$value)&&(float)$value>=0&&(float)$value<=10000)$values[$local]=(float)$value;
            }
            if ($values)$sets[]=['basis'=>$per==='100ml'?'per_100_ml':'per_100_g','preparation'=>'as_sold','values'=>$values,'modifiers'=>(object)[],'basis_source'=>'legacy_label_report','input_index'=>0,'kind'=>'reported','portion'=>null];
        }
        return ['status'=>$sets?'available':'unknown','sets'=>$sets];
    }

    private static function images(array $product): array
    {
        $out=[];
        foreach (['front','ingredients','nutrition','packaging'] as $kind) {
            $urls=$product['selected_images'][$kind]['small']??[];
            if (!is_array($urls))continue;
            foreach (array_unique(['ro',self::text($product['lang']??null,2)??'','en','fr']) as $language) {
                $url=$urls[$language]??null;
                if (is_string($url)&&OpenFoodFacts::imageUrl($url)) {
                    $metadata=$product['images']['selected'][$kind][$language]??null;$imageId=is_array($metadata)?($metadata['imgid']??null):null;$uploader=is_scalar($imageId)?self::text($product['images']['uploaded'][$imageId]['uploader']??null,180):null;
                    $out[]=['kind'=>$kind,'language'=>$language,'url'=>$url,'source_asset_key'=>hash('sha256',$url),'license'=>OpenFoodFacts::licenses()['images'],'credit'=>$uploader?'Open Food Facts · '.$uploader:'Open Food Facts contributors'];
                    break;
                }
            }
        }
        return $out;
    }
}
