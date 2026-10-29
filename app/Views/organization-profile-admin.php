<?php declare(strict_types=1); ?>
<div class="container page-space registration-page"><a class="back-link" href="/admin">← Administrare</a><div class="eyebrow">DATELE ORGANIZAȚIEI</div><h1 class="page-title"><?=e($reviewOrganization['display_name'])?></h1><p>Starea verificării reprezentării: <?=e(ro_state($reviewOrganization['identity_status']))?>.</p><p class="muted">Datele ANAF confirmă fișa fiscală. Mandatul reprezentantului și drepturile GS1 necesită verificarea lor separată.</p>
<?php if ($reviewProfile): $anafDisplay = $reviewProfile; ?><h2><?=e($reviewProfile['legal_name'])?> · CUI <?=e($reviewProfile['cui'])?></h2><?php require ROOT . '/app/Views/anaf-details.php'; ?><?php else: ?><p>Organizația nu are un profil fiscal completat.</p><?php endif?>
</div>
