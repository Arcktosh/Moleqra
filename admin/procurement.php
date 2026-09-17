<?php
$adminTitle = 'Procurement';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/procurement.php';

$pdo = db();
$ready = procurement_schema_ready($pdo);
$error = '';
$message = '';

if ($pdo && $ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            $offerId = (int)($_POST['supplier_product_id'] ?? 0);
            $name = trim((string)($_POST['scenario_name'] ?? 'Current quote'));
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            $unitPrice = max(0, (float)($_POST['unit_price'] ?? 0));
            $currency = sourcing_currency((string)($_POST['currency'] ?? procurement_base_currency()));
            $fx = max(0.000001, (float)($_POST['fx_to_base'] ?? 1));
            if ($offerId < 1) throw new RuntimeException('Choose a supplier-product offer.');
            if ($unitPrice <= 0) throw new RuntimeException('Enter a unit price greater than zero.');
            $sql = 'INSERT INTO procurement_scenarios
                    (supplier_product_id,scenario_name,quantity,unit_price,currency,fx_to_base,shipping_cost,duty_tax_cost,lab_testing_cost,packaging_label_cost,payment_fee_cost,other_cost,quoted_at,valid_until,notes)
                    VALUES (:offer,:name,:quantity,:unit_price,:currency,:fx,:shipping,:duty,:testing,:packaging,:fees,:other,:quoted,:valid_until,:notes)';
            $pdo->prepare($sql)->execute([
                'offer'=>$offerId,'name'=>$name ?: 'Current quote','quantity'=>$quantity,'unit_price'=>$unitPrice,'currency'=>$currency,'fx'=>$fx,
                'shipping'=>procurement_decimal($_POST['shipping_cost'] ?? 0),'duty'=>procurement_decimal($_POST['duty_tax_cost'] ?? 0),
                'testing'=>procurement_decimal($_POST['lab_testing_cost'] ?? 0),'packaging'=>procurement_decimal($_POST['packaging_label_cost'] ?? 0),
                'fees'=>procurement_decimal($_POST['payment_fee_cost'] ?? 0),'other'=>procurement_decimal($_POST['other_cost'] ?? 0),
                'quoted'=>trim((string)($_POST['quoted_at'] ?? '')) ?: null,'valid_until'=>trim((string)($_POST['valid_until'] ?? '')) ?: null,
                'notes'=>trim((string)($_POST['notes'] ?? '')) ?: null,
            ]);
            $message = 'Procurement scenario saved.';
        } catch (Throwable $e) {
            error_log('Moleqra procurement scenario failed: ' . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'The procurement scenario could not be saved.';
        }
    }
}

$offers = [];
$scenarios = [];
if ($pdo && $ready) {
    $offers = $pdo->query("SELECT sp.id, sp.wholesale_price, sp.currency, sp.moq_units, sp.lead_time_days, sp.coa_available,
                                  s.name AS supplier_name, s.status AS supplier_status, p.sku, p.name AS product_name
                           FROM supplier_products sp
                           JOIN suppliers s ON s.id=sp.supplier_id
                           JOIN products p ON p.id=sp.product_id
                           ORDER BY p.name, s.name")->fetchAll();
    $scenarios = $pdo->query("SELECT ps.*, sp.product_id, s.name AS supplier_name, s.status AS supplier_status,
                                     p.sku, p.name AS product_name
                              FROM procurement_scenarios ps
                              JOIN supplier_products sp ON sp.id=ps.supplier_product_id
                              JOIN suppliers s ON s.id=sp.supplier_id
                              JOIN products p ON p.id=sp.product_id
                              ORDER BY ps.created_at DESC, ps.id DESC")->fetchAll();
}
$rows = [];
foreach ($scenarios as $scenario) {
    $calc = procurement_calculate($scenario);
    $scenario['_calc'] = $calc;
    $rows[] = $scenario;
}
usort($rows, fn($a,$b) => ($a['_calc']['landed_unit_base'] <=> $b['_calc']['landed_unit_base']));
?>
<div class="admin-heading"><div><div class="eyebrow">Commercial modelling</div><h1>Procurement</h1><p class="muted">Compare quote scenarios using landed cost in <?= e(procurement_base_currency()) ?>. Cost position does not constitute supplier approval.</p></div><span class="tag">Base <?= e(procurement_base_currency()) ?></span></div>
<?php if (!$ready): ?><div class="alert error">The procurement/launch upgrade is not installed. <a href="system.php">Run the V4 upgrade</a>.</div><?php endif; ?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if ($ready): ?>
<div class="admin-two-col">
<section class="card"><h2>Add quote scenario</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="field"><label>Supplier offer</label><select id="supplier_product_id" name="supplier_product_id" required><option value="">Select offer</option><?php foreach($offers as $o): ?><option value="<?= (int)$o['id'] ?>" data-price="<?= e((string)($o['wholesale_price'] ?? '')) ?>" data-currency="<?= e($o['currency'] ?? procurement_base_currency()) ?>" data-moq="<?= e((string)($o['moq_units'] ?? '')) ?>"><?= e($o['sku'].' — '.$o['product_name'].' — '.$o['supplier_name']) ?></option><?php endforeach; ?></select></div>
<div class="form-grid"><div class="field"><label>Scenario name</label><input name="scenario_name" maxlength="120" value="Current quote"></div><div class="field"><label>Quantity</label><input id="proc_quantity" name="quantity" type="number" min="1" value="1" required></div><div class="field"><label>Unit price</label><input id="proc_unit_price" name="unit_price" type="number" min="0" step="0.0001" required></div><div class="field"><label>Quote currency</label><input id="proc_currency" name="currency" maxlength="3" value="<?= e(procurement_base_currency()) ?>" required></div><div class="field"><label>FX to <?= e(procurement_base_currency()) ?> <small>(1 quote unit = X <?= e(procurement_base_currency()) ?>)</small></label><input name="fx_to_base" type="number" min="0.000001" step="0.000001" value="1" required></div><div class="field"><label>Shipping</label><input name="shipping_cost" type="number" min="0" step="0.01" value="0"></div><div class="field"><label>Duty / tax estimate</label><input name="duty_tax_cost" type="number" min="0" step="0.01" value="0"></div><div class="field"><label>Independent testing</label><input name="lab_testing_cost" type="number" min="0" step="0.01" value="0"></div><div class="field"><label>Packaging / labels</label><input name="packaging_label_cost" type="number" min="0" step="0.01" value="0"></div><div class="field"><label>Payment / transfer fees</label><input name="payment_fee_cost" type="number" min="0" step="0.01" value="0"></div><div class="field"><label>Other costs</label><input name="other_cost" type="number" min="0" step="0.01" value="0"></div><div class="field"><label>Quote date</label><input name="quoted_at" type="date"></div><div class="field"><label>Valid until</label><input name="valid_until" type="date"></div><div class="field full"><label>Notes</label><textarea name="notes" maxlength="5000"></textarea></div><div class="field full"><button class="btn primary">Save scenario</button></div></div>
</form></section>
<section class="card"><h2>How landed cost is calculated</h2><p class="muted">Goods value is converted to <?= e(procurement_base_currency()) ?> using the FX value you enter. Shipping, duties/taxes, testing, packaging, payment fees and other costs are also treated as quote-currency amounts and converted using the same FX value.</p><div class="notice"><strong>Planning estimate only.</strong> Duties, taxes, import permissions, testing obligations and other charges must be confirmed independently before purchase.</div></section>
</div>
<section class="card admin-section-gap"><div class="section-heading"><div><h2>Scenario comparison</h2><p class="muted">Sorted by calculated landed unit cost; no supplier is automatically selected or approved.</p></div></div><div class="table-wrap"><table class="data-table sourcing-table"><thead><tr><th>Product</th><th>Supplier</th><th>Scenario</th><th>Qty</th><th>Quote</th><th>Extras</th><th>Landed total</th><th>Landed / unit</th><th>Validity</th></tr></thead><tbody>
<?php foreach($rows as $r): $c=$r['_calc']; ?><tr><td><strong><?= e($r['sku']) ?></strong><br><small><?= e($r['product_name']) ?></small></td><td><?= e($r['supplier_name']) ?><br><small><?= e($r['supplier_status']) ?></small></td><td><?= e($r['scenario_name']) ?></td><td><?= (int)$c['quantity'] ?></td><td><?= e($r['currency']) ?> <?= number_format((float)$r['unit_price'],4) ?>/unit<br><small>FX <?= number_format((float)$r['fx_to_base'],6) ?></small></td><td><?= procurement_money_base($c['extras_base']) ?></td><td><?= procurement_money_base($c['landed_total_base']) ?></td><td><strong><?= procurement_money_base($c['landed_unit_base']) ?></strong></td><td><?= e($r['valid_until'] ?: '—') ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="9">No procurement scenarios recorded.</td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; ?>
<script>
(function(){
  const offer=document.getElementById('supplier_product_id');
  if(!offer)return;
  offer.addEventListener('change',function(){
    const opt=this.options[this.selectedIndex];
    const price=document.getElementById('proc_unit_price');
    const currency=document.getElementById('proc_currency');
    const qty=document.getElementById('proc_quantity');
    if(opt.dataset.price && !price.value) price.value=opt.dataset.price;
    if(opt.dataset.currency) currency.value=opt.dataset.currency;
    if(opt.dataset.moq && (!qty.value || qty.value==='1')) qty.value=opt.dataset.moq;
  });
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
