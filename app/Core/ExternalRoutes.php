<?php
declare(strict_types=1);

namespace App\Core;

final class ExternalRoutes
{
    public static function all(): array
    {
        $out=[];
        foreach ([
            ['POST','/api/v2/public/food/resolve','externalResolve','public','','resolve','ExternalResolve'],
            ['POST','/api/v2/public/food/lookups','externalLookup','public','','lookup','ExternalLookup'],
            ['GET','/api/v2/public/food/lookups/{id}','externalLookupStatus','public','','lookupStatus',''],
            ['GET','/api/v2/public/sources','externalSources','public','','sources',''],
            ['GET','/api/v2/public/sources/openfoodfacts/revisions','externalRevisionIndex','public','','revisionIndex',''],
            ['GET','/api/v2/public/external-revisions/{id}','externalRevision','public','','revision',''],
            ['GET','/api/v2/public/external-assets/{id}','externalAsset','public','','asset',''],
            ['GET','/api/v2/public/sources/openfoodfacts/export','externalExport','public','','export',''],
            ['GET','/api/v2/manage/sources/openfoodfacts/bindings','externalOrganizationBindings','producer','read','organizationBindings',''],
            ['GET','/api/v2/manage/items/{id}/sources','externalItemSources','producer','read','bindings',''],
            ['POST','/api/v2/manage/items/{id}/sources','externalBind','producer','write','bind','ExternalBinding'],
            ['POST','/api/v2/manage/items/{id}/sources/{binding}/disconnect','externalDisconnectBinding','producer','write','disconnectBinding','Empty'],
            ['POST','/api/v2/manage/items/{id}/sources/openfoodfacts/preview','externalImportPreview','producer','read','preview','ExternalImport'],
            ['POST','/api/v2/manage/items/{id}/sources/openfoodfacts/drafts','externalImportDraft','producer','write','draft','ExternalImport'],
            ['GET','/api/v2/manage/source-conflicts','externalConflicts','producer','read','conflicts',''],
            ['POST','/api/v2/manage/source-conflicts/{id}/decisions','externalConflictDecision','producer','publish','decision','ExternalDecision'],
            ['GET','/api/v2/manage/sources/openfoodfacts/connection','externalConnection','producer','read','connection',''],
            ['POST','/api/v2/manage/sources/openfoodfacts/connection','externalConnect','producer','sharing:manage','connect','ExternalConnection'],
            ['POST','/api/v2/manage/sources/openfoodfacts/connection/disconnect','externalDisconnect','producer','sharing:manage','disconnect','ExternalSensitiveEmpty'],
            ['GET','/api/v2/manage/sources/openfoodfacts/sharing-grants','externalSharingGrants','producer','read','grants',''],
            ['POST','/api/v2/manage/sources/openfoodfacts/sharing-grants','externalSharingGrant','producer','sharing:manage','grant','ExternalGrant'],
            ['POST','/api/v2/manage/sources/openfoodfacts/sharing-grants/{id}/revoke','externalSharingRevoke','producer','sharing:manage','revoke','ExternalSensitiveEmpty'],
            ['POST','/api/v2/manage/items/{id}/sources/openfoodfacts/publications/preview','externalPublicationPreview','producer','read','publicationPreview','ExternalPublication'],
            ['POST','/api/v2/manage/items/{id}/sources/openfoodfacts/publications','externalCreatePublication','producer','sharing:write','publication','ExternalPublication'],
            ['GET','/api/v2/manage/sources/openfoodfacts/publications','externalPublications','producer','read','publications',''],
            ['GET','/api/v2/manage/sources/openfoodfacts/publications/{id}/package','externalProducerPackage','producer','export','package',''],
            ['POST','/api/v2/admin/sources/openfoodfacts/state','externalSourceState','admin-session','','state','ExternalState'],
            ['GET','/api/v2/admin/sources/openfoodfacts/status','externalSourceStatus','admin-session','','status',''],
            ['POST','/api/v2/admin/sources/openfoodfacts/connections/{id}/verify','externalConnectionVerify','admin-session','','verify','Empty'],
            ['POST','/api/v2/admin/sources/openfoodfacts/revisions/{id}/visibility','externalRevisionVisibility','admin-session','','visibility','ExternalVisibility'],
            ['POST','/api/v2/admin/sources/openfoodfacts/jobs/{id}/retry','externalJobRetry','admin-session','','retry','Empty'],
            ['POST','/api/v2/admin/sources/openfoodfacts/datasets/preview','externalDatasetPreview','admin-session','','datasetPreview','ExternalDataset'],
            ['POST','/api/v2/admin/sources/openfoodfacts/datasets','externalDatasetImport','admin-session','','dataset','ExternalDataset'],
            ['POST','/api/v2/admin/sources/openfoodfacts/datasets/{id}/retry','externalDatasetRetry','admin-session','','datasetRetry','Empty'],
            ['POST','/api/v2/admin/sources/openfoodfacts/exports','externalDatasetExport','admin-session','','datasetExport','Empty'],
            ['POST','/api/v2/admin/sources/openfoodfacts/worker','externalRunWorker','admin-session','','worker','Empty'],
        ] as [$method,$path,$id,$auth,$scope,$handler,$body]) {
            $summary=match($handler){
                'resolve'=>'Citește profilul aprobat și suplimentul extern separat, fără apel la OFF.',
                'lookup'=>'Caută un GTIN în OFF cu cache, buget persistent și termen limitat.',
                'draft'=>'Creează o ciornă cu proveniență, fără aprobarea implicită a datelor.',
                'publication','package'=>'Pregătește sau descarcă datele aprobate pentru importul producătorului; nu confirmă publicarea în OFF.',
                default=>'Gestionează '.strtolower(preg_replace('/(?<!^)[A-Z]/',' $0',$id)).' cu proveniență și autorizare explicită.',
            };
            $out[]=['method'=>$method,'path'=>$path,'id'=>$id,'auth'=>$auth,'scope'=>$scope,'handler'=>'external.'.$handler,'summary'=>$summary,'body'=>$body];
        }
        return $out;
    }
}
