<?php $requestForm=$selected; ?>
<form method="post">
  <input type="hidden" name="csrf" value="<?=h(csrf())?>">
  <input type="hidden" name="action" value="<?=$requestForm?'edit_request':'create'?>">
  <input type="hidden" name="project_id" value="<?=$projectId?>">
  <?php if ($requestForm): ?><input type="hidden" name="request_id" value="<?=$requestForm['id']?>"><?php endif; ?>
  <div class="detail">
    <div>
      <div class="field"><label>Título <input name="title" maxlength="240" value="<?=h($requestForm['title']??'')?>" required></label></div>
      <div class="field"><label>Descripción <textarea name="description"><?=h($requestForm['description']??'')?></textarea></label></div>
    </div>
    <div>
      <div class="field"><label>Responsable <select name="assigned_to">
        <option value="">Sin asignar</option>
        <?php foreach ($members as $member): ?><option value="<?=$member['id']?>" <?php if ((int)$member['id']===(int)($requestForm['assigned_to']??0)): ?>selected<?php endif; ?>><?=h($member['name'])?></option><?php endforeach; ?>
      </select></label></div>
      <div class="field"><label>Prioridad <select name="priority">
        <?php foreach (['normal'=>'Normal','high'=>'Alta','critical'=>'Crítica'] as $value=>$label): ?><option value="<?=$value?>" <?php if ($value===($requestForm['priority']??'normal')): ?>selected<?php endif; ?>><?=h($label)?></option><?php endforeach; ?>
      </select></label></div>
      <div class="field"><label>Fecha límite <input name="due_date" type="date" value="<?=h($requestForm['due_date']??'')?>"></label></div>
    </div>
  </div>
  <button><?=$requestForm?'Guardar cambios':'Crear solicitud'?></button>
</form>
