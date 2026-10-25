<?php
declare(strict_types=1);
namespace App\Domain;
use App\Core\{Auth,Config,Db,Problem,Migrations};

final class ResolverRegistry {
    public const NS='https://ref.gs1.org/voc/';
    public static function type(string $type): string {
        if (str_starts_with($type,'gs1:')) $type=self::NS.substr($type,4);
        if (preg_match('~\Ahttps://ref\.gs1\.org/voc/[A-Za-z][A-Za-z0-9]*\z~D',$type)) return $type;
        if (in_array($type,['alternate','describedby','license','help','service','related','item','collection','about'],true)) return $type;
        throw new Problem(422,'link_type','Tip de legătură incorect. Folosește un tip GS1 sau o relație IANA acceptată.');
    }
    public static function qualifiers(mixed $value): array {
        if ($value===null) return [];
        if (!is_array($value)) throw new Problem(422,'qualifiers','Calificatorii trebuie să fie un obiect.');
        $out=[];
        foreach ($value as $key=>$v) {
            $ai=(string)$key;
            if (!is_string($v)) throw new Problem(422,'qualifiers','Valoarea calificatorului trebuie să fie text.');
            try { ResolverUri::qualifier($ai,$v); } catch (Problem $e) { throw new Problem(422,'qualifiers',$e->getMessage()); }
            $out[$ai]=$v;
        }
        if ((isset($out['21'])||isset($out['235']))&&count($out)>1) throw new Problem(422,'qualifiers','Seria sau TPX se înregistrează separat de variantă și lot.');
        $ordered=[]; foreach (['22','10','21','235'] as $ai) if (isset($out[$ai])) $ordered[$ai]=$out[$ai];
        return $ordered;
    }
    private static function strings(mixed $value,int $max,int $count): array {
        if (!is_array($value)||!array_is_list($value)||count($value)>$count) throw new Problem(422,'metadata','Lista de metadate este incorectă.');
        $out=[]; foreach ($value as $entry) { if (!is_string($entry)||strlen($entry)>$max||$entry===''||preg_match('/[\x00-\x20\x7F]/',$entry)) throw new Problem(422,'metadata','Metadate incorecte.'); $out[]=$entry; }
        return array_values(array_unique($out));
    }
    public static function target(array $data): array {
        $href=Catalog::text($data,'href',2048); $parts=parse_url($href);
        if (!filter_var($href,FILTER_VALIDATE_URL)||!$parts||!in_array(strtolower($parts['scheme']??''),['http','https'],true)||isset($parts['user'])||isset($parts['pass'])||preg_match('/[\x00-\x20\x7F]/',$href)) throw new Problem(422,'destination','Destinația trebuie să fie o adresă web completă, fără date de autentificare.');
        $host=strtolower($parts['host']??'');
        if ($host==='localhost'||str_ends_with($host,'.localhost')||(filter_var($host,FILTER_VALIDATE_IP)&&!filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))) throw new Problem(422,'destination','Destinația trebuie să fie publică.');
        if ($host===strtolower((string)parse_url(Config::get('url'),PHP_URL_HOST)) && preg_match('~\A/(?:01/|eh|ex|manage(?:/|$)|admin(?:/|$)|setup(?:/|$)|api/v1/manage|openaliment-connect\.php)~',$parts['path']??'')) throw new Problem(422,'destination','Alege o pagină publică de conținut, fără buclă de resolver sau pagină de administrare.');
        $types=self::strings($data['types']??['gs1:pip'],160,12); if (!$types) throw new Problem(422,'link_type','Alege cel puțin un tip de legătură.');
        $types=array_values(array_unique(array_map(self::type(...),$types)));
        $default=in_array(self::NS.'defaultLink',$types,true)||($data['is_default']??false)===true;
        $types=array_values(array_diff($types,[self::NS.'defaultLink']));
        if (!$types || $types===[self::NS.'defaultLinkMulti']) throw new Problem(422,'link_type','Destinația necesită și un tip descriptiv.');
        $languages=self::strings($data['hreflang']??[],35,10);
        foreach ($languages as $language) if (!preg_match('/\A(?:[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*|[ix](?:-[a-zA-Z0-9]{1,8})+)\z/D',$language)) throw new Problem(422,'language','Cod de limbă incorect.');
        $contexts=self::strings($data['context']??[],80,12);
        $media=Catalog::text($data,'type',100,false);
        if ($media&&!preg_match('~\A[a-zA-Z0-9!#$&^_.+-]+/[a-zA-Z0-9!#$&^_.+-]+\z~D',$media)) throw new Problem(422,'media_type','Tip media incorect.');
        if($media){$media=strtolower($media);$registered=json_decode(file_get_contents(ROOT.'/contracts/gs1/iana-media-types.json'),true,16,JSON_THROW_ON_ERROR);if(!in_array($media,$registered['types'],true))throw new Problem(422,'media_type','Folosește un tip media înregistrat la IANA.');}
        if ($default&&($languages||$contexts||$media||in_array(self::NS.'defaultLinkMulti',$types,true))) throw new Problem(422,'default_metadata','Destinația implicită se salvează fără metadate opționale. Creează o legătură distinctă pentru variante de limbă sau format.');
        if ($default) $types[]=self::NS.'defaultLink';
        $out=['href'=>$href,'title'=>Catalog::text($data,'title',180),'types'=>$types];
        if ($languages) $out['hreflang']=$languages; if ($contexts) $out['context']=$contexts; if ($media) $out['type']=$media;
        return $out;
    }
    private static function writable(array $p,string $item,string $scope='write'): array {
        if (!Migrations::resolver()) throw new Problem(503,'resolver_schema','Administratorul trebuie să aplice actualizarea resolverului.');
        if($scope==='write')Auth::write($p);else Auth::approve($p,$scope);$row=Catalog::owned('trade_items',$item,$p);
        if (!$row['gtin14']||$row['gtin_status']!=='verified') throw new Problem(422,'gtin_right','Dreptul de administrare GTIN trebuie verificat înainte de administrarea destinațiilor publice.');
        return $row;
    }
    public static function draft(array $p,string $item,array $data): array {
        self::writable($p,$item); $qualifiers=self::qualifiers($data['qualifiers']??[]); $target=self::target($data);
        return Db::atomic(function()use($p,$item,$data,$qualifiers,$target){
            Db::one('SELECT id FROM trade_items WHERE id=? AND tenant_id=? FOR UPDATE',[$item,$p['tenant']]);
            $key=Catalog::text($data,'link_key',32,false)??Db::id();
            if (!preg_match('/\A[a-f0-9]{32}\z/D',$key)) throw new Problem(422,'link_key','Referință incorectă.');
            $existing=Db::one('SELECT trade_item_id FROM resolver_links WHERE tenant_id=? AND link_key=? LIMIT 1',[$p['tenant'],$key]);
            if($existing&&$existing['trade_item_id']!==$item)throw new Problem(404,'not_found','Legătura nu a fost găsită.');
            $old=Db::one('SELECT MAX(version_no) AS n FROM resolver_links WHERE tenant_id=? AND trade_item_id=? AND link_key=?',[$p['tenant'],$item,$key]);
            $version=(int)($old['n']??0)+1;
            if ($version>1 && (int)($data['expected_version']??-1)!==$version-1) throw new Problem(409,'version_conflict','Destinația a fost modificată. Reîncarcă istoricul.');
            if ((int)Db::one("SELECT COUNT(DISTINCT link_key) AS n FROM resolver_links WHERE tenant_id=? AND trade_item_id=?",[$p['tenant'],$item])['n']>=256&&$version===1) throw new Problem(422,'link_limit','Articolul a atins limita de destinații.');
            $id=Db::id(); Db::insert('resolver_links',['id'=>$id,'tenant_id'=>$p['tenant'],'trade_item_id'=>$item,'link_key'=>$key,'version_no'=>$version,'scope_sha256'=>ResolverUri::scopeHash($qualifiers),'qualifiers_json'=>Db::json((object)$qualifiers),'target_json'=>Db::json($target),'is_default'=>(int)in_array(self::NS.'defaultLink',$target['types'],true),'created_by'=>$p['user'],'created_at'=>Db::now()]);
            Catalog::audit($p,'resolver.draft_created',$id,['link_key'=>$key,'version'=>$version]); return ['id'=>$id,'link_key'=>$key,'version_no'=>$version,'state'=>'draft'];
        });
    }
    public static function owned(array $p,string $id): array {
        return Db::one('SELECT * FROM resolver_links WHERE id=? AND tenant_id=?',[$id,$p['tenant']])??throw new Problem(404,'not_found','Legătura nu a fost găsită.');
    }
    public static function publish(array $p,string $id,array $data): array {
        Auth::approve($p);
        if (($data['public_projection_confirmed']??false)!==true) throw new Problem(422,'publication','Confirmă că adresa și metadatele sunt aprobate pentru publicare.');
        return Db::atomic(function()use($p,$id){
            $row=self::owned($p,$id); self::writable($p,$row['trade_item_id'],'publish');
            Db::one('SELECT id FROM trade_items WHERE id=? AND tenant_id=? FOR UPDATE',[$row['trade_item_id'],$p['tenant']]);
            if ($row['state']!=='draft') throw new Problem(409,'state','Versiunea a fost deja închisă.');
            $latest=Db::one('SELECT MAX(version_no) AS n FROM resolver_links WHERE tenant_id=? AND link_key=?',[$p['tenant'],$row['link_key']]);
            if ((int)$latest['n']!==(int)$row['version_no']) throw new Problem(409,'version_conflict','Există o versiune mai nouă a acestei destinații.');
            if ($row['is_default']&&Db::one("SELECT id FROM resolver_links WHERE trade_item_id=? AND scope_sha256=? AND is_current=1 AND state='published' AND is_default=1 AND link_key<>?",[$row['trade_item_id'],$row['scope_sha256'],$row['link_key']])) throw new Problem(409,'default_conflict','Există deja o destinație implicită pentru acest nivel. Actualizează sau retrage acea legătură.');
            $native=Db::one("SELECT t.id FROM trade_items t JOIN product_info_versions v ON v.id=t.current_info_id AND v.tenant_id=t.tenant_id AND v.state='published' WHERE t.id=? AND t.tenant_id=?",[$row['trade_item_id'],$p['tenant']]);
            $rootDefault=Db::one("SELECT id FROM resolver_links WHERE tenant_id=? AND trade_item_id=? AND scope_sha256=? AND is_current=1 AND state='published' AND is_default=1 AND link_key<>?",[$p['tenant'],$row['trade_item_id'],ResolverUri::scopeHash([]),$row['link_key']]);
            if(!$native&&!$rootDefault&&!($row['is_default']&&$row['scope_sha256']===ResolverUri::scopeHash([])))throw new Problem(422,'default_required','Publică întâi informațiile produsului sau o destinație implicită la nivel GTIN.');
            Db::run('UPDATE resolver_links SET is_current=0 WHERE tenant_id=? AND link_key=?',[$p['tenant'],$row['link_key']]);
            Db::run("UPDATE resolver_links SET state='published',is_current=1,approved_by=?,approved_at=? WHERE id=? AND tenant_id=?",[$p['user'],Db::now(),$id,$p['tenant']]);
            Catalog::audit($p,'resolver.published',$id,['link_key'=>$row['link_key'],'version'=>$row['version_no']]); return ['id'=>$id,'state'=>'published'];
        });
    }
    public static function withdraw(array $p,string $id,array $data): array {
        Auth::approve($p); $reason=Catalog::text($data,'reason',1000);
        return Db::atomic(function()use($p,$id,$reason){
            $row=self::owned($p,$id); Catalog::owned('trade_items',$row['trade_item_id'],$p);
            Db::one('SELECT id FROM trade_items WHERE id=? AND tenant_id=? FOR UPDATE',[$row['trade_item_id'],$p['tenant']]);
            if (!$row['is_current']||$row['state']!=='published') throw new Problem(409,'state','Legătura nu este versiunea publicată curentă.');
            if($row['is_default']&&$row['scope_sha256']===ResolverUri::scopeHash([])&&!Db::one("SELECT t.id FROM trade_items t JOIN product_info_versions v ON v.id=t.current_info_id AND v.tenant_id=t.tenant_id AND v.state='published' WHERE t.id=? AND t.tenant_id=?",[$row['trade_item_id'],$p['tenant']])&&Db::one("SELECT id FROM resolver_links WHERE tenant_id=? AND trade_item_id=? AND is_current=1 AND state='published' AND link_key<>? LIMIT 1",[$p['tenant'],$row['trade_item_id'],$row['link_key']]))throw new Problem(422,'default_required','Păstrează o destinație implicită pentru legăturile publicate. Publică informația produsului sau retrage întâi celelalte legături.');
            $version=(int)Db::one('SELECT MAX(version_no) AS n FROM resolver_links WHERE tenant_id=? AND link_key=?',[$p['tenant'],$row['link_key']])['n']+1;
            $newId=Db::id(); unset($row['id']); $row['id']=$newId; $row['version_no']=$version; $row['state']='withdrawn'; $row['created_at']=Db::now(); $row['created_by']=$p['user']; $row['approved_at']=Db::now(); $row['approved_by']=$p['user']; $row['decision']=$reason;
            Db::run('UPDATE resolver_links SET is_current=0 WHERE tenant_id=? AND link_key=?',[$p['tenant'],$row['link_key']]); Db::insert('resolver_links',$row);
            Catalog::audit($p,'resolver.withdrawn',$newId,['link_key'=>$row['link_key'],'reason'=>$reason]); return ['id'=>$newId,'state'=>'withdrawn'];
        });
    }
    public static function history(array $p,string $item): array {
        Catalog::owned('trade_items',$item,$p);
        return Migrations::resolver()?Db::all('SELECT * FROM resolver_links WHERE tenant_id=? AND trade_item_id=? ORDER BY created_at DESC,id DESC LIMIT 1000',[$p['tenant'],$item]):[];
    }
    public static function transportReady(): bool {
        return Migrations::resolver()&&(bool)(Db::one('SELECT verified FROM resolver_transport WHERE id=1')['verified']??false);
    }
}
