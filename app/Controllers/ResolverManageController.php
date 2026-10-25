<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Auth,Http,Problem,Session};
use App\Domain\{Catalog,ResolverRegistry};

final class ResolverManageController {
    public static function handle(string $method,string $itemId): void {
        $p=Auth::browser(); $item=Catalog::owned('trade_items',$itemId,$p);
        if ($method==='POST') {
            Http::sameOrigin(); Session::verify(); $data=$_POST;
            if (($data['action']??'')==='draft') {
                $data['qualifiers']=array_filter($data['qualifiers']??[],static fn($value)=>$value!=='');
                foreach (['types','hreflang','context'] as $key) $data[$key]=array_values(array_filter(array_map('trim',explode(',',$data[$key]??'')),static fn($value)=>$value!==''));
                $data['is_default']=isset($data['is_default']); ResolverRegistry::draft($p,$itemId,$data);
                Session::flash('Destinația a fost salvată în ciornă. Publicarea necesită aprobare.');
            } elseif (in_array($data['action']??'',['publish','withdraw'],true)) {
                $id=Catalog::text($data,'id',32); $row=ResolverRegistry::owned($p,$id);
                if ($row['trade_item_id']!==$itemId) throw new Problem(404,'not_found','Legătura nu a fost găsită.');
                if ($data['action']==='publish') ResolverRegistry::publish($p,$id,['public_projection_confirmed'=>isset($data['public_projection_confirmed'])]);
                else ResolverRegistry::withdraw($p,$id,$data);
                Session::flash('Decizia a fost înregistrată în istoricul destinației.');
            } else throw new Problem(422,'action','Operațiune incorectă.');
            Http::redirect('/manage/resolver/'.$itemId);
        }
        if (!in_array($method,['GET','HEAD'],true)) throw new Problem(405,'method','Metodă nepermisă.');
        $history=ResolverRegistry::history($p,$itemId); $editing=null;
        if (isset($_GET['edit'])) {
            foreach ($history as $row) if ($row['link_key']===$_GET['edit']&&($editing===null||(int)$row['version_no']>(int)$editing['version_no'])) $editing=$row;
            if (!$editing) throw new Problem(404,'not_found','Legătura nu a fost găsită.');
        }
        Http::view('resolver-manage',['title'=>'Destinații GS1 Digital Link','item'=>$item,'p'=>$p,'history'=>$history,'editing'=>$editing]);
    }
}
