<?php
declare(strict_types=1);
$values = $values ?? [];
$found = $anafResult && $anafResult['state'] === 'found';
?>
<div class="container page-space registration-page">
<div class="eyebrow"><?=!empty($partnerRegistration) ? 'PARTENERI OPENALIMENT' : 'SPAȚIUL PRODUCĂTORULUI'?></div>
<h1 class="page-title"><?=!empty($partnerRegistration) ? 'Înscrie firma ca partener' : 'Înregistrează organizația'?></h1>
<p class="muted">Găsim datele firmei tale pornind de la CUI.</p>
<?php if (!$registrationEnabled): ?>
<section class="panel"><p>Înscrierile se fac prin administratorul platformei.</p><a href="/login">Ai deja cont? Intră în cont</a></section>
<?php else: ?>
<form method="post" action="/register" id="registration-form">
<?=csrf()?>
<section class="panel registration-lookup">
<div class="registration-cui"><label for="registration-cui">CUI</label><div class="registration-cui-row"><input class="form-control" id="registration-cui" name="cui" inputmode="numeric" autocomplete="off" maxlength="32" required aria-describedby="registration-cui-help" value="<?=e(is_string($values['cui'] ?? null) ? $values['cui'] : '')?>"><button class="btn btn-primary" type="submit" name="intent" value="lookup" formnovalidate aria-label="Verifică CUI-ul la ANAF">OK</button></div></div>
<p class="small muted" id="registration-cui-help">Cu sau fără RO. Dacă ai greșit CUI-ul, corectează-l și apasă din nou OK.</p>
<p id="registration-progress" class="small" role="status" aria-live="polite"></p>
</section>
<div id="anaf-result" aria-live="polite">
<?php if ($registrationError): ?><p class="notice" role="alert"><?=e($registrationError)?></p><?php endif?>
<?php if ($anafResult && !$found): ?><p class="notice" role="status">Nu am găsit acest CUI în ANAF. Verifică cifrele și încearcă din nou.</p><?php endif?>
<?php if ($found): $record = $anafResult['record']; $general = $record['date_generale']; ?>
<input type="hidden" name="organization_lookup_id" value="<?=e($anafResult['id'])?>">
<div class="registration-columns">
<section class="panel registration-company"><span class="eyebrow">DATE GĂSITE ÎN ANAF</span><h2><?=e($general['denumire'])?></h2>
<dl class="registration-summary"><div><dt>CUI</dt><dd><?=e($anafResult['cui'])?></dd></div><div><dt>Sediul social</dt><dd><?=e(\App\Domain\AnafPresentation::address($record['adresa_sediu_social'] ?? [], 's'))?></dd></div><div><dt>Registrul Comerțului</dt><dd><?=e(\App\Domain\AnafPresentation::value($general['nrRegCom'] ?? null))?></dd></div><div><dt>Stare de înregistrare</dt><dd><?=e(\App\Domain\AnafPresentation::value($general['stare_inregistrare'] ?? null))?></dd></div><div><dt>Înregistrată în scopuri de TVA</dt><dd><?=e(\App\Domain\AnafPresentation::value($record['inregistrare_scop_Tva']['scpTVA'] ?? null))?></dd></div></dl>
<p class="small muted">Situație la <?=e($anafResult['source']['queried_for'])?> · Consultat <?=e(ro_time($anafResult['source']['fetched_at']))?>.</p><a href="#anaf-complete">Toate datele ANAF ↓</a>
<?php foreach ($anafResult['warnings'] as $warning): ?><p class="notice"><?=e($warning)?></p><?php endforeach?>
</section>
<section class="panel registration-account"><h2>Aceasta este firma ta?</h2><p class="muted">Verifică firma găsită și completează datele tale de acces.</p>
<div class="form-stack"><label>Numele tău<input class="form-control" name="display_name" required maxlength="120" autocomplete="name" value="<?=e(is_string($values['display_name'] ?? null) ? $values['display_name'] : '')?>"></label><label>Email de contact<input class="form-control" type="email" name="email" required maxlength="190" autocomplete="username" value="<?=e(is_string($values['email'] ?? null) ? $values['email'] : '')?>"></label><label>Parolă<input class="form-control" type="password" name="password" required minlength="12" maxlength="<?=\App\Core\Passwords::maxBytes()?>" autocomplete="new-password" aria-describedby="registration-password-help"></label><small class="muted" id="registration-password-help">Cel puțin 12 caractere.</small><button class="btn btn-primary" type="submit" name="intent" value="create">Creează contul pentru această firmă <?=icon('arrow')?></button></div>
<p class="small muted mt-3">Datele firmei sunt preluate din ANAF. Reprezentarea organizației și drepturile GS1 se verifică separat.</p></section>
</div>
<section class="registration-all" id="anaf-complete"><h2>Toate datele primite de la ANAF</h2><p class="muted">Sediul social și domiciliul fiscal sunt prezentate separat. Valorile nefurnizate rămân indicate ca atare.</p><?php $anafDisplay = $anafResult; require ROOT . '/app/Views/anaf-details.php'; ?></section>
<?php endif?>
</div>
</form>
<p class="mt-4"><a href="/login">Ai deja cont? Intră în cont</a> · <a href="mailto:<?=e(\App\Core\Config::get('operator_contact', 'alex@patrut.com'))?>">Organizație fără CUI românesc</a></p>
<script src="/assets/registration.js?v=1" defer></script>
<?php endif?>
</div>
