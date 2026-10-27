<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\{Config, Problem};

/** Fixed provider transport. Never accepts a resolver target or a caller's URL. */
final class OpenFoodFacts
{
    public const PROVIDER = 'openfoodfacts';
    public const API = '3.6';
    public const MAPPING = 'openaliment.off.v1';
    public const FIELDS = 'code,product_type,product_name,product_name_ro,lang,brands,quantity,ingredients_text,ingredients_text_ro,allergens_tags,traces_tags,categories_tags,tags_sources,nutrition,nutriments,nutrition_data_per,nutrition_data_prepared_per,serving_quantity,serving_quantity_unit,selected_images,images,rev,schema_version,last_modified_t,last_updated_t,nutriscore_grade,nova_group,ecoscore_grade,data_quality_errors_tags,origins,manufacturing_places,countries_tags,packagings,conservation_conditions';

    public static function host(): string {return ExternalCatalog::settings()['source_environment']==='staging'?'world.openfoodfacts.net':'world.openfoodfacts.org';}

    public static function licenses(): array
    {
        return [
            'database' => ['id'=>'ODbL-1.0','url'=>'https://opendatacommons.org/licenses/odbl/1-0/'],
            'contents' => ['id'=>'DbCL-1.0','url'=>'https://opendatacommons.org/licenses/dbcl/1-0/'],
            'images' => ['id'=>'CC-BY-SA-3.0','url'=>'https://creativecommons.org/licenses/by-sa/3.0/'],
            'attribution' => 'Open Food Facts et ses contributeurs',
            'source_url' => 'https://world.openfoodfacts.org',
        ];
    }

    public static function gtin(string $value): string
    {
        $gtin = DigitalLink::gtin($value);
        if ($gtin === DemoBread::GTIN14) {
            throw new Problem(422, 'external_demonstration', 'Identificatorul demonstrativ nu se caută în surse externe.');
        }
        // Restricted circulation identifiers require the issuer's context.
        $ean13 = substr($gtin, 1);
        if ($gtin[0] === '0' && preg_match('/\A2[0-9]/D', $ean13)) {
            throw new Problem(422, 'external_restricted', 'Codul intern al magazinului nu identifică global un produs.');
        }
        return $gtin;
    }

    public static function page(string $sourceCode): string
    {
        if (!preg_match('/\A[0-9]{8,14}\z/D', $sourceCode)) {
            throw new Problem(422, 'external_code', 'Codul sursei este incorect.');
        }
        return 'https://'.self::host().'/product/'.$sourceCode;
    }

    public static function product(string $gtin): array
    {
        $gtin = self::gtin($gtin);
        $url = 'https://'.self::host().'/api/v'.self::API.'/product/'.$gtin.'.json?'.http_build_query([
            'fields'=>self::FIELDS, 'lc'=>'ro', 'cc'=>'ro', 'product_type'=>'food',
        ], '', '&', PHP_QUERY_RFC3986);
        $response = self::request($url, 262144, 1500);
        if ($response['status'] === 404) {
            return ['found'=>false];
        }
        self::success($response);
        try {
            $data = json_decode($response['body'], true, 40, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new Problem(502, 'external_json', 'Sursa nu a furnizat date valide.');
        }
        if (!is_array($data)) {
            throw new Problem(502, 'external_json', 'Sursa nu a furnizat date valide.');
        }
        if (($data['result']['id']??null)==='product_not_found' || ($data['status']??null)===0) {
            return ['found'=>false];
        }
        if (!is_array($data['product']??null) || !in_array($data['status']??null, ['success','success_with_warnings',1], true)
            || !empty($data['errors']) || (isset($data['result']) && ($data['result']['id']??null)!=='product_found')) {
            throw new Problem(502, 'external_result', 'Răspunsul sursei nu confirmă produsul.');
        }
        $sourceCode=$data['product']['code']??null;
        if(!is_string($sourceCode)||self::gtin($sourceCode)!==$gtin)throw new Problem(422,'external_identity','Identificatorul returnat de sursă este diferit.');
        // OFF normalizes zero-padded GS1 GTINs and reports the equivalent code as a warning.
        // Accept only this documented, non-impacting case; unknown warnings require review.
        $warnings=$data['warnings']??[];
        if(!is_array($warnings)||!array_is_list($warnings)
            ||(($data['status']??null)==='success_with_warnings'&&(!$warnings||($data['result']['id']??null)!=='product_found')))throw new Problem(502,'external_result','Avertizările sursei necesită verificare.');
        foreach($warnings as$warning){
            if(!is_array($warning)||($warning['message']['id']??null)!=='different_normalized_product_code'
                ||($warning['impact']['id']??null)!=='none'||($warning['field']['id']??null)!=='code'
                ||($warning['field']['value']??null)!==$sourceCode)throw new Problem(502,'external_result','Avertizările sursei necesită verificare.');
        }
        return ['found'=>true, 'product'=>$data['product']];
    }

    public static function imageUrl(string $url): bool
    {
        return strlen($url)<=2048 && preg_match('~\Ahttps://images\.openfoodfacts\.(?:org|net)/images/products/[0-9/]+/(?:front|ingredients|nutrition|packaging)_[a-z]{2}\.[0-9]+\.(?:100|200|400)\.(?:jpg|png|webp)\z~D', $url) === 1;
    }

    public static function image(string $url): array
    {
        if (!self::imageUrl($url)) {
            throw new Problem(422, 'external_image_url', 'Adresa imaginii nu este permisă.');
        }
        $response = self::request($url, 2097152, 3500);
        self::success($response,'image');
        return $response;
    }

    public static function success(array $response, string $bucket='product_read'): void
    {
        if ($response['status'] === 429) {
            ExternalJobs::cooldown($response['retry_after']??60, $bucket);
            throw new Problem(429, 'external_rate', 'Sursa a limitat temporar cererile.');
        }
        if($response['status']===503){ExternalJobs::cooldown($response['retry_after']??60,$bucket);throw new Problem(503,'external_http','Sursa nu este disponibilă momentan.');}
        if ($response['status'] !== 200) {
            throw new Problem(502, 'external_http', 'Sursa nu este disponibilă momentan.');
        }
    }

    private static function request(string $url, int $maximum, int $timeout): array
    {
        if (\App\Core\Db::pdo()->inTransaction()) {
            throw new \LogicException('External I/O must not hold a database transaction.');
        }
        // Test transport is injectable only in the disposable local environment.
        if (Config::get('environment')==='test' && is_callable(Config::get('external_test_transport'))) {
            return (Config::get('external_test_transport'))($url, $maximum);
        }
        if (!ExternalCatalog::settings()['network_enabled'] || !extension_loaded('curl')) {
            throw new Problem(503, 'external_network', 'Conexiunea la sursa externă este oprită.');
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($host, ['world.openfoodfacts.org','images.openfoodfacts.org','world.openfoodfacts.net','images.openfoodfacts.net'], true)) {
            throw new Problem(422, 'external_host', 'Sursa nu este permisă.');
        }
        if((curl_version()['features'] & CURL_VERSION_ASYNCHDNS)===0)throw new Problem(503,'external_dns_budget','Sursa necesită un resolver DNS cu termen limitat.');
        $body = '';
        $tooLarge = false;
        $retryAfter = 60;
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT_MS=>500, CURLOPT_TIMEOUT_MS=>$timeout,
            CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4, CURLOPT_PROXY=>'',
            CURLOPT_ENCODING=>'', CURLOPT_USERAGENT=>'OpenAliment/0.8.0 ('.ExternalCatalog::settings()['contact'].')',
            CURLOPT_HTTPHEADER=>array_merge(['Accept: '.(str_starts_with($host,'images.')?'image/jpeg,image/png,image/webp':'application/json')],str_ends_with($host,'.net')?['Authorization: Basic '.base64_encode('off:off')]:[]),
            CURLOPT_HEADERFUNCTION=>static function ($handle, string $line) use (&$retryAfter): int {
                if (preg_match('/\ARetry-After:\s*(.+?)\s*\z/iD',trim($line),$m)) {
                    $retryAfter=ctype_digit($m[1])?(int)$m[1]:max(0,(strtotime($m[1])?:time())-time());
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION=>static function ($handle, string $chunk) use (&$body, &$tooLarge, $maximum): int {
                if (strlen($body)+strlen($chunk)>$maximum) { $tooLarge=true; return 0; }
                $body.=$chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $mime = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $address=(string)curl_getinfo($handle,CURLINFO_PRIMARY_IP);
        curl_close($handle);
        if($address!==''&&!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new Problem(502,'external_dns','Adresa sursei nu este permisă.');
        if ($tooLarge) {
            throw new Problem(502, 'external_size', 'Răspunsul sursei depășește limita acceptată.');
        }
        if ($ok===false) {
            throw new Problem(502, 'external_timeout', 'Sursa nu a răspuns la timp.');
        }
        return ['status'=>$status, 'body'=>$body, 'mime'=>$mime, 'retry_after'=>$retryAfter];
    }
}
