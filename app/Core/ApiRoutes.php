<?php
declare(strict_types=1);
namespace App\Core;

/** Shared source of truth for new HTTP routing and its generated OpenAPI. */
final class ApiRoutes {
    private static ?array $cache=null;
    public static function all(): array {
        if(self::$cache!==null)return self::$cache;
        $routes=[];
        $add=static function(string $method,string $path,string $id,string $auth,string $scope,string $handler,string $summary,string $body='')use(&$routes):void{
            $routes[]=compact('method','path','id','auth','scope','handler','summary','body');
        };
        $add('GET','/api/v2/public/standards/gs1','gs1StandardsCatalog','public','','standards.catalog','Citește edițiile oficiale GS1, proveniența și hashurile.');
        $add('GET','/api/v2/admin/standards/gs1','gs1StandardsStatus','admin-session','','standards.status','Verifică integritatea copiilor private ale standardelor GS1.');
        $add('POST','/api/v2/admin/standards/gs1/copies','gs1StandardsPreserve','admin-session','','standards.preserve','Păstrează copia privată a unei ediții oficiale cu hash fix.','Gs1Preserve');
        foreach([
            ['GET','/api/v2/public/marketplace/apps','marketplaceCatalog','public','','catalog','Citește ofertele de integrări aprobate.',''],
            ['GET','/api/v2/public/marketplace/apps/{id}','marketplacePublicApp','public','','publicApp','Citește oferta aprobată și versiunile publice.',''],
            ['GET','/api/v2/public/marketplace/releases/{id}/download','marketplaceDownload','public','','download','Deschide pachetul gratuit la dezvoltator după verificarea stării ofertei.',''],
            ['GET','/api/v2/developer/marketplace/apps','marketplaceOwnApps','session','','own','Listează propriile oferte, ciorne și versiuni.',''],
            ['POST','/api/v2/developer/marketplace/apps','marketplaceCreateApp','session','','save','Creează oferta privată a dezvoltatorului.','MarketplaceListing'],
            ['PUT','/api/v2/developer/marketplace/apps/{id}','marketplaceUpdateApp','session','','save','Salvează o revizie fără înlocuirea ofertei aprobate.','MarketplaceListing'],
            ['POST','/api/v2/developer/marketplace/apps/{id}/submit','marketplaceSubmitApp','session','','submit','Trimite revizia curentă pentru aprobare.','Empty'],
            ['POST','/api/v2/developer/marketplace/apps/{id}/releases','marketplaceCreateRelease','session','','release','Înregistrează manifestul imuabil al unei versiuni.','MarketplaceRelease'],
            ['GET','/api/v2/developer/marketplace/apps/{id}/releases','marketplaceOwnReleases','session','','releases','Parcurge toate versiunile proprii cu cursor.',''],
            ['POST','/api/v2/developer/marketplace/apps/{id}/withdraw','marketplaceWithdraw','session','','withdraw','Retrage imediat oferta sau o versiune proprie.','MarketplaceWithdrawal'],
            ['GET','/api/v2/admin/marketplace/apps','marketplaceReviewQueue','admin-session','','queue','Citește ofertele și versiunile pentru verificare.',''],
            ['POST','/api/v2/admin/marketplace/apps/{id}/decisions','marketplaceReview','admin-session','','review','Aprobă revizia sau versiunea explicită ori suspendă oferta.','MarketplaceReview'],
            ['POST','/api/v2/manage/marketplace/apps/{id}/selection','marketplaceSelect','producer','write','select','Salvează selecția organizației fără acordarea accesului API.','MarketplaceSelection'],
            ['GET','/api/v2/manage/marketplace/selections','marketplaceSelections','producer','read','selections','Parcurge integrările selectate de organizație.',''],
            ['GET','/api/v2/manage/printing/devices','printDevices','producer','read','devices','Listează echipamentele fără credentiale.',''],
            ['POST','/api/v2/manage/printing/devices','printCreateDevice','owner-session','','device','Înregistrează echipamentul și afișează o singură dată secretul agentului.','PrintDevice'],
            ['POST','/api/v2/manage/printing/devices/{id}/rotate','printRotateDevice','owner-session','','device','Înlocuiește imediat credentialul echipamentului.','PrintDevice'],
            ['POST','/api/v2/manage/printing/devices/{id}/revoke','printRevokeDevice','owner-session','','revoke','Deconectează agentul echipamentului.','Empty'],
            ['GET','/api/v2/manage/printing/jobs','printJobs','producer','read','jobs','Listează comenzile de tipărire ale organizației.',''],
            ['GET','/api/v2/manage/printing/units','printUnits','producer','read','units','Exportă prin paginare identitățile și URL-urile originale ale ambalajelor.',''],
            ['GET','/api/v2/manage/printing/jobs/{id}','printJob','producer','read','job','Citește seriile, snapshoturile și istoricul comenzii.',''],
            ['POST','/api/v2/manage/printing/jobs','printCreateJob','producer','write','createJob','Alocă serii distincte și creează o comandă de tipărire.','PrintJob'],
            ['POST','/api/v2/manage/printing/jobs/{id}/cancel','printCancelJob','producer','write','cancel','Anulează exclusiv o comandă încă nepreluată.','PrintReason'],
            ['POST','/api/v2/manage/printing/jobs/{id}/reprint','printReprintJob','producer','publish','reprint','Retipărește explicit aceleași serii cu motiv și istoric.','PrintReprint'],
            ['POST','/api/v2/printing-agent/claims','printAgentClaim','print-agent','','claim','Preia o comandă exclusiv pentru echipamentul credentialului.','Empty'],
            ['POST','/api/v2/printing-agent/jobs/{id}/acknowledgements','printAgentAcknowledge','print-agent','','ack','Confirmă expedierea sau rezultatul echipamentului, separat.','PrintAcknowledgement']
        ]as[$method,$path,$id,$auth,$scope,$handler,$summary,$body])$add($method,$path,$id,$auth,$scope,'marketplace.'.$handler,$summary,$body);
        $add('GET','/api/v2/account/security','accountSecurity','session','','account.security','Citește cheile de acces și sesiunile proprii.');
        foreach(['request'=>'AccountEmail','preview'=>'AccountToken','reset'=>'AccountReset']as$action=>$body)$add('POST','/api/v2/account/password/'.$action,'accountPassword'.ucfirst($action),'session-optional','','account.recovery','Recuperează parola fără dezactivarea cheilor de acces.',$body);
        foreach(['create','get']as$kind)foreach(['options','confirm']as$step)$add('POST','/api/v2/account/passkeys/'.$kind.'/'.$step,'accountPasskey'.ucfirst($kind).ucfirst($step),'session','','account.passkey','Înregistrează sau verifică o cheie de acces.',$step==='options'?($kind==='create'?'PasskeyCreateOptions':'Empty'):($kind==='create'?'PasskeyRegistration':'PasskeyAssertion'));
        $add('POST','/api/v2/account/passkeys/recover','accountPasskeyRecover','session','','account.passkey','Folosește o singură dată un cod de recuperare.','RecoveryCode');
        $add('POST','/api/v2/account/passkeys/recovery-codes','accountRecoveryCodes','session','','account.passkey','Înlocuiește codurile de recuperare; sunt afișate o singură dată.','Empty');
        $add('POST','/api/v2/account/passkeys/{id}/remove','accountPasskeyRemove','session','','account.passkey','Elimină o cheie proprie, păstrând cel puțin o cheie verificată.','Empty');
        $add('POST','/api/v2/account/sessions/{id}/revoke','accountSessionRevoke','session','','account.session','Deconectează o sesiune proprie.','Empty');
        $add('GET','/api/v2/admin/operations','adminOperations','admin-session','','admin.operations','Citește starea workerului, emailului, backupului și configurației.');
        $add('GET','/api/v2/manage/documents/{id}/scan','manageDocumentScanStatus','producer','read','manage.documentScan','Citește verificarea de securitate a documentului.');
        $add('POST','/api/v2/manage/documents/{id}/scan','manageDocumentScan','producer','publish','manage.documentScan','Verifică documentul prin scanerul privat configurat.','Empty');
        foreach(['trade-items','products','batches','labels','recipe-versions','documents','proposals']as$r)$add('GET',"/api/v2/public/$r/{id}",'publicGet'.str_replace(' ','',ucwords(str_replace('-',' ',$r))),'public','','public.get','Citește proiecția publică aprobată.');
        $add('GET','/api/v2/public/trade-items','publicListTradeItems','public','','public.catalog','Caută și parcurge articolele publicate.');
        foreach(['trade-items','recipe-versions','proposals']as$r)$add('GET',"/api/v2/public/products/{id}/$r",'publicProduct'.str_replace(' ','',ucwords(str_replace('-',' ',$r))),'public','','public.children','Listează resursele publice ale produsului.');
        $add('GET','/api/v2/public/batches/{id}/recipe-versions','publicBatchRecipes','public','','public.children','Listează exclusiv rețeta publică asociată lotului.');
        $add('GET','/api/v2/public/batches/{id}/alerts','publicBatchAlerts','public','','public.children','Citește alertele active ale lotului.');
        $add('GET','/api/v2/public/labels/{id}/documents','publicLabelDocuments','public','','public.children','Listează documentele publice ale versiunii istorice.');
        $add('GET','/api/v2/public/trade-items/{id}/feedback','publicListFeedback','public','','public.feedback','Listează copiile moderate pentru versiunea și lotul selectate.');
        $add('GET','/api/v2/public/trade-items/{id}/ratings','publicRatings','public','','public.ratings','Citește mediile, numărul de răspunsuri și distribuția notelor publicate.');
        $add('GET','/api/v2/public/documents/{id}/content','publicDownloadDocument','public','','public.download','Descarcă documentul public.');
        $add('GET','/api/v2/public/recipe-versions/{id}/archive','publicRecipeArchive','public','','public.archive','Descarcă documentația publică licențiată într-o arhivă autonomă.');
        $add('GET','/api/v2/public/policies/privacy','publicPrivacyPolicy','public','','public.vocabulary','Citește informarea de confidențialitate.');
        foreach(['licenses','allergens','nutrition','link-types','units']as$r)$add('GET','/api/v2/public/vocabularies/'.$r,'publicVocabulary'.ucfirst(str_replace('-','',$r)),'public','','public.vocabulary','Citește dicționarul și valorile acceptate.');
        $add('POST','/api/v2/public/food-lookups','publicFoodLookupBatch','public','','public.lookup','Caută maximum 20 coduri publice; fiecare cod consumă o unitate din cotă.','FoodLookups');
        foreach(['parse','validate']as$r)$add('POST','/api/v2/tools/identifiers/'.$r,'identifier'.ucfirst($r),'public','','public.identifier','Interpretează codul fără verificarea mandatului GS1.','Identifier');
        $add('POST','/api/v2/community/feedback','communitySubmitFeedback','public','','community.feedback','Trimite feedback cu acord și cheie de deduplicare.','Feedback');
        $add('GET','/api/v2/community/me/votes','communityMyVotes','producer','community','community.votes','Citește voturile proprii.');
        $add('PUT','/api/v2/community/proposals/{id}/vote','communityVote','producer','community','community.vote','Susține propunerea publică, un singur vot per persoană.','VoteMutation');
        $add('DELETE','/api/v2/community/proposals/{id}/vote','communityRemoveVote','producer','community','community.vote','Retrage votul propriu.');
        foreach(['products','trade-items','labels','batches','recipes','documents','feedback','proposals','imports','audit-events','ai-jobs']as$r){
            $name=str_replace(' ','',ucwords(str_replace('-',' ',$r)));
            $add('GET',"/api/v2/manage/$r",'manageList'.$name,'producer','read','manage.list','Listează colecția autorizată cu cursor.');
            $add('GET',"/api/v2/manage/$r/{id}",'manageGet'.$name,'producer','read','manage.get','Citește resursa organizației curente.');
        }
        $add('GET','/api/v2/manage/dashboard','manageDashboard','producer','read','manage.dashboard','Citește indicatorii organizației.');
        $add('GET','/api/v2/manage/organization','manageOrganization','producer','read','manage.organization','Citește organizația și rolul curent.');
        $add('GET','/api/v2/manage/quotas','manageQuotas','producer','read','manage.quotas','Citește volumele și spațiul alocate organizației, fără posibilitate de mărire prin API.');
        foreach(['trade-items','documents','recipes','proposals']as$r)$add('GET',"/api/v2/manage/products/{id}/$r",'manageProduct'.str_replace(' ','',ucwords(str_replace('-',' ',$r))),'producer','read','manage.children','Listează resursele produsului.');
        foreach(['labels','batches','resolver-links']as$r)$add('GET',"/api/v2/manage/trade-items/{id}/$r",'manageItem'.str_replace(' ','',ucwords(str_replace('-',' ',$r))),'producer','read','manage.children','Listează versiunile articolului.');
        foreach(['events','alerts']as$r)$add('GET',"/api/v2/manage/batches/{id}/$r",'manageBatch'.ucfirst($r),'producer','read','manage.children','Listează istoricul sau alertele lotului.');
        foreach(['decisions','ai-results']as$r)$add('GET',"/api/v2/manage/feedback/{id}/$r",'manageFeedback'.str_replace('-','',ucwords($r,'-')),'producer','read','manage.children','Citește deciziile sau ciornele AI autorizate.');
        $add('GET','/api/v2/manage/proposals/{id}/decisions','manageProposalDecisions','producer','read','manage.children','Citește istoricul deciziilor.');
        $add('GET','/api/v2/manage/documents/{id}/content','manageDownloadDocument','producer','read','manage.download','Descarcă documentul privat autorizat.');
        $add('GET','/api/v2/manage/labels/{id}/public-preview','manageLabelPreview','producer','read','manage.preview','Previzualizează snapshotul publicabil fără publicare.');
        $add('POST','/api/v2/manage/label-validations','manageValidateLabel','producer','write','manage.validate','Validează eticheta fără salvare.','Label');
        $add('POST','/api/v2/manage/products/{id}/documents','manageUploadDocument','producer','write','manage.upload','Încarcă privat un document multipart, maximum 15 MB.','Upload');
        foreach([
            ['products','product.create','write','Product'],['products/{id}/trade-items','trade.create','write','TradeItem'],
            ['trade-items/{id}/labels','label.create','write','Label'],['labels/{id}/publish','label.publish','publish','Empty'],
            ['trade-items/{id}/batches','batch.create','write','Batch'],['batches/{id}/publish','batch.publish','publish','Empty'],
            ['batches/{id}/release','batch.release','publish','Empty'],['batches/{id}/alerts','alert.create','alerts','Alert'],
            ['alerts/{id}/resolve','alert.resolve','alerts','Resolution'],['products/{id}/recipes','recipe.create','write','Recipe'],
            ['products/{id}/proposals','proposal.create','write','Proposal'],['proposals/{id}/decide','proposal.decide','publish','Decision'],
            ['documents/{id}/publish','document.publish','publish','DocumentPublication'],['feedback/{id}/moderate','feedback.moderate','publish','Moderation'],
            ['trade-items/{id}/resolver-links','resolver.draft','write','ResolverLink'],['resolver-links/{id}/publish','resolver.publish','publish','ResolverApproval'],
            ['resolver-links/{id}/withdraw','resolver.withdraw','publish','ResolverWithdrawal']
        ]as[$path,$action,$scope,$schema])$add('POST','/api/v2/manage/'.$path,'manage'.str_replace('.','',ucwords($action,'.')),'producer',$scope,'mutation:'.$action,'Execută operația explicită, cu rol și cheie de idempotentă.',$schema);
        foreach(['item','batch']as$kind)$add('GET','/api/v2/manage/qr/'.$kind.'/{id}','manageQr'.ucfirst($kind),'producer','read','manage.qr','Obține URL-ul stabil pentru QR.');
        $add('GET','/api/v2/manage/export','manageExport','producer','export','manage.export','Exportă JSON sau NDJSON cu istoric și documente.');
        $add('POST','/api/v2/manage/import-stream','manageImportStream','producer','import','manage.importStream','Verifică și importă un export NDJSON cu digest final.');
        foreach(['import','import-pbt']as$r)$add('POST','/api/v2/manage/'.$r,'manage'.ucfirst(str_replace('-','',$r)),'producer','import','manage.import','Verifică sau importă date private; dry_run este implicit true.','Import');
        foreach(['events','bindings']as$r)$add('GET','/api/v2/manage/connectors/{id}/'.$r,'manageConnector'.ucfirst($r),'producer','read','manage.connector','Citește evenimentele sau mapările identităților externe.');
        $add('GET','/api/v2/manage/connectors/{id}','manageConnector','producer','read','manage.connector','Citește conectorul propriu.');
        $add('GET','/api/v2/manage/ai/capabilities','manageAiCapabilities','producer','read','manage.ai','Citește disponibilitatea AI fără secrete.');
        $add('POST','/api/v2/manage/ai-jobs','manageQueueAiJob','producer','publish','manage.ai','Programează trierea feedbackului public moderat; nu execută AI în cererea HTTP.','AiJob');
        $add('GET','/api/v2/manage/access-tokens','manageListAccessTokens','owner-session','','keys.list','Listează metadatele cheilor, fără secrete.');
        $add('POST','/api/v2/manage/access-tokens','manageCreateAccessToken','owner-session','','keys.create','Emite o cheie; secretul este returnat o singură dată.','ApiKey');
        $add('DELETE','/api/v2/manage/access-tokens/{id}','manageRevokeAccessToken','owner-session','','keys.revoke','Revocă imediat cheia organizației.');
        $add('POST','/api/v2/manage/access-tokens/{id}/rotations','manageRotateAccessToken','owner-session','','keys.rotate','Înlocuiește cheia, păstrează scopes și revocă cheia veche.','ApiKeyRotation');
        $add('GET','/api/v2/auth/token','inspectAccessToken','producer','','token.inspect','Inspectează identitatea și scopes ale credentialului curent.');
        $add('DELETE','/api/v2/auth/token','revokeCurrentToken','producer','','token.revoke','Revocă propriul credential Bearer.');
        foreach(['GET','POST']as$method)$add($method,'/api/v2/developer/apps','developer'.($method==='GET'?'List':'Create').'Apps','session','','developer.apps','Administrează aplicațiile publice OAuth.',$method==='POST'?'OAuthClient':'');
        $add('POST','/api/v2/developer/apps/{id}/suspend','developerSuspendApp','session','','developer.suspend','Suspendă aplicația și invalidează toate granturile ei.','Empty');
        $add('GET','/api/v2/identity/session','identitySession','public','','identity.session','Citește sesiunea web și tokenul CSRF, fără privilegii implicite.');
        $add('POST','/api/v2/identity/sessions','identityLogin','session-optional','','identity.login','Conectează contul producătorului în browserul propriu.','Login');
        $add('DELETE','/api/v2/identity/sessions/current','identityLogout','session','','identity.logout','Închide sesiunea web curentă.');
        $add('POST','/api/v2/identity/password','identityChangePassword','session','','identity.password','Schimbă parola și revocă celelalte sesiuni și accesuri ale contului.','PasswordChange');
        $add('POST','/api/v2/identity/access-revocations','identityRevokeOtherAccess','session','','identity.access-revoke','Retrage celelalte sesiuni și credentialele contului după confirmarea parolei.','AccessRevocation');
        $add('POST','/api/v2/identity/registrations','identityRegister','session-optional','','identity.register','Înregistrează cont și organizație dacă înscrierile sunt active.','Registration');
        $add('GET','/api/v2/identity/organizations','identityOrganizations','session','','identity.organizations','Listează organizațiile contului propriu.');
        $add('POST','/api/v2/identity/organization-selection','identitySelectOrganization','session','','identity.select','Schimbă organizația activă după verificarea membership.','OrganizationSelection');
        $add('GET','/api/v2/identity/grants','identityListGrants','session','','identity.grants','Listează aplicațiile autorizate de producător.');
        $add('DELETE','/api/v2/identity/grants/{id}','identityRevokeGrant','session','','identity.grants','Retrage accesul unei aplicații autorizate.');
        foreach(['organizations','users','gtin-claims']as$r)$add('GET','/api/v2/admin/'.$r,'adminList'.str_replace('-','',ucwords($r,'-')),'admin-session','','admin.list','Listează resursele de verificat; exclusiv administrator platformă.');
        foreach([['organizations/{id}/identity-reviews','organization.verify'],['users/{id}/contact-reviews','contact.verify'],['gtin-claims/{id}/decisions','gtin.review']]as[$path,$action])$add('POST','/api/v2/admin/'.$path,'admin'.str_replace('.','',ucwords($action,'.')),'admin-session','','admin:'.$action,'Înregistrează o decizie atribuită administratorului.','AdminReview');
        return self::$cache=$routes;
    }
    public static function methods(string $path): array {
        $methods=[];foreach(['GET','POST','PUT','DELETE','PATCH']as$method)if(self::find($method,$path))$methods[]=$method;
        if(in_array('GET',$methods,true))$methods[]='HEAD';if($methods)$methods[]='OPTIONS';return $methods;
    }
    public static function find(string $method,string $path): ?array {
        foreach(self::all()as$route){if($route['method']!==$method)continue;
            $pattern='~\A'.str_replace('\{id\}','([a-f0-9]{32})',preg_quote($route['path'],'~')).'\z~D';
            if(preg_match($pattern,$path,$m))return $route+['resource_id'=>$m[1]??''];
        }return null;
    }
}
