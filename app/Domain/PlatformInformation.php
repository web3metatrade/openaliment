<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\Config;

/** Public project information; independent deployments keep their own identity. */
final class PlatformInformation {
    public static function privacy(): array {
        $operator=Config::get('operator_name');
        $text=Config::get('privacy_text');
        $version='privacy-2026-10-24';
        // Bind this project's approved public copy to its configured origin,
        // never to a visitor-controlled Host header or a producer organization.
        if(rtrim((string)Config::get('url'),'/')==='https://openaliment.ro'){
            $operator='OpenAliment';
            $text='OpenAliment este un proiect în formare. Organizația neguvernamentală este în curs de înființare, iar platforma nu operează comercial în această etapă. Alex Patrut este fondatorul și persoana de contact a inițiativei.';
            $version='privacy-2026-10-25';
        }
        return ['version'=>$version,'url'=>url('/confidentialitate'),'operator'=>$operator,'contact'=>Config::get('operator_contact'),'operator_text'=>$text,'local_only'=>['allergen_preferences','shopping_list'],'optional_sync'=>['packages','photos','history']];
    }
}
