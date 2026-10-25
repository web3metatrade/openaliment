<?php
declare(strict_types=1);
namespace App\Core;

/** Publicable file types. Historical/imported originals can stay in private archives. */
final class FilePolicy {
    public const TYPES=['application/pdf','text/plain','image/jpeg','image/png','image/webp','application/json'];
    public static function publishable(string $mime): bool {return in_array($mime,self::TYPES,true);}
    public static function jpegWithoutMetadata(string $bytes): string {
        // Lossless JPEG marker filtering. Preserve image samples and non-private
        // colour profiles; remove APP1 (EXIF/XMP), APP13 (IPTC) and COM metadata.
        if(!str_starts_with($bytes,"\xff\xd8"))throw new Problem(422,'home_photo','Fotografia JPEG nu este validă.');
        $out="\xff\xd8";$i=2;$size=strlen($bytes);
        while($i<$size){
            $start=$i;if(ord($bytes[$i++])!==255)throw new Problem(422,'home_photo','Fotografia JPEG nu este validă.');
            while($i<$size&&ord($bytes[$i])===255)++$i;
            if($i>=$size)throw new Problem(422,'home_photo','Fotografia JPEG nu este validă.');$marker=ord($bytes[$i++]);
            if($marker===0||$marker===216)throw new Problem(422,'home_photo','Fotografia JPEG nu este validă.');
            if($marker===217)return $out."\xff\xd9";
            if($marker===218){
                // Metadata may also occur between scans in progressive JPEGs.
                if($i+2>$size)throw new Problem(422,'home_photo','Fotografia JPEG nu este validă.');
                $length=unpack('n',substr($bytes,$i,2))[1];if($length<2||$i+$length>$size)throw new Problem(422,'home_photo','Fotografia JPEG nu este validă.');
                $i+=$length;$out.=substr($bytes,$start,$i-$start);$scan=$i;
                while($i<$size){if(ord($bytes[$i++])!==255)continue;$at=$i-1;while($i<$size&&ord($bytes[$i])===255)++$i;if($i>=$size)break;$next=ord($bytes[$i]);if($next===0||($next>=208&&$next<=215)){++$i;continue;}$out.=substr($bytes,$scan,$at-$scan);$i=$at;continue 2;}
                throw new Problem(422,'home_photo','Fotografia JPEG nu este completă.');
            }
            if($marker===1||($marker>=208&&$marker<=215)){$out.=substr($bytes,$start,$i-$start);continue;}
            if($i+2>$size)throw new Problem(422,'home_photo','Fotografia JPEG nu este validă.');
            $length=unpack('n',substr($bytes,$i,2))[1];if($length<2||$i+$length>$size)throw new Problem(422,'home_photo','Fotografia JPEG nu este validă.');
            $i+=$length;if(!in_array($marker,[225,237,254],true))$out.=substr($bytes,$start,$i-$start);
        }
        throw new Problem(422,'home_photo','Fotografia JPEG nu este completă.');
    }
}
