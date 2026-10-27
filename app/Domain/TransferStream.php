<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Db,Problem};

/** Bounded NDJSON ingestion with exact transport digest, before any domain write. */
final class TransferStream {
    public const MAX_BYTES=24000000;
    private const TABLES=['products','trade_items','product_info_versions','recipe_versions','batches','batch_events','alerts','documents','change_proposals','url_mappings','imports','resolver_links','integration_connectors','integration_mappings','integration_bindings','feedback','audit_events','external_sources'];
    public static function read($stream): array {
        if(!is_resource($stream))throw new Problem(400,'stream','Fluxul nu este disponibil.');$header=null;$data=array_fill_keys(self::TABLES,[]);$hash=hash_init('sha256');$bytes=0;$records=0;$ended=false;
        while(($line=fgets($stream,self::MAX_BYTES+2))!==false){$bytes+=strlen($line);if($bytes>self::MAX_BYTES)throw new Problem(413,'body_size','Importul NDJSON depășește 24 MB.');if($ended||!str_ends_with($line,"\n"))throw new Problem(422,'stream','Fluxul conține date după final sau o linie incompletă.');
            try{$value=json_decode($line,true,64,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(422,'json','Linie NDJSON incorectă.');}if(!is_array($value)||array_is_list($value))throw new Problem(422,'stream','Fiecare linie trebuie să fie un obiect.');
            if(($value['end']??false)===true){if(!$header||($value['records']??null)!==$records||!is_string($value['sha256']??null)||!hash_equals(hash_final($hash),$value['sha256']))throw new Problem(422,'checksum','Finalul sau amprenta fluxului nu corespunde.');$ended=true;continue;}
            hash_update($hash,$line);
            if($header===null){if(($value['schema_version']??'')!=='openaliment.transfer.stream.v1'||!is_string($value['source_base_url']??null))throw new Problem(422,'schema','Antet NDJSON nerecunoscut.');$header=$value;continue;}
            $table=$value['table']??null;if(!is_string($table)||!in_array($table,self::TABLES,true)||!is_array($value['row']??null)||array_is_list($value['row']))throw new Problem(422,'stream','Înregistrare NDJSON nerecunoscută.');if(count($data[$table])>=10000)throw new Problem(413,'size','Colecția importată depășește 10.000 de rânduri.');$data[$table][]=$value['row'];$records++;
        }
        if(!$header||!$ended)throw new Problem(422,'stream','Fluxul NDJSON este incomplet.');
        return ['schema_version'=>'openaliment.transfer.v1','exported_at'=>$header['exported_at']??null,'source_base_url'=>$header['source_base_url'],'organization'=>$header['organization']??null,'data'=>$data,'data_sha256'=>hash('sha256',Db::json($data))];
    }
}
