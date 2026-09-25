<?php
$adminTitle = 'Inventory Batch';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/inventory.php';

$pdo=db(); $ready=inventory_schema_ready($pdo); $id=(int)($_GET['id'] ?? $_POST['batch_id'] ?? 0); $error='';
if(!$ready || $id<1){ $error='Inventory batch unavailable.'; }

if($pdo && $ready && $id>0 && $_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null)) $error='Your session expired. Please try again.';
    else {
        $action=(string)($_POST['action']??'');
        try{
            if($action==='save_metadata'){
                $coaId=(int)($_POST['coa_document_id']??0)?:null;
                if($coaId){$b=inventory_batch($pdo,$id);$s=$pdo->prepare('SELECT product_id FROM coa_documents WHERE id=:id');$s->execute(['id'=>$coaId]);if((int)$s->fetchColumn()!==(int)$b['product_id'])throw new RuntimeException('The selected COA belongs to a different product.');}
                $pdo->prepare('UPDATE inventory_batches SET coa_document_id=:coa,expiry_date=:expiry,retest_date=:retest,storage_location=:storage,notes=:notes WHERE id=:id')->execute(['coa'=>$coaId,'expiry'=>trim((string)($_POST['expiry_date']??''))?:null,'retest'=>trim((string)($_POST['retest_date']??''))?:null,'storage'=>trim((string)($_POST['storage_location']??''))?:null,'notes'=>trim((string)($_POST['notes']??''))?:null,'id'=>$id]);
                header('Location: inventory-batch.php?id='.$id.'&saved=1');exit;
            }
            if($action==='release_checks'){
                $sql='INSERT INTO inventory_release_checks (batch_id,quantity_verified,packaging_ok,coa_linked_verified,batch_coa_match,storage_ok,release_notes) VALUES (:batch,:quantity,:packaging,:coa,:match,:storage,:notes) ON DUPLICATE KEY UPDATE quantity_verified=VALUES(quantity_verified),packaging_ok=VALUES(packaging_ok),coa_linked_verified=VALUES(coa_linked_verified),batch_coa_match=VALUES(batch_coa_match),storage_ok=VALUES(storage_ok),release_notes=VALUES(release_notes)';
                $pdo->prepare($sql)->execute(['batch'=>$id,'quantity'=>isset($_POST['quantity_verified'])?1:0,'packaging'=>isset($_POST['packaging_ok'])?1:0,'coa'=>isset($_POST['coa_linked_verified'])?1:0,'match'=>isset($_POST['batch_coa_match'])?1:0,'storage'=>isset($_POST['storage_ok'])?1:0,'notes'=>trim((string)($_POST['release_notes']??''))?:null]);
                header('Location: inventory-batch.php?id='.$id.'&checks=1');exit;
            }
            if($action==='status'){
                inventory_set_status($pdo,$id,(string)($_POST['status']??''),(int)(admin_user()['id']??0)?:null);
                header('Location: inventory-batch.php?id='.$id.'&status_saved=1');exit;
            }
            if($action==='movement'){
                inventory_apply_movement($pdo,$id,(string)($_POST['movement_type']??''),(float)($_POST['quantity_delta']??0),trim((string)($_POST['reason']??'')),trim((string)($_POST['reference']??''))?:null,(int)(admin_user()['id']??0)?:null);
                header('Location: inventory-batch.php?id='.$id.'&movement=1');exit;
            }
        }catch(Throwable $e){error_log('Moleqra inventory batch operation failed: '.$e->getMessage());$error=$e instanceof RuntimeException?$e->getMessage():'The inventory operation could not be saved.';}
    }
}

$batch=$ready&&$id>0?inventory_batch($pdo,$id):null;
$checks=[];$movements=[];$coas=[];$gate=['allowed'=>false,'blockers'=>[]];
if($batch){$s=$pdo->prepare('SELECT * FROM inventory_release_checks WHERE batch_id=:id');$s->execute(['id'=>$id]);$checks=$s->fetch()?:[];$s=$pdo->prepare('SELECT m.*,u.display_name FROM inventory_movements m LEFT JOIN admin_users u ON u.id=m.created_by WHERE m.batch_id=:id ORDER BY m.created_at DESC,m.id DESC');$s->execute(['id'=>$id]);$movements=$s->fetchAll();$s=$pdo->prepare('SELECT id,batch_number,lab_name,tested_at FROM coa_documents WHERE product_id=:product ORDER BY created_at DESC');$s->execute(['product'=>$batch['product_id']]);$coas=$s->fetchAll();$gate=inventory_release_gate($pdo,$id);}
?>
<div class="admin-heading"><div><div class="eyebrow">Batch traceability</div><h1><?= e($batch ? $batch['sku'].' · '.$batch['batch_number'] : 'Inventory batch') ?></h1></div><a class="btn" href="inventory.php">Back to inventory</a></div>
<?php if(isset($_GET['received'])):?><div class="alert success">Inventory received into quarantine.</div><?php endif;?><?php if(isset($_GET['saved'])):?><div class="alert success">Batch metadata updated.</div><?php endif;?><?php if(isset($_GET['checks'])):?><div class="alert success">Release checks updated.</div><?php endif;?><?php if(isset($_GET['status_saved'])):?><div class="alert success">Batch disposition updated.</div><?php endif;?><?php if(isset($_GET['movement'])):?><div class="alert success">Stock movement recorded.</div><?php endif;?><?php if($error):?><div class="alert error"><?= e($error) ?></div><?php endif;?>
<?php if($batch): ?>
<div class="supplier-summary-grid"><section class="card"><span class="muted">On hand</span><p><strong class="inventory-big-number"><?= e((string)$batch['units_on_hand']) ?></strong> units</p><span class="status-pill"><?= e($batch['status']) ?></span></section><section class="card"><span class="muted">Source</span><p><strong><?= e($batch['supplier_name']) ?></strong><br><?= e($batch['po_number'] ?: 'No linked PO') ?></p></section><section class="card"><span class="muted">COA</span><p><strong><?= e($batch['coa_batch_number'] ?: 'Not linked') ?></strong><?php if($batch['coa_lab_name']): ?><br><?= e($batch['coa_lab_name']) ?><?php endif; ?></p></section></div>

<section class="card admin-section-gap <?= $gate['allowed']?'gate-pass':'gate-hold' ?>"><div class="section-heading"><div><h2>Internal release gate</h2><p class="muted">This is an inventory disposition control only. It is not regulatory approval, a safety determination, or authorization for human use.</p></div><span class="status-pill"><?= $gate['allowed']?'PASS':'HOLD' ?></span></div><?php if(!$gate['allowed']): ?><ul class="admin-list"><?php foreach($gate['blockers'] as $blocker): ?><li><?= e($blocker) ?></li><?php endforeach; ?></ul><?php else: ?><p>All configured receipt/release checks are complete and the linked COA batch number matches exactly.</p><?php endif; ?></section>

<div class="admin-two-col admin-section-gap"><section class="card"><h2>Batch metadata</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_metadata"><input type="hidden" name="batch_id" value="<?= $id ?>"><div class="field"><label>Linked COA</label><select name="coa_document_id"><option value="">None</option><?php foreach($coas as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$batch['coa_document_id']===(int)$c['id']?' selected':'' ?>><?= e('Batch '.$c['batch_number'].($c['lab_name']?' · '.$c['lab_name']:'')) ?></option><?php endforeach; ?></select></div><div class="form-grid"><div class="field"><label>Expiry date</label><input type="date" name="expiry_date" value="<?= e($batch['expiry_date']) ?>"></div><div class="field"><label>Retest date</label><input type="date" name="retest_date" value="<?= e($batch['retest_date']) ?>"></div><div class="field full"><label>Storage location</label><input name="storage_location" maxlength="120" value="<?= e($batch['storage_location']) ?>"></div><div class="field full"><label>Notes</label><textarea name="notes"><?= e($batch['notes']) ?></textarea></div></div><button class="btn">Save metadata</button></form></section>
<section class="card"><h2>Receipt / release checks</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="release_checks"><input type="hidden" name="batch_id" value="<?= $id ?>"><div class="control-checklist"><label><input type="checkbox" name="quantity_verified"<?= !empty($checks['quantity_verified'])?' checked':'' ?>>Quantity verified</label><label><input type="checkbox" name="packaging_ok"<?= !empty($checks['packaging_ok'])?' checked':'' ?>>Packaging/condition acceptable</label><label><input type="checkbox" name="coa_linked_verified"<?= !empty($checks['coa_linked_verified'])?' checked':'' ?>>COA linkage verified</label><label><input type="checkbox" name="batch_coa_match"<?= !empty($checks['batch_coa_match'])?' checked':'' ?>>Batch matches COA</label><label><input type="checkbox" name="storage_ok"<?= !empty($checks['storage_ok'])?' checked':'' ?>>Storage/location checked</label></div><div class="field admin-section-gap"><label>Release notes</label><textarea name="release_notes"><?= e($checks['release_notes']??'') ?></textarea></div><button class="btn">Save checks</button></form></section></div>

<div class="admin-two-col admin-section-gap"><section class="card"><h2>Disposition</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="batch_id" value="<?= $id ?>"><div class="field"><label>Status</label><select name="status"><?php foreach(inventory_batch_statuses() as $st): ?><option<?= $batch['status']===$st?' selected':'' ?>><?= e($st) ?></option><?php endforeach; ?></select></div><button class="btn primary">Update disposition</button></form></section>
<section class="card"><h2>Record stock movement</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="movement"><input type="hidden" name="batch_id" value="<?= $id ?>"><div class="form-grid"><div class="field"><label>Type</label><select name="movement_type"><option>Adjustment</option><option>Correction</option><option>Sample</option><option>Write-off</option><option>Return</option></select></div><div class="field"><label>Quantity</label><input type="number" step="0.001" name="quantity_delta" required><small>Sample, write-off and return are always treated as stock reductions.</small></div><div class="field"><label>Reference</label><input name="reference" maxlength="140"></div><div class="field full"><label>Reason</label><textarea name="reason" required></textarea></div></div><button class="btn">Record movement</button></form></section></div>

<section class="card admin-section-gap"><h2>Movement ledger</h2><div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Type</th><th>Delta</th><th>Reference</th><th>Reason</th><th>By</th></tr></thead><tbody><?php foreach($movements as $m): ?><tr><td><?= e($m['created_at']) ?></td><td><?= e($m['movement_type']) ?></td><td><?= e((string)$m['quantity_delta']) ?></td><td><?= e($m['reference']) ?></td><td><?= e($m['reason']) ?></td><td><?= e($m['display_name'] ?: 'System') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
