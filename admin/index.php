<?php
$adminTitle = 'Dashboard';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/sourcing.php';
require_once __DIR__ . '/../includes/procurement.php';
require_once __DIR__ . '/../includes/inventory.php';
require_once __DIR__ . '/../includes/commerce.php';
require_once __DIR__ . '/../includes/commercial_ops.php';
require_once __DIR__ . '/../includes/storefront.php';

$pdo = db();
$counts = ['products'=>0,'suppliers'=>0,'coa'=>0,'enquiries'=>0,'followups'=>0,'tests'=>0,'scenarios'=>0,'launch_holds'=>0,'open_pos'=>0,'quarantine_batches'=>0,'orders_pending'=>0,'orders_paid'=>0,'unreconciled'=>0,'refund_requests'=>0,'mail_failures'=>0];
$opsReady = sourcing_schema_ready($pdo);
$procurementReady = procurement_schema_ready($pdo);
$inventoryReady = inventory_schema_ready($pdo);
$commerceReady = commerce_schema_ready($pdo);
$commercialOpsReady = commercial_ops_schema_ready($pdo);
$storefrontReady = storefront_schema_ready($pdo);
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
    if ($inventoryReady) {
        $counts['open_pos'] = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status NOT IN ('Received','Cancelled')")->fetchColumn();
        $counts['quarantine_batches'] = (int)$pdo->query("SELECT COUNT(*) FROM inventory_batches WHERE status='Quarantine' AND units_on_hand>0")->fetchColumn();
    }
    if ($commerceReady) {
        $counts['orders_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM sales_orders WHERE payment_status='Pending'")->fetchColumn();
        $counts['orders_paid'] = (int)$pdo->query("SELECT COUNT(*) FROM sales_orders WHERE payment_status='Paid' AND fulfilment_status<>'Fulfilled'")->fetchColumn();
    }
    if ($commercialOpsReady) {
        $counts['unreconciled'] = (int)$pdo->query("SELECT COUNT(*) FROM payment_transactions p LEFT JOIN payment_reconciliations r ON r.payment_transaction_id=p.id WHERE r.id IS NULL")->fetchColumn();
        $counts['refund_requests'] = (int)$pdo->query("SELECT COUNT(*) FROM order_refunds WHERE status='Requested'")->fetchColumn();
        $counts['mail_failures'] = (int)$pdo->query("SELECT COUNT(*) FROM commerce_notifications WHERE status='Failed'")->fetchColumn();
    }
}
?>
<div class="admin-heading"><div><div class="eyebrow">Operations</div><h1>Dashboard</h1></div><span class="tag">No Node runtime required</span></div>
<?php if ($pdo && !$opsReady): ?><div class="alert error">The supplier-operations upgrade is not installed. <a href="system.php">Run the additive upgrade</a> to enable sourcing controls.</div><?php elseif ($pdo && !$procurementReady): ?><div class="alert error">The V4 procurement/launch upgrade is not installed. <a href="system.php">Run the V4 upgrade</a> to enable landed-cost and publication controls.</div><?php elseif ($pdo && !$inventoryReady): ?><div class="alert error">The V5 inventory upgrade is not installed. <a href="system.php">Run the V5 upgrade</a> to enable purchase orders and batch stock control.</div><?php elseif ($pdo && !$commerceReady): ?><div class="alert error">The V6 commerce upgrade is not installed. <a href="system.php">Run the V6 upgrade</a> to enable cart, customer accounts and payments.</div><?php elseif ($pdo && !$commercialOpsReady): ?><div class="alert error">The V7 commercial-operations upgrade is not installed. <a href="system.php">Run the V7 upgrade</a> to enable invoices, refunds, notifications and shipping rules.</div><?php elseif ($pdo && !$storefrontReady): ?><div class="alert error">The V8 storefront-hardening upgrade is not installed. <a href="system.php">Run the V8 upgrade</a> for pack variants, account recovery and audit controls.</div><?php endif; ?>
<div class="admin-stats sourcing-stats">
  <a class="admin-stat" href="products.php"><span>Products</span><strong><?= $counts['products'] ?></strong></a>
  <a class="admin-stat" href="suppliers.php"><span>Suppliers</span><strong><?= $counts['suppliers'] ?></strong></a>
  <a class="admin-stat" href="coa.php"><span>COA records</span><strong><?= $counts['coa'] ?></strong></a>
  <a class="admin-stat" href="enquiries.php"><span>New enquiries</span><strong><?= $counts['enquiries'] ?></strong></a>
  <a class="admin-stat" href="sourcing.php"><span>Follow-ups due</span><strong><?= $counts['followups'] ?></strong></a>
  <a class="admin-stat" href="sourcing.php"><span>Open test orders</span><strong><?= $counts['tests'] ?></strong></a>
  <a class="admin-stat" href="procurement.php"><span>Cost scenarios</span><strong><?= $counts['scenarios'] ?></strong></a>
  <a class="admin-stat" href="launch.php"><span>Listing holds</span><strong><?= $counts['launch_holds'] ?></strong></a>
  <a class="admin-stat" href="purchase-orders.php"><span>Open POs</span><strong><?= $counts['open_pos'] ?></strong></a>
  <a class="admin-stat" href="inventory.php"><span>Quarantine lots</span><strong><?= $counts['quarantine_batches'] ?></strong></a>
  <a class="admin-stat" href="orders.php"><span>Pending payments</span><strong><?= $counts['orders_pending'] ?></strong></a>
  <a class="admin-stat" href="orders.php"><span>Paid to fulfil</span><strong><?= $counts['orders_paid'] ?></strong></a>
  <a class="admin-stat" href="payments.php"><span>Unreconciled</span><strong><?= $counts['unreconciled'] ?></strong></a>
  <a class="admin-stat" href="refunds.php"><span>Refund requests</span><strong><?= $counts['refund_requests'] ?></strong></a>
  <a class="admin-stat" href="system.php"><span>Email failures</span><strong><?= $counts['mail_failures'] ?></strong></a>
</div>
<div class="grid-2">
<section class="card"><h2>Launch workflow</h2><ol class="admin-list"><li>Add or seed supplier prospects.</li><li>Log outreach and record commercial terms against products.</li><li>Complete qualification controls only when evidence has actually been received or checked.</li><li>Use test orders to verify packaging, documentation and batch-to-COA consistency.</li><li>Model quote scenarios and landed costs before making a sourcing decision.</li><li>Complete the market review and keep listing holds active until the intended public scope has been reviewed.</li><li>Publish products only after the launch gate reports no configured blockers.</li><li>Raise purchase orders, receive each supplier lot into quarantine, link its COA, complete receipt checks and use the inventory release gate before internal disposition as Released.</li></ol></section>
<section class="card"><h2>Deployment state</h2><p class="muted">The admin area is request-driven PHP. There is no background process, queue worker or service to restart.</p><p><strong>Database:</strong> <?= db_ready() ? 'Connected' : 'Not configured' ?><br><strong>Supplier operations:</strong> <?= $opsReady ? 'Ready' : 'Upgrade required' ?><br><strong>Procurement/launch:</strong> <?= $procurementReady ? 'Ready' : 'Upgrade required' ?><br><strong>Inventory:</strong> <?= $inventoryReady ? 'Ready' : 'Upgrade required' ?><br><strong>Commerce:</strong> <?= $commerceReady ? 'Ready' : 'Upgrade required' ?><br><strong>Commercial ops:</strong> <?= $commercialOpsReady ? 'Ready' : 'Upgrade required' ?><br><strong>Storefront hardening:</strong> <?= $storefrontReady ? 'Ready' : 'Upgrade required' ?></p><?php if (!$opsReady && $pdo): ?><a class="btn" href="system.php">Open system upgrade</a><?php endif; ?></section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
