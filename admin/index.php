<?php
$adminTitle = 'Dashboard';
require __DIR__ . '/_header.php';
$pdo = db();
$counts = ['products'=>0,'suppliers'=>0,'coa'=>0,'enquiries'=>0];
if ($pdo) {
    $counts['products'] = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $counts['suppliers'] = (int)$pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
    $counts['coa'] = (int)$pdo->query('SELECT COUNT(*) FROM coa_documents')->fetchColumn();
    $counts['enquiries'] = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")->fetchColumn();
}
?>
<div class="admin-heading"><div><div class="eyebrow">Operations</div><h1>Dashboard</h1></div><span class="tag">No Node runtime required</span></div>
<div class="admin-stats"><a class="admin-stat" href="products.php"><span>Products</span><strong><?= $counts['products'] ?></strong></a><a class="admin-stat" href="suppliers.php"><span>Suppliers</span><strong><?= $counts['suppliers'] ?></strong></a><a class="admin-stat" href="coa.php"><span>COA records</span><strong><?= $counts['coa'] ?></strong></a><a class="admin-stat" href="enquiries.php"><span>New enquiries</span><strong><?= $counts['enquiries'] ?></strong></a></div>
<div class="grid-2"><section class="card"><h2>Launch workflow</h2><ol class="admin-list"><li>Add supplier prospects and qualification notes.</li><li>Create product records but keep them unpublished until documentation is accepted.</li><li>Upload batch-specific COAs and verify the recorded SHA-256 digest.</li><li>Publish only records ready for the public catalogue.</li></ol></section><section class="card"><h2>Deployment state</h2><p class="muted">The admin area is request-driven PHP. There is no background process, queue worker or service to restart.</p><p><strong>Database:</strong> <?= db_ready() ? 'Connected' : 'Not configured' ?></p></section></div>
<?php require __DIR__ . '/_footer.php'; ?>
