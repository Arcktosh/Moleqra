<?php
$adminTitle = 'System';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/sourcing.php';
require_once __DIR__ . '/../includes/procurement.php';
require_once __DIR__ . '/../includes/inventory.php';
require_once __DIR__ . '/../includes/commerce.php';
require_once __DIR__ . '/../includes/payments.php';

$pdo = db();
$error = '';
$message = '';

function run_sql_file(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException('SQL file not found.');
    }
    $sql = trim((string) file_get_contents($path));
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') continue;
        $pdo->exec($statement);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (!$pdo) {
        $error = 'Database is not configured.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'upgrade_supplier_ops') {
                run_sql_file($pdo, __DIR__ . '/../database/migrations-002-supplier-operations.sql');
                $message = 'Supplier operations tables are ready.';
            } elseif ($action === 'upgrade_procurement_launch') {
                if (!sourcing_schema_ready($pdo)) {
                    run_sql_file($pdo, __DIR__ . '/../database/migrations-002-supplier-operations.sql');
                }
                run_sql_file($pdo, __DIR__ . '/../database/migrations-003-procurement-launch.sql');
                $message = 'Procurement and launch-control tables are ready.';
            } elseif ($action === 'upgrade_inventory') {
                if (!sourcing_schema_ready($pdo)) {
                    run_sql_file($pdo, __DIR__ . '/../database/migrations-002-supplier-operations.sql');
                }
                if (!procurement_schema_ready($pdo)) {
                    run_sql_file($pdo, __DIR__ . '/../database/migrations-003-procurement-launch.sql');
                }
                run_sql_file($pdo, __DIR__ . '/../database/migrations-004-inventory.sql');
                $message = 'Inventory and batch-control tables are ready.';
            } elseif ($action === 'upgrade_commerce') {
                if (!inventory_schema_ready($pdo)) {
                    if (!sourcing_schema_ready($pdo)) run_sql_file($pdo, __DIR__ . '/../database/migrations-002-supplier-operations.sql');
                    if (!procurement_schema_ready($pdo)) run_sql_file($pdo, __DIR__ . '/../database/migrations-003-procurement-launch.sql');
                    run_sql_file($pdo, __DIR__ . '/../database/migrations-004-inventory.sql');
                }
                run_sql_file($pdo, __DIR__ . '/../database/migrations-005-commerce.sql');
                $message = 'Commerce, customer, order and payment tables are ready.';
            } elseif ($action === 'seed_suppliers') {
                run_sql_file($pdo, __DIR__ . '/../database/seed-supplier-prospects.sql');
                $message = 'Initial supplier prospects were added without overwriting existing records.';
            }
        } catch (Throwable $e) {
            error_log('Moleqra admin system operation failed: ' . $e->getMessage());
            $error = 'The database operation failed. Review the hosting error log for details.';
        }
    }
}

$opsReady = sourcing_schema_ready($pdo);
$procurementReady = procurement_schema_ready($pdo);
$inventoryReady = inventory_schema_ready($pdo);
$commerceReady = commerce_schema_ready($pdo);
$supplierCount = 0;
if ($pdo && db_table_exists('suppliers', $pdo)) {
    $supplierCount = (int)$pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
}
?>
<div class="admin-heading"><div><div class="eyebrow">Maintenance</div><h1>System</h1></div><span class="tag">Shared-hosting safe</span></div>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="grid-2">
<section class="card">
  <h2>Supplier operations upgrade</h2>
  <p class="muted">Adds qualification controls, supplier-product commercial terms, outreach history and test-order tracking. The migration is additive and uses <code>CREATE TABLE IF NOT EXISTS</code>.</p>
  <p><strong>Status:</strong> <?= $opsReady ? 'Ready' : 'Upgrade required' ?></p>
  <?php if (!$opsReady): ?>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upgrade_supplier_ops"><button class="btn primary" type="submit">Run supplier operations upgrade</button></form>
  <?php endif; ?>
</section>
<section class="card">
  <h2>V4 procurement &amp; launch controls</h2>
  <p class="muted">Adds landed-cost scenarios, market review holds, sourcing decisions and an internal publication gate. The migration is additive. Once installed, existing public product flags are filtered from the catalogue until their launch gate passes.</p>
  <p><strong>Status:</strong> <?= $procurementReady ? 'Ready' : 'Upgrade required' ?></p>
  <?php if (!$procurementReady): ?>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upgrade_procurement_launch"><button class="btn primary" type="submit">Run V4 upgrade</button></form>
  <?php endif; ?>
</section>
<section class="card">
  <h2>V5 inventory &amp; batch control</h2>
  <p class="muted">Adds purchase orders, PO lines, received batch inventory, quarantine/release checks and an auditable stock movement ledger. The migration is additive.</p>
  <p><strong>Status:</strong> <?= $inventoryReady ? 'Ready' : 'Upgrade required' ?></p>
  <?php if (!$inventoryReady): ?>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upgrade_inventory"><button class="btn primary" type="submit">Run V5 inventory upgrade</button></form>
  <?php endif; ?>
</section>
<section class="card">
  <h2>V6 commerce &amp; checkout</h2>
  <p class="muted">Adds customer accounts, public product commerce settings, server-side cart/checkout, stock reservations, sales orders, payment audit records and shipment records.</p>
  <p><strong>Status:</strong> <?= $commerceReady ? 'Ready' : 'Upgrade required' ?></p>
  <?php if (!$commerceReady): ?>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upgrade_commerce"><button class="btn primary" type="submit">Run V6 commerce upgrade</button></form>
  <?php endif; ?>
  <p class="muted" style="margin-top:1rem"><strong>PayFast:</strong> <?= payfast_configured() ? (payfast_sandbox() ? 'Configured · sandbox' : 'Configured · live') : 'Credentials not configured' ?></p>
</section>
<section class="card">
  <h2>Initial supplier prospects</h2>
  <p class="muted">Adds the initial South African and US prospects previously identified for outreach. Existing suppliers with the same names are left untouched.</p>
  <p><strong>Current supplier records:</strong> <?= $supplierCount ?></p>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="seed_suppliers"><button class="btn" type="submit" <?= $pdo ? '' : 'disabled' ?>>Add initial prospects</button></form>
</section>
</div>
<section class="card" style="margin-top:1rem"><h2>Runtime model</h2><p class="muted">All admin actions are ordinary PHP requests. No process manager, Node runtime, queue worker or service restart is required after deployment.</p></section>
<?php require __DIR__ . '/_footer.php'; ?>
