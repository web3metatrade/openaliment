<?php declare(strict_types=1);
use App\Domain\ApiDocumentation;
$isOperation=$docPage['kind']==='operation';$currentId=$docPage['id'];$toc=$docPage['toc']??[['id'=>'operation-access','title'=>'Acces și permisiuni'],['id'=>'operation-parameters','title'=>'Parametri'],['id'=>'operation-request','title'=>'Corpul cererii'],['id'=>'operation-examples','title'=>'Exemple de integrare'],['id'=>'operation-responses','title'=>'Răspunsuri'],['id'=>'operation-explorer','title'=>'Încearcă operația']];
$position=array_search($currentId,array_column($documentation['navigation'],'id'),true);$previous=$documentation['navigation'][$position-1]??null;$next=$documentation['navigation'][$position+1]??null;
?>
<link rel="stylesheet" href="/assets/api-docs.css?v=2">
<div class="api-docs" data-docs-page="<?=e($currentId)?>" data-markdown-url="<?=e($docPage['path'].'.md')?>">
<div class="api-docs-top"><a href="/api/docs" class="api-docs-brand"><?=icon('file')?> Documentație API</a><div><a href="/api/platform-openapi.json">OpenAPI <?=icon('download')?></a><a href="/manage/developers">Aplicațiile tale <?=icon('arrow')?></a></div></div>
<div class="api-docs-grid">
<details class="api-sidebar" open><summary><?=icon('menu')?> Navigare și căutare <?=icon('chevron')?></summary><div class="api-sidebar-inner">
<label class="api-search-label" for="api-search"><?=icon('search')?><span class="visually-hidden">Caută în documentația API</span><input id="api-search" type="search" placeholder="Caută în documentație…" autocomplete="off"><kbd>Ctrl K</kbd></label>
<p id="api-search-status" class="api-search-status" role="status"><?=$documentation['operation_count']?> operații · <?=$documentation['guides']?> ghiduri</p>
<nav aria-label="Ghiduri și referință API">
<section class="api-nav-group" data-nav-group><h2>Ghiduri</h2><ul><?php foreach($documentation['navigation']as$item):if($item['kind']!=='guide')continue;?><li data-doc-search="<?=e($item['search']??$item['title'])?>"><a href="<?=e($item['path'])?>" <?=$item['id']===$currentId?'aria-current="page"':''?>><?=e($item['title'])?></a></li><?php endforeach?></ul></section>
<h2 class="api-reference-label" id="referinta">REFERINȚĂ API</h2>
<?php foreach($documentation['groups']as$key=>$label):$items=array_values(array_filter($documentation['navigation'],static fn($item)=>($item['group']??'')===$key));if(!$items)continue;?>
<details class="api-nav-group" data-nav-group <?=($isOperation&&$docPage['group']===$key)?'open':''?>><summary><?=icon('chevron')?><?=e($label)?><span><?=count($items)?></span></summary><ul>
<?php foreach($items as$item):$short=preg_replace('~\A/api/v[12]/(?:manage/|public/)?~','/',$item['endpoint']);?><li data-doc-search="<?=e(($item['search']??$item['title']).' '.$item['method'].' '.$item['endpoint'].' '.$item['id'])?>"><a href="<?=e($item['path'])?>" title="<?=e($item['title'])?>" <?=$item['id']===$currentId?'aria-current="page"':''?>><span class="api-method api-method-<?=e(strtolower($item['method']))?>"><?=e($item['method'])?></span><span class="api-nav-path"><?=e($short)?></span></a></li><?php endforeach?>
</ul></details><?php endforeach?></nav>
<div class="api-sidebar-links"><a href="/api/docs/postman.json">Colecție Postman</a><a href="/api/docs/examples/erp-workflow.py">Exemplu ERP executabil</a><a href="/api/manifest.json">Manifest API</a><a href="/llms.txt">Index pentru AI</a><a href="/api/guide.md">Ghid complet Markdown</a><a href="/api/sdk/python">Client Python</a><a href="/api/sdk/javascript">Client JavaScript</a><a href="/api/sdk/kotlin">Exemplu Android / Kotlin</a></div>
</div></details>
<article class="api-document" id="api-document">
<div class="api-page-bar"><div class="api-breadcrumb"><a href="/api/docs">API</a><span>/</span><span><?=e($isOperation?$documentation['groups'][$docPage['group']]:'Ghiduri')?></span></div><div class="api-page-actions"><button type="button" class="api-copy-page" data-copy-page title="Copy Page"><?=icon('copy')?> Copiază pagina</button><details class="api-page-menu"><summary aria-label="Opțiuni pentru pagină"><?=icon('chevron')?></summary><div class="api-menu-items"><a href="<?=e($chatgptUrl)?>" target="_blank" rel="noopener noreferrer">Deschide în ChatGPT <?=icon('external')?></a><a href="<?=e($claudeUrl)?>" target="_blank" rel="noopener noreferrer">Deschide în Claude <?=icon('external')?></a><a href="<?=e($docPage['path'].'.md')?>" target="_blank" rel="noopener">Vezi ca Markdown <?=icon('file')?></a></div></details></div></div>
<p class="api-copy-status" id="api-copy-status" role="status" aria-live="polite"></p>
<h1><?=e($docPage['title'])?></h1>
<?php if($isOperation):require ROOT.'/app/Views/api-reference.php';else:?>
<div class="api-prose"><?=$docPage['html']?></div>
<?php if($currentId==='overview'):?><div class="api-demo-panel"><div><strong>Încearcă exemplul Pâine Bună Tehnic</strong><p>Citire publică, fără cheie.</p></div><button type="button" class="btn btn-primary" data-try-demo>Obține informațiile <?=icon('arrow')?></button><pre class="api-code" id="api-demo-result" hidden aria-live="polite"><code></code></pre></div><?php endif?>
<?php endif?>
<nav class="api-pagination" aria-label="Pagini de documentație"><?php foreach([[$previous,'Pagina anterioară','previous'],[$next,'Pagina următoare','next']]as[$item,$label,$class]):if(!$item)continue;?><a class="<?=$class?>" href="<?=e($item['path'])?>"><span><?=e($label)?></span><strong><?=e($item['title'])?></strong></a><?php endforeach?></nav>
</article>
<aside class="api-page-toc" aria-label="În această pagină"><p>În această pagină</p><?php foreach($toc as$entry):?><a href="#<?=e($entry['id'])?>" <?=($entry['level']??2)===3?'class="subheading"':''?>><?=e($entry['title'])?></a><?php endforeach?><div class="api-toc-links"><a href="<?=e($docPage['path'].'.md')?>">Markdown <?=icon('file')?></a><a href="/api/platform-openapi.json">Contract OpenAPI <?=icon('external')?></a></div></aside>
</div>
<template id="api-page-markdown"><?=e(ApiDocumentation::markdown($docPage))?></template>
<dialog id="api-copy-dialog" class="api-copy-dialog"><h2>Copiază documentația</h2><p>Copierea automată nu este disponibilă. Selectează textul și folosește opțiunea Copiază a dispozitivului.</p><label for="api-copy-text">Conținut Markdown</label><textarea id="api-copy-text" rows="12" readonly></textarea><button type="button" class="btn btn-primary" data-close-copy>Închide</button></dialog>
</div><script src="/assets/api-docs.js?v=3" defer></script><?php if($isOperation):?><script src="/assets/api-explorer.js?v=1" defer></script><?php endif?>
