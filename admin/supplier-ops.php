<?php
$adminTitle='Supplier Operations';
require __DIR__.'/_header.php';
require_once __DIR__.'/../includes/sourcing.php';
require_once __DIR__.'/../includes/supplier_ops_actions.php';
require_once __DIR__.'/../includes/supplier_ops_data.php';
$pdo=db(); $ready=sourcing_schema_ready($pdo); $id=(int)($_GET['id'] ?? $_POST['supplier_id'] ?? 0); $error=''; $message=''; $supplier=null;
if(!$pdo || !$ready) $error='Supplier operations are not ready. Run the database upgrade from System first.';
elseif($id<1) $error='Choose a supplier from the sourcing screen.';
else { $stmt=$pdo->prepare('SELECT * FROM suppliers WHERE id=:id'); $stmt->execute(['id'=>$id]); $supplier=$stmt->fetch() ?: null; if(!$supplier)$error='Supplier not found.'; }
if($supplier){ $result=supplier_ops_handle_post($pdo,$supplier,$id); $error=$result['error']; $message=$result['message']; $data=supplier_ops_load($pdo,$id); extract($data); $readiness=sourcing_readiness($qualification); }
?>
<div class="admin-heading"><div><div class="eyebrow">Supplier record</div><h1><?= e($supplier['name'] ?? 'Supplier operations') ?></h1><?php if($supplier):?><p class="muted"><?= e($supplier['region'] ?: 'Region not set') ?> · <?= e($supplier['status']) ?></p><?php endif;?></div><a class="btn" href="sourcing.php">Back to sourcing</a></div>
<?php if($message):?><div class="alert success"><?= e($message) ?></div><?php endif;?><?php if($error):?><div class="alert error"><?= e($error) ?></div><?php endif;?>
<?php if($supplier && $ready): ?>
<div class="supplier-summary-grid"><section class="card supplier-readiness-card"><div><span class="muted">Qualification controls</span><strong><?= $readiness['percent'] ?>%</strong><small><?= $readiness['complete'] ?> of <?= $readiness['total'] ?> complete</small></div><div class="readiness large"><div class="readiness-track"><span style="width:<?= $readiness['percent'] ?>%"></span></div><small><?= e(sourcing_readiness_label($readiness['percent'])) ?></small></div></section><section class="card"><span class="muted">Contact</span><p><strong><?= e($supplier['contact_name'] ?: 'Not set') ?></strong><br><?= e($supplier['email'] ?: 'No email') ?></p><?php if($supplier['website']):?><a href="<?= e($supplier['website']) ?>" target="_blank" rel="noopener">Open supplier website</a><?php endif;?></section><section class="card"><span class="muted">Commercial coverage</span><p><strong><?= count($offers) ?></strong> product offers<br><strong><?= count($tests) ?></strong> test orders</p><a href="suppliers.php?edit=<?= $id ?>">Edit supplier details</a></section></div>
<?php require __DIR__.'/partials/supplier-qualification.php'; require __DIR__.'/partials/supplier-outreach.php'; require __DIR__.'/partials/supplier-offers.php'; require __DIR__.'/partials/supplier-tests.php'; ?>
<?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
