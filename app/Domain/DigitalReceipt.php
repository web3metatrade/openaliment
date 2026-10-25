<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Db,Problem};

final class DigitalReceipt {
    public const VERSION='openaliment.receipt.v1';
    public static function validate(array $data): array {
        if(array_diff(array_keys($data),['merchant','receipt_external_id','issued_at','lines','simulation']))self::bad('Câmp de bon neacceptat.');
        $merchant=Catalog::text($data,'merchant',180);$external=Catalog::text($data,'receipt_external_id',120);$issued=Catalog::text($data,'issued_at',30);
        if(!preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/D',$issued))self::bad('Momentul emiterii trebuie să includă fusul orar.');
        try{$time=new \DateTimeImmutable($issued);}catch(\Exception){self::bad('Moment de emitere incorect.');}
        if($time->format('Y-m-d')!==substr($issued,0,10)||$time->format('H:i:s')!==substr($issued,11,8)||(int)$time->format('Y')<2000||(int)$time->format('Y')>2200)self::bad('Moment de emitere incorect.');
        if(!is_array($data['lines']??null)||!array_is_list($data['lines'])||count($data['lines'])<1||count($data['lines'])>500)self::bad('Bonul necesită 1–500 de linii.');
        $lines=[];$seen=[];
        foreach($data['lines']as$line){if(!is_array($line)||array_diff(array_keys($line),['line_id','name','gtin','lot_code','quantity','unit','expiry']))self::bad('Linie de bon incorectă.');
            $id=Catalog::text($line,'line_id',80);if(isset($seen[$id]))self::bad('Identificator de linie repetat.');$seen[$id]=true;
            $gtin=$line['gtin']??null;if($gtin!==null){if(!is_string($gtin))self::bad('GTIN incorect.');$gtin=DigitalLink::gtin($gtin);}
            $lot=Catalog::text($line,'lot_code',120,false);if($lot!==null&&$lot!==$line['lot_code'])self::bad('Lotul nu poate avea spații la capete.');
            $quantity=$line['quantity']??null;$unit=$line['unit']??null;
            if((!is_int($quantity)&&!is_float($quantity))||!is_finite((float)$quantity)||$quantity<=0||$quantity>100000||!in_array($unit,['pack','g','ml'],true))self::bad('Cantitate sau unitate incorectă.');
            if($unit==='pack'&&floor((float)$quantity)!==(float)$quantity)self::bad('Numărul ambalajelor trebuie să fie întreg.');
            $expiry=isset($line['expiry'])?FoodDate::validate($line['expiry']):null;if($expiry!==null)$expiry['source']='receipt';
            $lines[]=['line_id'=>$id,'name'=>Catalog::text($line,'name',180),'gtin14'=>$gtin,'lot_code'=>$lot,'quantity'=>$quantity,'unit'=>$unit,'expiry'=>$expiry];
        }
        if(isset($data['simulation'])&&!is_bool($data['simulation']))self::bad('Stadiu de simulare incorect.');
        return ['schema_version'=>self::VERSION,'merchant'=>$merchant,'receipt_external_id'=>$external,'issued_at'=>$time->format(DATE_ATOM),'lines'=>$lines,'simulation'=>$data['simulation']??false];
    }
    public static function store(array $p,string $connector,array $receipt): array {
        $id=Db::id();$token=bin2hex(random_bytes(32));$receipt['id']=$id;
        Db::insert('consumer_receipts',['id'=>$id,'tenant_id'=>$p['tenant'],'connector_id'=>$connector,'token_hash'=>hash('sha256',$token),'receipt_json'=>Db::json($receipt),'expires_at'=>gmdate('Y-m-d H:i:s',time()+30*86400),'created_at'=>Db::now()]);
        return ['id'=>$id,'receipt_url'=>url('/acasa/#bon='.$token),'expires_in_days'=>30,'simulation'=>$receipt['simulation']];
    }
    public static function preview(string $token): array {
        if(!preg_match('/\A[a-f0-9]{64}\z/D',$token))throw new Problem(404,'receipt','Bonul nu este disponibil.');
        $r=Db::one('SELECT receipt_json FROM consumer_receipts WHERE token_hash=? AND expires_at>?',[hash('sha256',$token),Db::now()]);
        if(!$r)throw new Problem(404,'receipt','Bonul nu este disponibil sau legătura a expirat.');return json_decode($r['receipt_json'],true,64,JSON_THROW_ON_ERROR);
    }
    private static function bad(string $message): never {throw new Problem(422,'receipt',$message);}
}
