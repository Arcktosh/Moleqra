<?php
$adminTitle = 'Sourcing';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/sourcing.php';

$pdo = db();
$ready = sourcing_schema_ready($pdo);
$suppliers = [];
$offers = [];
$metrics = ['suppliers' => 0, 'contacted' => 0, 'qualified' => 0, 'followups' => 0, 'tests' => 0];

if ($pdo) {
    $metrics['suppliers'] = (int)$pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
    $metrics['contacted'] = (int)$pdo->query("SELECT COUNT(*) FROM suppliers WHERE status IN ('Contacted','Evaluating','Qualified')")->fetchColumn();
    $metrics['qualified'] = (int)$pdo->query("SELECT COUNT(*) FROM suppliers WHERE status = 'Qualified'")->fetchColumn();
}

if ($ready && $pdo) {
    $metrics['followups'] = (int)$pdo->query("SELECT COUNT(*) FROM supplier_outreach WHERE next_follow_up IS NOT NULL AND next_follow_up <= CURDATE() AND outcome NOT IN ('Closed','Rejected')")->fetchColumn();
    $metrics['tests'] = (int)$pdo->query("SELECT COUNT(*) FROM supplier_test_orders WHERE status NOT IN ('Completed','Cancelled')")->fetchColumn();

    $sql = "SELECT s.*, q.business_verified, q.sample_coa_received, q.batch_specific_coa, q.hplc_present,
                   q.mass_spec_present, q.coa_identity_matches, q.ruo_label_confirmed, q.private_label_confirmed,
                   q.shipping_confirmed, q.payment_terms_confirmed, q.returns_process_confirmed, q.reviewed_at,
                   (SELECT COUNT(*) FROM supplier_products sp WHERE sp.supplier_id=s.id) AS offer_count,
                   (SELECT COUNT(*) FROM supplier_test_orders st WHERE st.supplier_id=s.id) AS test_count,
                   (SELECT MIN(so.next_follow_up) FROM supplier_outreach so WHERE so.supplier_id=s.id AND so.next_follow_up IS NOT NULL AND so.outcome NOT IN ('Closed','Rejected')) AS next_follow_up
            FROM suppliers s
            LEFT JOIN supplier_qualifications q ON q.supplier_id=s.id
            ORDER BY FIELD(s.status,'Qualified','Evaluating','Contacted','Prospect','Rejected'), s.name";
    $suppliers = $pdo->query($sql)->fetchAll();

    $offers = $pdo->query("SELECT sp.*, s.name AS supplier_name, p.sku, p.name AS product_name
                           FROM supplier_products sp
                           JOIN suppliers s ON s.id=sp.supplier_id
                           JOIN products p ON p.id=sp.product_id
                           ORDER BY p.name, s.name")->fetchAll();
}
?>
<div class="admin-heading"><div><div class="eyebrow">Supplier operations</div><h1>Sourcing</h1></div><a class="btn" href="suppliers.php">Supplier directory</a></div>
<?php if (!$ready): ?><div class="alert error">Supplier operations tables are not installed yet. <a href="system.php">Open System</a> and run the supplier operations upgrade.</div><?php endif; ?>
<div class="admin-stats sourcing-stats">
  <a class="admin-stat" href="suppliers.php"><span>Supplier prospects</span><strong><?= $metrics['suppliers'] ?></strong></a>
  <div class="admin-stat"><span>Contacted / evaluating</span><strong><?= $metrics['contacted'] ?></strong></div>
  <div class="admin-stat"><span>Qualified</span><strong><?= $metrics['qualified'] ?></strong></div>
  <div class="admin-stat"><span>Follow-ups due</span><strong><?= $metrics['followups'] ?></strong></div>
  <div class="admin-stat"><span>Open test orders</span><strong><?= $metrics['tests'] ?></strong></div>
</div>

<?php if ($ready): ?>
<section class="card admin-section-gap">
  <div class="section-heading"><div><h2>Supplier qualification pipeline</h2><p class="muted">Readiness is a documentation-completeness indicator based on the 11 controls in each supplier record. It is not a substitute for independent verification.</p></div></div>
  <div class="table-wrap"><table class="data-table sourcing-table"><thead><tr><th>Supplier</th><th>Region</th><th>Status</th><th>Controls</th><th>Offers</th><th>Tests</th><th>Next follow-up</th><th></th></tr></thead><tbody>
  <?php foreach ($suppliers as $s): $readiness = sourcing_readiness($s); ?>
    <tr>
      <td><strong><?= e($s['name']) ?></strong></td><td><?= e($s['region'] ?: '—') ?></td><td><span class="status-pill status-<?= e(strtolower($s['status'])) ?>"><?= e($s['status']) ?></span></td>
      <td><div class="readiness"><div class="readiness-track"><span style="width:<?= $readiness['percent'] ?>%"></span></div><small><?= $readiness['complete'] ?>/<?= $readiness['total'] ?> · <?= e(sourcing_readiness_label($readiness['percent'])) ?></small></div></td>
      <td><?= (int)$s['offer_count'] ?></td><td><?= (int)$s['test_count'] ?></td><td><?= e($s['next_follow_up'] ?: '—') ?></td><td><a href="supplier-ops.php?id=<?= (int)$s['id'] ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$suppliers): ?><tr><td colspan="8">No suppliers yet. Add or seed prospects first.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<section class="card admin-section-gap">
  <div class="section-heading"><div><h2>Commercial offer matrix</h2><p class="muted">Internal comparison of current supplier terms by product. Prices and availability are not exposed on the public site.</p></div></div>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>Product</th><th>Supplier</th><th>Availability</th><th>Wholesale</th><th>MOQ</th><th>Lead time</th><th>COA</th><th>Private label</th><th>Dropship</th></tr></thead><tbody>
  <?php foreach ($offers as $o): ?>
    <tr><td><?= e($o['sku']) ?> · <?= e($o['product_name']) ?></td><td><a href="supplier-ops.php?id=<?= (int)$o['supplier_id'] ?>"><?= e($o['supplier_name']) ?></a></td><td><?= e($o['availability']) ?></td><td><?= sourcing_money($o['wholesale_price'], $o['currency']) ?></td><td><?= $o['moq_units'] !== null ? (int)$o['moq_units'].' units' : ($o['moq_value'] !== null ? sourcing_money($o['moq_value'], $o['currency']) : '—') ?></td><td><?= $o['lead_time_days'] !== null ? (int)$o['lead_time_days'].' days' : '—' ?></td><td><?= $o['coa_available'] ? 'Yes' : 'No' ?></td><td><?= $o['private_label'] ? 'Yes' : 'No' ?></td><td><?= $o['dropship'] ? 'Yes' : 'No' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$offers): ?><tr><td colspan="9">No supplier-product offers recorded yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
