<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Db,Http,Problem,Session};
use App\Domain\Catalog;
final class AdminController {
    public static function handle(string $method,string $path): void {
        if($path==='/admin/updates'){UpdatesController::handle($method);return;}
        $u=Auth::admin();
        if(preg_match('~\A/admin/organizations/([a-f0-9]{32})\z~D',$path,$m)){
            \App\Core\Api::method($method,['GET','HEAD']);$org=Db::one('SELECT id,display_name,identity_status FROM organizations WHERE id=?',[$m[1]]);
            if(!$org)throw new Problem(404,'not_found','Organizația nu a fost găsită.');
            Http::view('organization-profile-admin',['title'=>'Datele organizației','reviewOrganization'=>$org,'reviewProfile'=>\App\Domain\OrganizationRegistry::profile(['tenant'=>$org['id']])['profile']]);return;
        }
        if($method==='POST'){
            Session::verify();$d=$_POST;$id=Catalog::text($d,'id',32);$reason=Catalog::text($d,'evidence',5000);
            \App\Domain\Administration::decide($u,(string)($d['action']??''),$id,$d);Session::flash('Verificarea a fost înregistrată.');Http::redirect('/admin');
        }
        $orgs=Db::all('SELECT id,display_name,identity_status FROM organizations ORDER BY created_at DESC LIMIT 100');$claims=Db::all('SELECT t.id,t.name,t.gtin14,t.gtin_status,t.claim_evidence,o.display_name FROM trade_items t JOIN organizations o ON o.id=t.tenant_id WHERE t.gtin14 IS NOT NULL ORDER BY t.created_at DESC LIMIT 100');
        $contacts=Db::all('SELECT id,email,display_name,email_verified FROM users WHERE active=1 ORDER BY created_at DESC LIMIT 100');
        Http::view('admin',['contacts'=>$contacts,'title'=>'Administrare platformă','organizations'=>$orgs,'claims'=>$claims]);
    }
}
