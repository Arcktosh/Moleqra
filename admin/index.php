<?php
$adminTitle = 'Dashboard';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/sourcing.php';
require_once __DIR__ . '/../includes/procurement.php';

$pdo = db();
$counts = ['products'=>0,'suppliers'=>0,'coa'=>0,'enquiries'=>0,'followups'=>0,'tests'=>0,'scenarios'=>0,'launch_holds'=>0];
$opsReady = sourcing_schema_ready($pdo);
$procurementReady = procurement_schema_ready($pdo);
if ($pdo) {
    $counts['products'] = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $counts['suppliers'] = (int)$pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
    $counts['coa'] = (int)$pdo->query('SELECT COUNT(*) FROM coa_documents')->fetchColumn();
    $counts['enquiries'] = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")->fetchColumn();
    if ($opsReady) {
        $counts['followups'] = (int)$pdo->query("SELECT COUNT(*) FROM supplier_outreach WHERE next_follow_up IS NOT NULL AND next_follow_up <= CURDATE() AND outcome NOT IN ('Closed','Rejected')")->fetchColumn();
        $counts['tests'] = (int)$pdo->query("SELECT COUNT(*) FROM supplier_test_orders WHERE status NOT IN ('Completed','Cancelled')")->fetchColumn();
    }
    if ($procurementReady) {
        $counts['scenarios'] = (int)$pdo->query('SELECT COUNT(*) FROM procurement_scenarios')->fetchColumn();
        $counts['launch_holds'] = (int)$pdo->query("SELECT COUNT(*) FROM product_market_reviews WHERE market_code=" . $pdo->quote(procurement_primary_market()) . " AND listing_hold=1")->fetchColumn();
    }
}
?>
<div class="admin-heading"><div><div class="eyebrow">Operations</div><h1>Dashboard</h1></div><span class="tag">No Node runtime required</span></div>
<?php if ($pdo && !$opsReady): ?><div class="alert error">The supplier-operations upgrade is not installed. <a href="system.php">Run the additive upgrade</a> to enable sourcing controls.</div><?php elseif ($pdo && !$procurementReady): ?><div class="alert error">The V4 procurement/launch upgrade is not installed. <a href="system.php">Run the V4 upgrade</a> to enable landed-cost and publication controls.</div><?php endif; ?>
<div class="admin-stats sourcing-stats">
  <a class="admin-stat" href="products.php"><span>Products</span><strong><?= $counts['products'] ?></strong></a>
  <a class="admin-stat" href="suppliers.php"><span>Suppliers</span><strong><?= $counts['suppliers'] ?></strong></a>
  <a class="admin-stat" href="coa.php"><span>COA records</span><strong><?= $counts['coa'] ?></strong></a>
  <a class="admin-stat" href="enquiries.php"><span>New enquiries</span><strong><?= $counts['enquiries'] ?></strong></a>
  <a class="admin-stat" href="sourcing.php"><span>Follow-ups due</span><strong><?= $counts['followups'] ?></strong></a>
  <a class="admin-stat" href="sourcing.php"><span>Open test orders</span><strong><?= $counts['tests'] ?></strong></a>
  <a class="admin-stat" href="procurement.php"><span>Cost scenarios</span><strong><?= $counts['scenarios'] ?></strong></a>
  <a class="admin-stat" href="launch.php"><span>Listing holds</span><strong><?= $counts['launch_holds'] ?></strong></a>
</div>
<div class="grid-2">
<section class="card"><h2>Launch workflow</h2><ol class="admin-list"><li>Add or seed supplier prospects.</li><li>Log outreach and record commercial terms against products.</li><li>Complete qualification controls only when evidence has actually been received or checked.</li><li>Use test orders to verify packaging, documentation and batch-to-COA consistency.</li><li>Model quote scenarios and landed costs before making a sourcing decision.</li><li>Complete the market review and keep listing holds active until the intended public scope has been reviewed.</li><li>Publish products only after the launch gate reports no configured blockers.</li></ol></section>
<section class="card"><h2>Deployment state</h2><p class="muted">The admin area is request-driven PHP. There is no background process, queue worker or service to restart.</p><p><strong>Database:</strong> <?= db_ready() ? 'Connected' : 'Not configured' ?><br><strong>Supplier operations:</strong> <?= $opsReady ? 'Ready' : 'Upgrade required' ?><br><strong>Procurement/launch:</strong> <?= $procurementReady ? 'Ready' : 'Upgrade required' ?></p><?php if (!$opsReady && $pdo): ?><a class="btn" href="system.php">Open system upgrade</a><?php endif; ?></section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
