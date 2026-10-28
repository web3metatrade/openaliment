<?php declare(strict_types=1); ?>
<section class="panel"><h2>Profil fiscal și înregistrare cu CUI</h2>
<?php if (!\App\Core\Migrations::anaf()): ?><p>Actualizarea adaugă profilul fiscal ANAF și istoricul confirmărilor, păstrând conturile existente.</p><form method="post"><?=csrf()?><input type="hidden" name="action" value="install_anaf"><button class="btn btn-primary">Instalează profilul fiscal ANAF</button></form>
<?php else: ?><p>Profilul fiscal ANAF și API-urile sale sunt instalate. Înscrierile sunt <?=\App\Domain\RegistrationSettings::enabled() ? 'active' : 'oprite'?>.</p>
<?php if (!\App\Domain\RegistrationSettings::enabled() && rtrim((string) \App\Core\Config::get('url'), '/') === 'https://dev.openaliment.ro'): ?><form method="post" class="form-stack"><?=csrf()?><input type="hidden" name="action" value="enable_anaf_staging"><label>Confirmă parola dacă sesiunea este mai veche de 15 minute<input type="password" class="form-control" name="current_password" autocomplete="current-password" maxlength="200"></label><button class="btn btn-primary">Activează înregistrarea cu CUI pe dev</button></form><?php endif?>
<a href="/register">Pagina de înregistrare</a> · <a href="/api/docs/guides/inregistrare-anaf">Configurare și API</a>
<?php endif?>
</section>
