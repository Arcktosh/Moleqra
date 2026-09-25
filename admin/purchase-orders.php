<?php
$adminTitle = 'Purchase Orders';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/inventory.php';

$pdo = db();
$ready = inventory_schema_ready($pdo);
$error = '';
$message = '';
$viewId = (int)($_GET['view'] ?? 0);

if ($pdo && $ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create_po') {
                $supplierId = (int)($_POST['supplier_id'] ?? 0);
                $poNumber = strtoupper(trim((string)($_POST['po_number'] ?? '')));
                if ($poNumber === '') $poNumber = 'PO-' . date('Ymd-His');
                $orderedAt = trim((string)($_POST['ordered_at'] ?? '')) ?: date('Y-m-d');
                $expectedAt = trim((string)($_POST['expected_at'] ?? '')) ?: null;
                $currency = sourcing_currency((string)($_POST['currency'] ?? procurement_base_currency()));
                if ($supplierId < 1) throw new RuntimeException('Choose a supplier.');
                $stmt = $pdo->prepare('INSERT INTO purchase_orders (supplier_id,po_number,ordered_at,expected_at,status,currency,shipping_cost,other_cost,notes,created_by)
                                       VALUES (:supplier,:po,:ordered,:expected,\'Draft\',:currency,:shipping,:other,:notes,:admin)');
                $stmt->execute([
                    'supplier'=>$supplierId,'po'=>$poNumber,'ordered'=>$orderedAt,'expected'=>$expectedAt,'currency'=>$currency,
                    'shipping'=>max(0,(float)($_POST['shipping_cost'] ?? 0)),'other'=>max(0,(float)($_POST['other_cost'] ?? 0)),
                    'notes'=>trim((string)($_POST['notes'] ?? '')) ?: null,'admin'=>(admin_user()['id'] ?? null),
                ]);
                $viewId = (int)$pdo->lastInsertId();
                header('Location: purchase-orders.php?view=' . $viewId . '&saved=1'); exit;
            }

            if ($action === 'add_item') {
                $poId = (int)($_POST['purchase_order_id'] ?? 0);
                $productId = (int)($_POST['product_id'] ?? 0);
                $offerId = (int)($_POST['supplier_product_id'] ?? 0) ?: null;
                $qty = inventory_decimal($_POST['quantity_ordered'] ?? 0);
                $unitCost = max(0,(float)($_POST['unit_cost'] ?? 0));
                $stmt = $pdo->prepare('SELECT supplier_id FROM purchase_orders WHERE id=:id'); $stmt->execute(['id'=>$poId]); $supplierId = (int)$stmt->fetchColumn();
                if ($poId < 1 || $supplierId < 1 || $productId < 1 || $qty <= 0) throw new RuntimeException('PO, product and positive quantity are required.');
                if ($offerId) {
                    $stmt = $pdo->prepare('SELECT supplier_id,product_id FROM supplier_products WHERE id=:id'); $stmt->execute(['id'=>$offerId]); $offer=$stmt->fetch();
                    if (!$offer || (int)$offer['supplier_id'] !== $supplierId || (int)$offer['product_id'] !== $productId) throw new RuntimeException('The selected supplier offer does not match this PO.');
                }
                $pdo->prepare('INSERT INTO purchase_order_items (purchase_order_id,product_id,supplier_product_id,description,quantity_ordered,unit_cost)
                               VALUES (:po,:product,:offer,:description,:qty,:cost)')
                    ->execute(['po'=>$poId,'product'=>$productId,'offer'=>$offerId,'description'=>trim((string)($_POST['description'] ?? '')) ?: null,'qty'=>$qty,'cost'=>$unitCost]);
                header('Location: purchase-orders.php?view=' . $poId . '&item_saved=1'); exit;
            }

            if ($action === 'update_po') {
                $poId = (int)($_POST['purchase_order_id'] ?? 0);
                $status = (string)($_POST['status'] ?? 'Draft');
                if (!in_array($status, purchase_order_statuses(), true)) throw new RuntimeException('Invalid PO status.');
                $pdo->prepare('UPDATE purchase_orders SET expected_at=:expected,status=:status,shipping_cost=:shipping,other_cost=:other,notes=:notes WHERE id=:id')
                    ->execute(['expected'=>trim((string)($_POST['expected_at'] ?? '')) ?: null,'status'=>$status,'shipping'=>max(0,(float)($_POST['shipping_cost'] ?? 0)),'other'=>max(0,(float)($_POST['other_cost'] ?? 0)),'notes'=>trim((string)($_POST['notes'] ?? '')) ?: null,'id'=>$poId]);
                header('Location: purchase-orders.php?view=' . $poId . '&updated=1'); exit;
            }
        } catch (Throwable $e) {
            error_log('Moleqra PO operation failed: ' . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'The purchase order operation could not be saved.';
        }
    }
}

$suppliers = $pdo ? $pdo->query('SELECT id,name,status FROM suppliers ORDER BY name')->fetchAll() : [];
$products = $pdo ? $pdo->query('SELECT id,sku,name FROM products ORDER BY name')->fetchAll() : [];
$orders = $ready ? $pdo->query('SELECT po.*,s.name supplier_name,
    (SELECT COUNT(*) FROM purchase_order_items i WHERE i.purchase_order_id=po.id) item_count,
    (SELECT COALESCE(SUM(i.quantity_ordered*i.unit_cost),0) FROM purchase_order_items i WHERE i.purchase_order_id=po.id) goods_total
    FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id ORDER BY po.ordered_at DESC,po.id DESC LIMIT 200')->fetchAll() : [];
$selected = null; $items = []; $offers = [];
if ($ready && $viewId > 0) {
    $stmt=$pdo->prepare('SELECT po.*,s.name supplier_name FROM purchase_orders po JOIN suppliers s ON s.id=po.supplier_id WHERE po.id=:id'); $stmt->execute(['id'=>$viewId]); $selected=$stmt->fetch() ?: null;
    if ($selected) {
        $stmt=$pdo->prepare('SELECT i.*,p.sku,p.name product_name,sp.supplier_sku FROM purchase_order_items i JOIN products p ON p.id=i.product_id LEFT JOIN supplier_products sp ON sp.id=i.supplier_product_id WHERE i.purchase_order_id=:id ORDER BY i.id'); $stmt->execute(['id'=>$viewId]); $items=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT sp.id,sp.product_id,p.sku,p.name,sp.wholesale_price,sp.currency,sp.moq_units FROM supplier_products sp JOIN products p ON p.id=sp.product_id WHERE sp.supplier_id=:supplier ORDER BY p.name'); $stmt->execute(['supplier'=>$selected['supplier_id']]); $offers=$stmt->fetchAll();
    }
}
?>
<div class="admin-heading"><div><div class="eyebrow">Inventory acquisition</div><h1>Purchase orders</h1></div><a class="btn" href="inventory.php">Inventory</a></div>
<?php if (!$ready): ?><div class="alert error">V5 inventory is not installed. <a href="system.php">Run the inventory upgrade</a>.</div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert success">Purchase order created.</div><?php endif; ?>
<?php if (isset($_GET['item_saved'])): ?><div class="alert success">PO line added.</div><?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="alert success">Purchase order updated.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<?php if ($ready): ?>
<div class="admin-two-col">
<section class="card"><h2>Create purchase order</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_po"><div class="field"><label>Supplier</label><select name="supplier_id" required><option value="">Choose supplier</option><?php foreach($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name'].' · '.$s['status']) ?></option><?php endforeach; ?></select></div><div class="form-grid"><div class="field"><label>PO number</label><input name="po_number" maxlength="100" placeholder="Auto-generated if blank"></div><div class="field"><label>Currency</label><input name="currency" maxlength="3" value="<?= e(procurement_base_currency()) ?>"></div><div class="field"><label>Order date</label><input type="date" name="ordered_at" value="<?= e(date('Y-m-d')) ?>"></div><div class="field"><label>Expected date</label><input type="date" name="expected_at"></div><div class="field"><label>Shipping cost</label><input type="number" name="shipping_cost" min="0" step="0.01" value="0"></div><div class="field"><label>Other order cost</label><input type="number" name="other_cost" min="0" step="0.01" value="0"></div><div class="field full"><label>Notes</label><textarea name="notes"></textarea></div></div><button class="btn primary">Create PO</button></form></section>
<section class="card"><h2>Purchase orders</h2><div class="table-wrap"><table class="data-table"><thead><tr><th>PO</th><th>Supplier</th><th>Status</th><th>Goods</th><th></th></tr></thead><tbody><?php foreach($orders as $po): ?><tr><td><?= e($po['po_number']) ?><br><small><?= e($po['ordered_at']) ?></small></td><td><?= e($po['supplier_name']) ?></td><td><span class="status-pill"><?= e($po['status']) ?></span></td><td><?= e($po['currency']) ?> <?= number_format((float)$po['goods_total'],2) ?><br><small><?= (int)$po['item_count'] ?> line(s)</small></td><td><a href="purchase-orders.php?view=<?= (int)$po['id'] ?>">Open</a></td></tr><?php endforeach; ?><?php if(!$orders): ?><tr><td colspan="5">No purchase orders yet.</td></tr><?php endif; ?></tbody></table></div></section>
</div>

<?php if($selected): ?>
<section class="card admin-section-gap"><div class="section-heading"><div><h2><?= e($selected['po_number']) ?> · <?= e($selected['supplier_name']) ?></h2><p class="muted">Receive individual PO lines into batch inventory so each lot retains its traceability.</p></div><span class="status-pill"><?= e($selected['status']) ?></span></div>
<form method="post" class="form-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_po"><input type="hidden" name="purchase_order_id" value="<?= (int)$selected['id'] ?>"><div class="field"><label>Status</label><select name="status"><?php foreach(purchase_order_statuses() as $st): ?><option<?= $selected['status']===$st?' selected':'' ?>><?= e($st) ?></option><?php endforeach; ?></select></div><div class="field"><label>Expected date</label><input type="date" name="expected_at" value="<?= e($selected['expected_at']) ?>"></div><div class="field"><label>Shipping cost</label><input type="number" name="shipping_cost" min="0" step="0.01" value="<?= e((string)$selected['shipping_cost']) ?>"></div><div class="field"><label>Other cost</label><input type="number" name="other_cost" min="0" step="0.01" value="<?= e((string)$selected['other_cost']) ?>"></div><div class="field full"><label>Notes</label><textarea name="notes"><?= e($selected['notes']) ?></textarea></div><div class="field full"><button class="btn">Update PO</button></div></form>
<div class="table-wrap admin-section-gap"><table class="data-table"><thead><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Unit cost</th><th></th></tr></thead><tbody><?php foreach($items as $i): ?><tr><td><?= e($i['sku'].' · '.$i['product_name']) ?></td><td><?= e((string)$i['quantity_ordered']) ?></td><td><?= e((string)$i['quantity_received']) ?></td><td><?= e($selected['currency']) ?> <?= number_format((float)$i['unit_cost'],4) ?></td><td><?php if($selected['status']!=='Cancelled'): ?><a href="inventory.php?receive_item=<?= (int)$i['id'] ?>">Receive lot</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$items): ?><tr><td colspan="5">No PO lines yet.</td></tr><?php endif; ?></tbody></table></div>
</section>
<section class="card admin-section-gap"><h2>Add PO line</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_item"><input type="hidden" name="purchase_order_id" value="<?= (int)$selected['id'] ?>"><div class="form-grid"><div class="field"><label>Product</label><select name="product_id" required><option value="">Choose product</option><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['sku'].' · '.$p['name']) ?></option><?php endforeach; ?></select></div><div class="field"><label>Supplier offer (optional)</label><select name="supplier_product_id"><option value="">Unlinked/manual line</option><?php foreach($offers as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['sku'].' · '.$o['name'].' · '.$o['currency'].' '.number_format((float)$o['wholesale_price'],2)) ?></option><?php endforeach; ?></select></div><div class="field"><label>Quantity</label><input type="number" name="quantity_ordered" min="0.001" step="0.001" required></div><div class="field"><label>Unit cost</label><input type="number" name="unit_cost" min="0" step="0.0001" value="0"></div><div class="field full"><label>Description / pack note</label><input name="description" maxlength="180"></div></div><button class="btn primary">Add line</button></form></section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
