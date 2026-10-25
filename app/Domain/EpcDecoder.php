<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\Problem;

/** GTIN EPC decoding: TDS 2.3, compression 1.0.0. GS1 tables: contracts/gs1/NOTICE.json. */
final class EpcDecoder {
    private string $bits;
    private int $offset = 0;
    private array $tables;
    private const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    private const CODE40 = ' ABCDEFGHIJKLMNOPQRSTUVWXYZ-.:0123456789';

    private function __construct(string $encoded) {
        if (strlen($encoded)>1500) self::invalid();
        $this->bits='';
        if (str_starts_with($encoded,'eh')) {
            $data=substr($encoded,2);
            if ($data==='' || !ctype_xdigit($data) || strtolower($data)!==$data) self::invalid();
            foreach (str_split($data) as $char) $this->bits.=str_pad(decbin(hexdec($char)),4,'0',STR_PAD_LEFT);
        } elseif (str_starts_with($encoded,'ex')) {
            foreach (str_split(substr($encoded,2)) as $char) {
                $value=strpos(self::BASE64,$char); if ($value===false) self::invalid();
                $this->bits.=str_pad(decbin($value),6,'0',STR_PAD_LEFT);
            }
        } else self::invalid();
        $this->tables=json_decode(file_get_contents(ROOT.'/contracts/gs1/epc-tables.json'),true,32,JSON_THROW_ON_ERROR);
    }
    private static function invalid(): never { throw new Problem(400,'epc','Cod EPC invalid sau în afara profilului GTIN declarat.'); }
    private function read(int $count): string {
        if ($count<0 || $this->offset+$count>strlen($this->bits)) self::invalid();
        $value=substr($this->bits,$this->offset,$count); $this->offset+=$count; return $value;
    }
    private function number(int $count): int { $bits=$this->read($count); return $bits===''?0:(int)bindec($bits); }
    private function bcd(int $digits): string {
        $value=''; for ($i=0;$i<$digits;$i++) { $n=$this->number(4); if ($n>9) self::invalid(); $value.=$n; } return $value;
    }
    private static function decimal(string $bits): string {
        $digits=[0];
        foreach (str_split($bits) as $bit) {
            $carry=(int)$bit;
            for ($i=count($digits)-1;$i>=0;$i--) { $n=$digits[$i]*2+$carry; $digits[$i]=$n%10; $carry=intdiv($n,10); }
            if ($carry) array_unshift($digits,$carry);
        }
        return implode('',$digits);
    }
    private static function code40(string $bits): string {
        $out='';
        foreach (str_split($bits,16) as $chunk) {
            if (strlen($chunk)!==16) self::invalid(); $n=(int)bindec($chunk)-1;
            if ($n<0 || $n>=64000) self::invalid();
            $out.=self::CODE40[intdiv($n,1600)].self::CODE40[intdiv($n,40)%40].self::CODE40[$n%40];
        }
        return rtrim($out,' ');
    }
    private function alphanumeric(int $lengthBits,int $max): string {
        $encoding=$this->number(3); $length=$this->number($lengthBits);
        if ($length>$max) self::invalid();
        $width=match($encoding) {0=>(int)ceil($length*log(10,2)),1,2=>$length*4,3=>$length*6,4=>$length*7,5=>(int)ceil($length/3)*16,default=>-1};
        $bits=$this->read($width); $out='';
        if ($length===0) return '';
        if ($encoding===0) $out=str_pad(self::decimal($bits),$length,'0',STR_PAD_LEFT);
        elseif ($encoding===5) $out=self::code40($bits);
        else {
            $size=match($encoding) {1,2=>4,3=>6,4=>7};
            foreach (str_split($bits,$size) as $part) {
                $n=(int)bindec($part);
                $out.=match($encoding) {1=>strtoupper(dechex($n)),2=>dechex($n),3=>self::BASE64[$n],4=>chr($n)};
            }
        }
        if (strlen($out)!==$length) self::invalid(); return $out;
    }
    private function date(): string {
        $year=$this->number(7); $month=$this->number(4); $day=$this->number(5);
        if ($year>99 || !checkdate($month,max(1,$day),2000+$year)) self::invalid();
        return sprintf('%02d%02d%02d',$year,$month,$day);
    }
    private function clock(bool $seconds=false): string {
        $hour=$this->number(5); $minute=$this->number(6); $second=$seconds?$this->number(6):null;
        if ($hour>24 || $minute>59 || ($second!==null && $second>59) || ($hour===24&&($minute!==0||($second!==null&&$second!==0)))) self::invalid();
        return sprintf('%02d%02d',$hour,$minute).($seconds?sprintf('%02d',$second):'');
    }
    private function component(array $row,bool $second=false): string {
        [$section,$chars,$bits,$length,$max]=$second
            ?[$row['j'],(int)($row['k']??0),(int)($row['l']??0),(int)($row['n']??0),(int)($row['o']??90)]
            :[$row['c'],(int)($row['d']??0),(int)($row['e']??0),(int)($row['g']??0),(int)($row['h']??90)];
        switch ($section) {
            case '14.5.2':
                $value=str_pad(self::decimal($this->read($bits)),$chars,'0',STR_PAD_LEFT);
                if (strlen($value)!==$chars) self::invalid(); return $value;
            case '14.5.4': return $this->bcd($chars);
            case '14.5.5':
                $out='';
                for ($i=0;$i<=$max;$i++) {
                    $n=$this->number(4);
                    if ($n<10) $out.=$n;
                    elseif ($n===15) return $out;
                    elseif ($n===14) return $out.$this->alphanumeric($length,$max-strlen($out));
                    else self::invalid();
                } self::invalid();
            case '14.5.6': return $this->alphanumeric($length,$max);
            case '14.5.7': return (string)$this->number(1);
            case '14.5.8': return $this->date();
            case '14.5.9': return $this->date().$this->clock();
            case '14.5.10': $range=$this->number(1); return $this->date().($range?$this->date():'');
            case '14.5.11':
                $precision=$this->number(2); $out=$this->date();
                $h=0; $m=0; $s=0;
                if ($precision!==3) { $h=$this->number(5); if ($h>24) self::invalid(); $out.=sprintf('%02d',$h); }
                if ($precision===1 || $precision===2) { $m=$this->number(6); if ($m>59) self::invalid(); $out.=sprintf('%02d',$m); }
                if ($precision===2) { $s=$this->number(6); if ($s>59) self::invalid(); $out.=sprintf('%02d',$s); }
                if ($h===24&&($m!==0||$s!==0)) self::invalid();
                return $out;
            case '14.5.12':
                $a=$this->number(6); $b=$this->number(6); if($a>25||$b>25)self::invalid();
                return self::BASE64[$a].self::BASE64[$b];
            case '14.5.13':
                $n=$length?$this->number($length):1; if ($n>$max) self::invalid();
                if ($n===0) return '';
                $out=str_pad(self::decimal($this->read((int)ceil($n*log(10,2)))),$n,'0',STR_PAD_LEFT);
                if (strlen($out)!==$n) self::invalid(); return $out;
            case '14.5.14': return $this->number(1)?'-':'';
            case '14.5.15': $a=$this->number(4); $b=$this->number(4); if ($a<1||$a>9||$b<1||$b>9) self::invalid(); return $a.'/'.$b;
            default: self::invalid();
        }
    }
    private function ai(string $ai): string {
        $row=$this->tables['F'][$ai]??null; if (!$row) self::invalid();
        return $this->component($row).(isset($row['j'])?$this->component($row,true):'');
    }
    private function hostname(): string {
        $strategy=$this->number(1); $length=$this->number(6);
        if ($length<1) self::invalid();
        if ($strategy===0) $out=substr(self::code40($this->read((int)ceil($length/3)*16)),0,$length);
        else {
            $out='';
            for ($i=0;$i<$length;$i++) {
                $key=$this->read(7); $row=$this->tables['hostname']['A'][$key]??null;
                if (!$row) self::invalid();
                if (isset($row['d'])) {
                    $key.=$this->read(7); $i++; if ($i>=$length) self::invalid();
                    $row=$this->tables['hostname'][$row['d']][$key]??null;
                    if (!$row) self::invalid();
                }
                $out.=$row['b']??$row['c']??'';
            }
        }
        if (strlen($out)>63 || !preg_match('/\A[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?(?::[0-9]{1,5})?\z/D',$out)) self::invalid();
        return $out;
    }
    public static function decode(string $encoded): array {
        $decoder=new self($encoded); $header=$decoder->number(8); $out=[]; $toggle=0;
        if ($header===0x30 || $header===0x36) {
            $decoder->read(3); $partition=$decoder->number(3);
            $table=[[40,12,4,1],[37,11,7,2],[34,10,10,3],[30,9,14,4],[27,8,17,5],[24,7,20,6],[20,6,24,7]];
            if (!isset($table[$partition])) self::invalid();
            [$cpBits,$cpDigits,$itemBits,$itemDigits]=$table[$partition];
            $prefix=str_pad((string)$decoder->number($cpBits),$cpDigits,'0',STR_PAD_LEFT);
            $item=str_pad((string)$decoder->number($itemBits),$itemDigits,'0',STR_PAD_LEFT);
            if (strlen($prefix)!==$cpDigits||strlen($item)!==$itemDigits) self::invalid();
            $digits=$item[0].$prefix.substr($item,1); $sum=0;
            for ($i=0;$i<13;$i++) $sum+=(int)$digits[$i]*($i%2===0?3:1);
            $out['01']=$digits.((10-$sum%10)%10);
            if ($header===0x30) $out['21']=(string)$decoder->number(38);
            else {
                $serial=''; $ended=false;
                for ($i=0;$i<20;$i++) {
                    $n=$decoder->number(7);
                    if ($n===0) $ended=true;
                    elseif ($ended || $n<32 || $n>126) self::invalid();
                    else $serial.=chr($n);
                }
                $out['21']=$serial;
            }
        } elseif (in_array($header,[0xF7,0xFB,0xFC,0xFD],true)) {
            $toggle=$decoder->number(1); $decoder->read(3);
            if ($header===0xFB || $header===0xFC) {
                $type=$decoder->number(4); $ais=['11','13','15','16','17','7006','7007'];
                if (!isset($ais[$type])) self::invalid();
                $out[$ais[$type]]=$decoder->date();
            }
            $out['01']=$decoder->ai('01'); $out['21']=$decoder->ai('21');
            if ($header===0xFC || $header===0xFD) $out['_hostname']=$decoder->hostname();
        } else self::invalid();
        if ($toggle) {
            for ($count=0;$count<64;$count++) {
                $remaining=substr($decoder->bits,$decoder->offset);
                if ($remaining==='' || (strlen($remaining)<=31 && !str_contains($remaining,'1'))) break;
                $ai=$decoder->bcd(2); $length=$decoder->tables['K'][$ai]??null;
                if ($length===null) self::invalid();
                if ($length>2) $ai.=$decoder->bcd($length-2);
                if (isset($out[$ai])) self::invalid();
                $out[$ai]=$decoder->ai($ai);
            }
        }
        $remaining=substr($decoder->bits,$decoder->offset);
        if (strlen($remaining)>31 || str_contains($remaining,'1')) self::invalid();
        try { DigitalLink::gtin($out['01']); ResolverUri::qualifier('21',$out['21']); } catch (Problem) { self::invalid(); }
        return $out;
    }
}
