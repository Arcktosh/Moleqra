<section class="card admin-section-gap">
<h2>Qualification controls</h2><p class="muted">Record evidence that has actually been received or independently checked. Unchecked items remain outstanding.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="qualification">
<div class="control-checklist"><?php foreach(sourcing_control_labels() as $field=>$label): ?><label><input type="checkbox" name="<?= e($field) ?>" <?= !empty($qualification[$field])?'checked':'' ?>><span><?= e($label) ?></span></label><?php endforeach; ?></div>
<div class="form-grid admin-form-gap"><div class="field"><label>Last reviewed</label><input type="date" name="reviewed_at" value="<?= e($qualification['reviewed_at'] ?? '') ?>"></div><div class="field full"><label>Qualification notes</label><textarea name="qualification_notes" maxlength="10000"><?= e($qualification['qualification_notes'] ?? '') ?></textarea></div><div class="field full"><button class="btn primary">Save qualification</button></div></div>
</form></section>
