<?php declare(strict_types=1); if(!$fieldRows)return; ?>
<div class="api-table-wrap"><table>
<thead><tr><th>Câmp</th><th>Tip</th><th>Obligatoriu</th><th>Semnificație și limite</th></tr></thead>
<tbody><?php foreach($fieldRows as$field):?><tr>
<td><code><?=e($field['name'])?></code></td><td><?=e($field['type'])?></td>
<td><?=$field['required']?'Da':'Nu / după variantă'?></td>
<td><?=e($field['description'])?><?php if($field['rules']):?><p class="api-field-rules"><?=e($field['rules'])?></p><?php endif?></td>
</tr><?php endforeach?></tbody></table></div>
