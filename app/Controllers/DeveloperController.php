<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Api,Auth,Db,Http,Problem,Session};
use App\Domain\{Catalog,OAuth};

final class DeveloperController {
    public static function handle(string $method): void {
        $p=Auth::principal();if($p['token']!==null)throw new Problem(403,'browser_session','Folosește contul în browser.');Api::installed();
        Api::method($method,['GET','HEAD','POST']);
        if($method==='POST'){
            Http::sameOrigin();Session::verify();$d=$_POST;$action=$d['action']??'';
            if($action==='client.create'){
                $d['redirect_uris']=array_values(array_filter(array_map('trim',preg_split('/\R/',(string)($d['redirect_uris']??'')))));OAuth::clientCreate($p,$d);Session::flash('Aplicația a fost înregistrată.');
            }elseif($action==='client.suspend'){
                $id=Api::id(Catalog::text($d,'id',32));if(!Db::one('SELECT id FROM api_clients WHERE id=? AND owner_user_id=?',[$id,$p['user']]))throw new Problem(404,'not_found','Aplicația nu a fost găsită.');
                Db::run('UPDATE api_clients SET active=0 WHERE id=?',[$id]);Catalog::audit($p,'oauth.client_suspended',$id);Session::flash('Aplicația a fost suspendată.');
            }elseif($action==='grant.revoke'){
                $id=Api::id(Catalog::text($d,'id',32));if(!Db::one('SELECT id FROM api_oauth_grants WHERE id=? AND user_id=?',[$id,$p['user']]))throw new Problem(404,'not_found','Autorizarea nu a fost găsită.');
                Db::atomic(fn()=>OAuth::revokeGrant($id));Session::flash('Accesul aplicației a fost retras.');
            }else throw new Problem(422,'action','Operațiune incorectă.');Http::redirect('/manage/developers');
        }
        Http::view('developers',['title'=>'Aplicații și autorizări','clients'=>OAuth::clients($p)['items'],'grants'=>Db::all('SELECT g.id,c.name,g.scopes_json,g.tenant_id,g.expires_at,g.revoked_at FROM api_oauth_grants g JOIN api_clients c ON c.id=g.client_id WHERE g.user_id=? ORDER BY g.created_at DESC LIMIT 100',[$p['user']])]);
    }
}
