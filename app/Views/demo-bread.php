<?php declare(strict_types=1); use App\Domain\DemoBread; ?>
<div class="demo-bread container">
<a class="back-link" href="/gs1-digital-link#exemplu">← Exemplul GS1 Digital Link</a>
<div class="demo-bread-grid"><section>
<span class="demo-badge">DEMONSTRAȚIE · FĂRĂ VÂNZARE</span>
<h1>Pâine Bună<br>Tehnic<span class="brand-dot">.</span></h1>
<p class="lead"><?=$batch?'Ai deschis pagina lotului simulat.':'Ai deschis pagina demonstrativă a produsului.'?></p>
<?php if($unknownLot):?><p class="notice">Lotul din adresă nu este înregistrat în demonstrație. Sunt afișate numai informațiile de produs.</p><?php endif?>
<p>Primul produs pilot OpenBread din OpenAliment. GTIN-ul<?=$batch?' și lotul sunt simulate':' este simulat'?> pentru testarea scanării. Documentația R1 rămâne în stadiu experimental.</p>
<p><a class="btn btn-outline-primary" href="/acasa/#add=<?=e(rawurlencode(DemoBread::address($batch)))?>" data-home-add="<?=e(DemoBread::address($batch))?>" data-home-name="Pâine Bună Tehnic"><?=icon('plus')?> Adaugă acasă</a></p><script type="module" src="/assets/home/entry.js?v=8"></script>
<dl class="demo-facts"><div><dt>GTIN-13 demonstrativ</dt><dd><?=DemoBread::GTIN13?></dd></div><div><dt>GTIN în Digital Link · AI (01)</dt><dd><?=DemoBread::GTIN14?></dd></div><div><dt>Lot · AI (10)</dt><dd><?=$batch?DemoBread::LOT:'Fără lot în QR'?></dd></div><div><dt>Versiunea informațiilor simulate</dt><dd>DEMO-E1</dd></div></dl>
<p class="small muted">Prefixul 952 este destinat de GS1 demonstrațiilor. Acest număr nu este un GTIN comercial al pâinii. Exemplul nu atribuie o siglă, o licență deschisă sau rezultate de fabricație.</p>
<div class="button-row"><a class="btn btn-primary" href="<?=DemoBread::PAGE?>/etichete">Fișă de scanare și tipărire <?=icon('arrow')?></a><a class="text-link" href="<?=e(DemoBread::page($batch))?>/date.json">Date JSON <?=icon('file')?></a></div>
</section><aside class="demo-scan-card" aria-label="Codul QR demonstrativ">
<span class="eyebrow"><?=$batch?'QR DE LOT':'QR DE PRODUS'?> · DEMO</span>
<img class="demo-qr" src="<?=DemoBread::asset($batch)?>" width="294" height="294" alt="Cod QR scanabil pentru Pâine Bună Tehnic, <?=$batch?'lot simulat '.DemoBread::LOT:'produs demonstrativ'?>">
<p>Scanează cu telefonul</p><code class="demo-address"><?=e(DemoBread::address($batch))?></code>
<a class="btn btn-outline-primary" href="<?=DemoBread::asset($batch)?>" download="PBT-DEMO-<?=$batch?'lot':'produs'?>-QR.svg">Descarcă QR · SVG</a>
<a class="text-link" href="<?=e(DemoBread::page(!$batch))?>">Vezi <?=$batch?'produsul generic':'lotul '.DemoBread::LOT?> <?=icon('arrow')?></a>
</aside></div>
<section class="demo-pos"><div><div class="eyebrow">PROBA ÎN MAGAZIN</div><h2>Citire, apoi recunoaștere.</h2></div><ol><li><strong>Telefonul</strong> deschide această pagină, prin resolver, fără cont și fără AI.</li><li><strong>Scannerul 2D compatibil</strong> citește URI-ul GS1 Digital Link; în funcție de configurare, transmite URI-ul, GTIN-ul sau elementele GS1 către POS.</li><li><strong>Casa de marcat</strong> trebuie să aibă un articol demonstrativ pentru GTIN-ul <?=DemoBread::GTIN13?>. Fă proba în modul de test al magazinului, fără vânzare.</li></ol><p class="small muted">Decodarea QR-ului nu confirmă automat integrarea POS sau calitatea tiparului. <a href="https://ref.gs1.org/guidelines/2d-in-retail/" rel="external">Ghid GS1 pentru retail</a>.</p></section>
</div>
