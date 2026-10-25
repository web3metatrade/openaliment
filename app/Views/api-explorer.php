<?php declare(strict_types=1);
$requestContent=$operation['requestBody']['content']??[];
$requestMime=array_key_first($requestContent);
$requestExample=$requestContent[$requestMime]['example']??null;
$bearerAllowed=array_any($operation['security']??[],static fn($s)=>isset($s['Bearer'])||isset($s['HomeOAuth']));
$publicAllowed=empty($operation['security'])&&$docPage['method']==='GET'&&!str_contains($docPage['endpoint'],'authorize')&&!str_contains($docPage['endpoint'],'/identity/');
$binaryResponse=array_any($operation['responses']??[],static fn($r)=>isset($r['content']['application/octet-stream'])||isset($r['content']['image/jpeg'])||isset($r['content']['application/zip']));
$liveAllowed=($bearerAllowed||$publicAllowed)&&!$binaryResponse&&($requestMime===null||in_array($requestMime,['application/json','application/x-www-form-urlencoded'],true));
$cases=[];
foreach($operation['responses']as$status=>$response){
    foreach($response['content']??[]as$mime=>$content){
        if(array_key_exists('example',$content))$cases[]=['label'=>$status.' · '.$mime,'status'=>(int)$status,'mime'=>$mime,'body'=>$content['example']];
        foreach($content['examples']??[]as$example)$cases[]=['label'=>$status.' · '.($example['summary']??$mime),'status'=>(int)$status,'mime'=>$mime,'body'=>$example['value']];
    }
    if(empty($response['content']))$cases[]=['label'=>$status.' · fără corp JSON','status'=>(int)$status,'mime'=>null,'body'=>''];
}
$explorer=['method'=>$docPage['method'],'path'=>$docPage['endpoint'],'mime'=>$requestMime,'bearer'=>$bearerAllowed,'live'=>$liveAllowed,'cases'=>$cases];
?>
<section class="api-prose api-explorer" id="operation-explorer" data-api-explorer>
<h2>Încearcă operația</h2>
<p>Explorează exemplele fără cont. Cererile reale folosesc exclusiv datele și drepturile contului tău; exemplele sintetice nu sunt un server demonstrativ.</p>
<form autocomplete="off" data-explorer-form>
<label for="api-explorer-case">Răspuns demonstrativ</label>
<select id="api-explorer-case"><?php foreach($cases as$i=>$case):?><option value="<?=$i?>"><?=e($case['label'])?></option><?php endforeach?></select>
<button type="button" class="btn btn-outline-primary" data-explorer-example>Afișează exemplul</button>
<?php if($liveAllowed):?>
<details class="api-live-options"><summary>Configurează o cerere reală</summary>
<p>Destinație: <code><?=e(url($docPage['endpoint']))?></code>. Nu se trimit cookie-uri și nu se urmăresc redirecționări. Cheia rămâne numai în această pagină și nu intră în Copy Page sau în legăturile către AI.</p>
<?php foreach($operation['parameters']??[]as$param):if(!in_array($param['in'],['path','query'],true)&&$param['name']!=='Idempotency-Key')continue;?>
<label><?=e($param['name'])?> <small>(<?=e($param['in'])?>)</small><input type="text" data-explorer-param="<?=e($param['name'])?>" data-param-location="<?=e($param['in'])?>" value="<?=e($param['in']==='query'&&empty($param['required'])&&!in_array($param['name'],['limit','linkType'],true)?'':(string)($param['example']??''))?>" maxlength="4096"></label>
<?php endforeach?>
<?php if($bearerAllowed):?><label for="api-explorer-token">Token Bearer propriu</label><input type="password" id="api-explorer-token" autocomplete="off" spellcheck="false" maxlength="180" placeholder="Cheie API sau token OAuth cu permisiunile necesare"><?php endif?>
<?php if($requestMime):?><label for="api-explorer-body">Corpul cererii (JSON; pentru formular se codifică automat)</label><textarea id="api-explorer-body" rows="10" spellcheck="false" maxlength="2000000"><?=e($codeJson($requestExample))?></textarea><?php endif?>
<?php if($docPage['method']!=='GET'):?><label class="api-explorer-confirm"><input type="checkbox" data-explorer-confirm> Execut această operație în contul meu, cu datele completate mai sus.</label><?php endif?>
<button type="submit" class="btn btn-primary" data-explorer-send>Trimite cererea reală</button>
</details>
<?php else:?><p>Acest flux folosește sesiune, consimțământ, fișier binar sau o trimitere publică. Urmează exemplul de integrare și ghidul corespunzător; exploratorul afișează aici răspunsurile documentate.</p><?php endif?>
</form>
<p role="status" aria-live="polite" data-explorer-status></p>
<pre class="api-code" data-copy-code><code data-explorer-result>Alege un exemplu de răspuns.</code></pre>
<template id="api-explorer-data"><?=e($codeJson($explorer))?></template>
</section>
