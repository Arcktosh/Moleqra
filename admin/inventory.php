<?php
$adminTitle = 'Inventory';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/inventory.php';

$pdo = db();
$ready = inventory_schema_ready($pdo);
$error = '';
$receiveItemId = (int)($_GET['receive_item'] ?? 0);
$prefill = null;

if ($ready && $receiveItemId > 0) {
    $stmt=$pdo->prepare('SELECT poi.*,po.supplier_id,po.po_number,po.currency,p.sku,p.name product_name,s.name supplier_name
                         FROM purchase_order_items poi JOIN purchase_orders po ON po.id=poi.purchase_order_id JOIN products p ON p.id=poi.product_id JOIN suppliers s ON s.id=po.supplier_id WHERE poi.id=:id');
    $stmt->execute(['id'=>$receiveItemId]); $prefill=$stmt->fetch() ?: null;
}

if ($pdo && $ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) $error='Your session expired. Please try again.';
    else {
        try {
            $batchId = inventory_receive_batch($pdo, $_POST, (int)(admin_user()['id'] ?? 0) ?: null);
            header('Location: inventory-batch.php?id=' . $batchId . '&received=1'); exit;
        } catch (Throwable $e) {
            error_log('Moleqra inventory receipt failed: ' . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'The inventory receipt could not be saved.';
        }
    }
}

$products=$pdo?$pdo->query('SELECT id,sku,name FROM products ORDER BY name')->fetchAll():[];
$suppliers=$pdo?$pdo->query('SELECT id,name FROM suppliers ORDER BY name')->fetchAll():[];
$coas=$pdo?$pdo->query('SELECT c.id,c.product_id,c.batch_number,c.lab_name,p.sku FROM coa_documents c JOIN products p ON p.id=c.product_id ORDER BY c.created_at DESC')->fetchAll():[];
$batches=$ready?$pdo->query('SELECT b.*,p.sku,p.name product_name,s.name supplier_name,c.batch_number coa_batch,
    CASE WHEN b.expiry_date IS NOT NULL AND b.expiry_date<CURDATE() THEN 1 ELSE 0 END expired,
    CASE WHEN b.retest_date IS NOT NULL AND b.retest_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) THEN 1 ELSE 0 END retest_due
    FROM inventory_batches b JOIN products p ON p.id=b.product_id JOIN suppliers s ON s.id=b.supplier_id LEFT JOIN coa_documents c ON c.id=b.coa_document_id ORDER BY b.received_at DESC,b.id DESC')->fetchAll():[];
$summary=$ready?$pdo->query("SELECT p.id,p.sku,p.name,
    COALESCE(SUM(CASE WHEN b.status='Released' THEN b.units_on_hand ELSE 0 END),0) released_units,
    COALESCE(SUM(CASE WHEN b.status='Quarantine' THEN b.units_on_hand ELSE 0 END),0) quarantine_units,
    COALESCE(SUM(CASE WHEN b.status='Hold' THEN b.units_on_hand ELSE 0 END),0) hold_units,
    COALESCE(SUM(b.units_on_hand),0) total_units
    FROM products p LEFT JOIN inventory_batches b ON b.product_id=p.id GROUP BY p.id,p.sku,p.name HAVING total_units>0 ORDER BY p.name")->fetchAll():[];
?>
<div class="admin-heading"><div><div class="eyebrow">Stock control</div><h1>Inventory</h1></div><a class="btn" href="purchase-orders.php">Purchase orders</a></div>
<?php if(!$ready): ?><div class="alert error">V5 inventory is not installed. <a href="system.php">Run the inventory upgrade</a>.</div><?php endif; ?>
<?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if($ready): ?>
<section class="card"><div class="section-heading"><div><h2>Receive inventory lot</h2><p class="muted">Every receipt enters <strong>Quarantine</strong>. Internal release requires a linked matching COA and completed receipt checks.</p></div><?php if($prefill): ?><span class="tag"><?= e($prefill['po_number']) ?></span><?php endif; ?></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="purchase_order_item_id" value="<?= (int)($prefill['id'] ?? 0) ?>"><input type="hidden" name="reference" value="<?= e($prefill['po_number'] ?? '') ?>"><div class="form-grid">
<div class="field"><label>Product</label><select name="product_id" required><?php if(!$prefill): ?><option value="">Choose product</option><?php endif; ?><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>"<?= $prefill && (int)$prefill['product_id']===(int)$p['id']?' selected':'' ?>><?= e($p['sku'].' · '.$p['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Supplier</label><select name="supplier_id" required><?php if(!$prefill): ?><option value="">Choose supplier</option><?php endif; ?><?php foreach($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $prefill && (int)$prefill['supplier_id']===(int)$s['id']?' selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Batch / lot number</label><input name="batch_number" maxlength="100" required></div><div class="field"><label>Received date</label><input type="date" name="received_at" value="<?= e(date('Y-m-d')) ?>"></div>
<div class="field"><label>Quantity received (units)</label><input type="number" name="units_received" min="0.001" step="0.001" required<?= $prefill?' value="'.e((string)max(0,(float)$prefill['quantity_ordered']-(float)$prefill['quantity_received'])).'"':'' ?>></div>
<div class="field"><label>Unit cost in <?= e(procurement_base_currency()) ?></label><input type="number" name="unit_cost_base" min="0" step="0.0001"<?= $prefill && strtoupper((string)$prefill['currency'])===procurement_base_currency()?' value="'.e((string)$prefill['unit_cost']).'"':'' ?>><?php if($prefill && strtoupper((string)$prefill['currency'])!==procurement_base_currency()): ?><small>PO is in <?= e($prefill['currency']) ?>; enter the approved base-currency unit cost separately.</small><?php endif; ?></div>
<div class="field"><label>Expiry date</label><input type="date" name="expiry_date"></div><div class="field"><label>Retest date</label><input type="date" name="retest_date"></div>
<div class="field"><label>Storage location</label><input name="storage_location" maxlength="120"></div><div class="field"><label>Linked COA (optional at receipt)</label><select name="coa_document_id"><option value="">None yet</option><?php foreach($coas as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['sku'].' · batch '.$c['batch_number'].($c['lab_name']?' · '.$c['lab_name']:'')) ?></option><?php endforeach; ?></select></div>
<div class="field full"><label>Receipt notes</label><textarea name="notes"></textarea></div></div><button class="btn primary">Receive into quarantine</button></form></section>

<section class="card admin-section-gap"><h2>Stock by product</h2><div class="table-wrap"><table class="data-table"><thead><tr><th>Product</th><th>Released</th><th>Quarantine</th><th>Hold</th><th>Total on hand</th></tr></thead><tbody><?php foreach($summary as $s): ?><tr><td><?= e($s['sku'].' · '.$s['name']) ?></td><td><?= e((string)$s['released_units']) ?></td><td><?= e((string)$s['quarantine_units']) ?></td><td><?= e((string)$s['hold_units']) ?></td><td><strong><?= e((string)$s['total_units']) ?></strong></td></tr><?php endforeach; ?><?php if(!$summary): ?><tr><td colspan="5">No inventory has been received.</td></tr><?php endif; ?></tbody></table></div></section>

<section class="card admin-section-gap"><h2>Batch register</h2><div class="table-wrap"><table class="data-table"><thead><tr><th>Batch</th><th>Product</th><th>Supplier</th><th>Status</th><th>On hand</th><th>Dates</th><th></th></tr></thead><tbody><?php foreach($batches as $b): ?><tr><td><?= e($b['batch_number']) ?><?= $b['coa_batch']?'<br><small>COA linked</small>':'' ?></td><td><?= e($b['sku'].' · '.$b['product_name']) ?></td><td><?= e($b['supplier_name']) ?></td><td><span class="status-pill"><?= e($b['status']) ?></span><?php if($b['expired']): ?><br><small class="stock-alert">Expired</small><?php elseif($b['retest_due']): ?><br><small class="stock-warn">Retest due</small><?php endif; ?></td><td><?= e((string)$b['units_on_hand']) ?></td><td><small>Received <?= e($b['received_at']) ?><?php if($b['expiry_date']): ?><br>Expiry <?= e($b['expiry_date']) ?><?php endif; ?><?php if($b['retest_date']): ?><br>Retest <?= e($b['retest_date']) ?><?php endif; ?></small></td><td><a href="inventory-batch.php?id=<?= (int)$b['id'] ?>">Open</a></td></tr><?php endforeach; ?><?php if(!$batches): ?><tr><td colspan="7">No batches yet.</td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
