<?php
declare(strict_types=1);
namespace App\Core;

/** SMTP only in production; the private test outbox is restricted to the isolated test database. */
final class Mailer {
    public static function ready(): bool {
        $c=self::options();
        if(!is_array($c)||($c['enabled']??false)!==true)return false;
        if(($c['transport']??'smtp')==='test_file')return self::testTransportAllowed();
        if(($c['transport']??'smtp')!=='smtp')return false;
        return is_string($c['host']??null)&&preg_match('/\A[A-Za-z0-9](?:[A-Za-z0-9.-]{0,251}[A-Za-z0-9])?\z/D',$c['host'])
            &&is_string($c['username']??null)&&$c['username']!==''&&is_string($c['password']??null)&&$c['password']!==''
            &&in_array($c['encryption']??'tls',['tls','ssl'],true)&&in_array($c['port']??587,[465,587],true)
            &&filter_var($c['from_address']??'',FILTER_VALIDATE_EMAIL)!==false;
    }
    public static function loginLink(string $email,string $token): void {
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email)||!preg_match('/\A[a-f0-9]{64}\z/D',$token))throw new Problem(422,'email','Adresa de email este incorectă.');
        $base=rtrim((string)Config::get('url'),'/');
        if(Config::get('environment')==='production'&&!str_starts_with($base,'https://'))throw new Problem(503,'email_config','Conexiunea securizată nu este configurată.');
        $link=$base.'/acasa/#conectare='.$token;
        $text="Intră în OpenAliment Acasă\n\nDeschide legătura de mai jos și confirmă conectarea. Este valabilă 15 minute și se poate folosi o singură dată.\n\n".$link."\n\nDacă nu ai cerut această conectare, poți ignora mesajul.\nOpenAliment\n";
        self::send($email,'Legătura ta de conectare · OpenAliment',$text);
    }
    public static function test(string $recipient): void {self::send($recipient,'Verificare email · OpenAliment',"Conexiunea email OpenAliment a trimis acest mesaj de verificare.\n");}
    public static function securityMessage(string $recipient,string $subject,string $text): void {
        if(strlen($subject)>190||preg_match('/[\r\n]/',$subject)||strlen($text)>20000)throw new \InvalidArgumentException('Invalid security message.');self::send($recipient,$subject,$text);
    }
    public static function probe(): array {
        if(!self::ready())throw new Problem(503,'email_unavailable','Configurația SMTP nu este completată.');$c=self::options();
        if(($c['transport']??'smtp')==='test_file')return ['transport'=>'test_file','authenticated'=>false,'email_sent'=>false,'simulated'=>true];
        $mail=self::client($c);try{if(!$mail->smtpConnect())throw new \PHPMailer\PHPMailer\Exception('connection');return ['transport'=>'smtp','tls_verification'=>true,'authenticated'=>true,'email_sent'=>false];}
        catch(\PHPMailer\PHPMailer\Exception){throw new Problem(503,'email_connection','Conexiunea sau autentificarea SMTP nu a reușit. Verifică setările contului pe hosting.');}finally{$mail->smtpClose();}
    }
    private static function send(string $recipient,string $subject,string $text): void {
        if(!self::ready())throw new Problem(503,'email_unavailable','Conectarea prin email nu este disponibilă momentan. Alimentele rămân salvate pe dispozitiv.');
        if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$recipient))throw new Problem(422,'email','Adresa de email este incorectă.');
        $c=self::options();
        if(($c['transport']??'smtp')==='test_file'){
            if(!self::testTransportAllowed())throw new Problem(503,'email_config','Transportul de test nu este permis.');
            $dir=rtrim((string)Config::get('storage'),'/\\').'/private/mail-test';if(!is_dir($dir))mkdir($dir,0700,true);
            $file=$dir.'/'.Db::id().'.json';if(file_put_contents($file,Db::json(['recipient'=>$recipient,'subject'=>$subject,'text'=>$text]),LOCK_EX)===false)throw new Problem(503,'email_unavailable','Mesajul de test nu poate fi păstrat.');@chmod($file,0600);return;
        }
        $mail=self::client($c);
        try{
            $mail->setFrom($c['from_address'],is_string($c['from_name']??null)?$c['from_name']:'OpenAliment');$mail->addAddress($recipient);$mail->Subject=$subject;$mail->Body=$text;$mail->send();
        }catch(\PHPMailer\PHPMailer\Exception){throw new Problem(503,'email_unavailable','Emailul nu a putut fi trimis. Încearcă din nou mai târziu.');}
        finally{$mail->smtpClose();}
    }
    private static function client(array $c): \PHPMailer\PHPMailer\PHPMailer {
        foreach(['Exception','SMTP','PHPMailer']as$class)require_once ROOT.'/app/Vendor/PHPMailer/'.$class.'.php';$mail=new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();$mail->CharSet='UTF-8';$mail->Host=$c['host'];$mail->Port=$c['port']??587;$mail->SMTPAuth=true;$mail->Username=$c['username'];$mail->Password=$c['password'];
        $mail->SMTPSecure=($c['encryption']??'tls')==='ssl'?\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS:\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS=true;$mail->SMTPDebug=0;$mail->Timeout=15;$mail->Timelimit=20;$mail->SMTPOptions=['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]];return $mail;
    }
    private static function options(): array {
        $inline=Config::get('mail');if($inline!==null)return is_array($inline)?$inline:[];
        // Optional SMTP configuration is lazy: even a malformed mail file cannot take down QR pages.
        if(str_contains((string)(Config::get('db',[])['dsn']??''),'dbname=openaliment_tests;'))return [];
        try{$path=ROOT.'/config/mail.local.php';$c=is_file($path)?require $path:[];return is_array($c)?$c:[];}catch(\Throwable){return [];}
    }
    private static function testTransportAllowed(): bool {return Config::get('environment')==='test'&&str_contains((string)(Config::get('db',[])['dsn']??''),'dbname=openaliment_tests;');}
}
