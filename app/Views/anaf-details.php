<?php declare(strict_types=1); ?>
<div class="anaf-data-grid">
<?php if (isset($anafDisplay['directly_verified']) && !$anafDisplay['directly_verified']): ?><p class="notice">Date importate cu proveniența lor. Este necesară o reverificare ANAF pentru confirmarea directă pe această platformă.</p><?php endif?>
<?php foreach (\App\Domain\AnafPresentation::groups($anafDisplay['record']) as $group): ?>
<section class="panel anaf-data-group"><h3><?=e($group['title'])?></h3><dl>
<?php foreach ($group['fields'] as $field): ?><div class="anaf-field"><dt><?=e($field['label'])?></dt><dd><?=e(\App\Domain\AnafPresentation::value($field['value']))?></dd></div><?php endforeach?>
</dl></section>
<?php endforeach?>
</div>
<p class="small muted">Sursă: ANAF · API <?=e($anafDisplay['source']['api_version'])?> · Situație la <?=e($anafDisplay['source']['queried_for'])?> · Consultat <?=e(ro_time($anafDisplay['source']['fetched_at']))?>.</p>
