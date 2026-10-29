<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Db,Http,Problem,Migrations,Session};
use App\Domain\Catalog;

final class UpdatesController {
    public static function handle(string $method): void {
        $user=Auth::admin();
        if ($method==='POST') {
            Http::sameOrigin(); Session::verify();
            if (($_POST['action']??'')==='install_program') {\App\Domain\OrganizationRegistry::recentAccount($user['id'],$_POST);\App\Domain\OperationsAssets::install();Migrations::upgradeProgram();\App\Domain\ProgramLearning::seed($user);Session::flash('Programul pentru contribuitori, parteneri și învățare este instalat.');Http::redirect('/admin/program');}
            if (($_POST['action']??'')==='install_anaf') {\App\Domain\OperationsAssets::install();Migrations::upgradeAnaf();Session::flash('Profilul fiscal ANAF este instalat. Înscrierile se activează separat.');Http::redirect('/admin/updates');}
            if (($_POST['action']??'')==='enable_anaf_staging') {\App\Domain\OrganizationRegistry::recentAccount($user['id'],$_POST);\App\Domain\RegistrationSettings::enableStaging();Session::flash('Înregistrarea cu CUI este activă pe staging.');Http::redirect('/admin/updates');}
            if (($_POST['action']??'')==='install_external') {\App\Domain\OperationsAssets::install();Migrations::upgradeExternal(true);Session::flash('Catalogul extern și acordurile de distribuire sunt instalate. Activarea sursei se face separat.');Http::redirect('/admin/sources');}
            if (($_POST['action']??'')==='install_marketplace') { \App\Domain\OperationsAssets::install();Migrations::upgradeMarketplace();Session::flash('Marketplace-ul și tipărirea serializată sunt active.'); }
            elseif (($_POST['action']??'')==='install_resolver') { Migrations::upgradeResolver(); Session::flash('Actualizarea resolverului a fost aplicată.'); }
            elseif (($_POST['action']??'')==='install_readiness') { \App\Domain\OperationsAssets::install();Migrations::upgradeReadiness(); Session::flash('Protecția conturilor, documentelor și operațiunilor este activă.'); }
            elseif (($_POST['action']??'')==='install_operations_assets') { \App\Domain\OperationsAssets::install();Session::flash('Resursele operaționale verificate au fost instalate.'); }
            elseif (($_POST['action']??'')==='install_security') { Migrations::upgradeSecurity();Session::flash('Actualizarea de securitate este activă. Curățarea datelor temporare este disponibilă.'); }
            elseif (($_POST['action']??'')==='cleanup_security') { Auth::rate('cleanup|'.$user['id'],5,3600);\App\Domain\Maintenance::run();Session::flash('Curățarea datelor temporare a fost executată.'); }
            elseif (($_POST['action']??'')==='prepare_backup') { Auth::rate('backup-settings|'.$user['id'],3,3600);\App\Domain\BackupSettings::prepare();Session::flash('Configurația și cheia de backup au fost create în fișiere private separate. Salvează cheia separat prin File Manager, apoi configurează comanda în Cron Jobs.'); }
            elseif (($_POST['action']??'')==='install_api') { Migrations::upgradeApi(); Session::flash('Accesul API și autorizarea aplicațiilor sunt active.'); }
            elseif (($_POST['action']??'')==='install_consumer_assets') { \App\Domain\ConsumerAssets::install(); Session::flash('Citirea locală a datelor din fotografii este activă.'); }
            elseif (($_POST['action']??'')==='install_integrations') { Migrations::upgradeIntegrations(); Session::flash('Conectoarele și bonurile digitale sunt active.'); }
            elseif (($_POST['action']??'')==='install_home') { Migrations::upgradeHome(); Session::flash('Sincronizarea alimentelor și familiile sunt active.'); }
            elseif (($_POST['action']??'')==='prepare_smtp') { \App\Domain\MailSettings::prepare(); Session::flash('Fișierul privat SMTP a fost creat. Completează parola contului direct în config/mail.local.php.'); }
            elseif (($_POST['action']??'')==='probe_smtp') { Auth::rate('smtp-probe|'.$user['id'],5,3600);$probe=\App\Core\Mailer::probe();Session::flash($probe['authenticated']?'Conexiunea securizată și autentificarea SMTP au reușit. Nu a fost trimis niciun email.':'Este activ transportul local de test; autentificarea SMTP reală nu a fost executată.'); }
            elseif (($_POST['action']??'')==='verify_transport'&&Migrations::resolver()) {
                if (!isset($_POST['confirmed'])) throw new Problem(422,'confirmation','Confirmă efectuarea probelor pe domeniul real.');
                try { $evidence=json_decode((string)($_POST['evidence']??''),true,32,JSON_THROW_ON_ERROR); } catch (\JsonException) { throw new Problem(422,'evidence','Raport JSON incorect.'); }
                if (!is_array($evidence)||empty($evidence['checks'])||count($evidence['checks'])>200) throw new Problem(422,'evidence','Raportul trebuie să conțină probele de transport.');
                foreach ($evidence['checks'] as $check) if (!is_array($check)||($check['passed']??false)!==true) throw new Problem(422,'evidence','Toate probele de transport trebuie să fie trecute.');
                Db::run('INSERT INTO resolver_transport (id,verified,verified_at,verified_by,evidence_json) VALUES (1,1,?,?,?) ON DUPLICATE KEY UPDATE verified=1,verified_at=VALUES(verified_at),verified_by=VALUES(verified_by),evidence_json=VALUES(evidence_json)',[Db::now(),$user['id'],Db::json($evidence)]);
                Session::flash('Profilul de transport a fost verificat și înregistrat.');
            } else throw new Problem(422,'action','Operațiune incorectă.');
            Http::redirect('/admin/updates');
        }
        $transport=Migrations::resolver()?Db::one('SELECT verified,verified_at FROM resolver_transport WHERE id=1'):null;
        Http::view('updates',['title'=>'Actualizări platformă','resolverInstalled'=>Migrations::resolver(),'transport'=>$transport,'consumerAssetsReady'=>\App\Domain\ConsumerAssets::ready(),'integrationsInstalled'=>Migrations::integrations(),'homeInstalled'=>Migrations::home(),'smtpFileExists'=>\App\Domain\MailSettings::exists(),'smtpReady'=>\App\Core\Mailer::ready()]);
    }
}
