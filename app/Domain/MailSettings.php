<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Mailer,Problem};

/** Installs only the operator-approved, password-free SMTP template at one fixed private path. */
final class MailSettings {
    public static function exists(): bool {return is_file(ROOT.'/config/mail.local.php');}
    public static function template(): string {
        $c=['enabled'=>true,'transport'=>'smtp','host'=>'mail.openaliment.ro','port'=>465,'encryption'=>'ssl','username'=>'email@openaliment.ro','password'=>'','from_address'=>'email@openaliment.ro','from_name'=>'OpenAliment'];
        return "<?php\ndeclare(strict_types=1);\n// Private configuration. Set the mailbox password directly on the hosting account.\nreturn ".var_export($c,true).";\n";
    }
    public static function prepare(): void {
        $path=ROOT.'/config/mail.local.php';if(file_exists($path)||is_link($path))throw new Problem(409,'mail_config_exists','Fișierul SMTP există deja și a fost păstrat.');
        $handle=@fopen($path,'x');if(!$handle)throw new Problem(503,'mail_config_write','Fișierul privat SMTP nu a putut fi creat.');$ok=false;
        try{@chmod($path,0600);if(!flock($handle,LOCK_EX))throw new Problem(503,'mail_config_write','Fișierul privat SMTP nu a putut fi creat.');$text=self::template();if(fwrite($handle,$text)!==strlen($text)||!fflush($handle))throw new Problem(503,'mail_config_write','Fișierul privat SMTP nu a putut fi creat.');$ok=true;}
        finally{fclose($handle);if(!$ok&&is_file($path))unlink($path);}
    }
}
