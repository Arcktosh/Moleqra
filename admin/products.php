<?php
$adminTitle = 'Products';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/procurement.php';
$pdo = db();
$error = '';
$editing = null;
$held = false;
if (!$pdo) $error = 'Database not configured.';

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Please try again.';
    else {
        $id = (int)($_POST['id'] ?? 0);
        $sku = strtoupper(trim((string)($_POST['sku'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $format = trim((string)($_POST['format'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Supplier qualification'));
        $purity = trim((string)($_POST['purity_label'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $featured = isset($_POST['featured']) ? 1 : 0;
        $requestedPublic = isset($_POST['is_public']);
        $public = $requestedPublic ? 1 : 0;
        if ($requestedPublic && $pdo && procurement_schema_ready($pdo)) {
            if ($id < 1) {
                $public = 0; $held = true;
            } else {
                $gate = product_publication_gate($pdo, $id);
                if (!$gate['allowed']) { $public = 0; $held = true; }
            }
        }
        $sort = (int)($_POST['sort_order'] ?? 0);
        if (!preg_match('/^[A-Z0-9._-]{2,50}$/', $sku)) $error = 'SKU must use 2–50 letters, numbers, dots, dashes or underscores.';
        elseif (text_length($name) < 2 || text_length($name) > 120) $error = 'Enter a valid product name.';
        elseif (text_length($format) < 2 || text_length($format) > 180) $error = 'Enter a valid format.';
        else {
            try {
                if ($id > 0) {
                    $sql = 'UPDATE products SET sku=:sku,name=:name,category=:category,format=:format,status=:status,purity_label=:purity,description=:description,featured=:featured,is_public=:public,sort_order=:sort WHERE id=:id';
                    $params = compact('sku','name','category','format','status','description','featured','sort');
                    $params += ['purity'=>$purity ?: null,'public'=>$public,'id'=>$id];
                } else {
                    $sql = 'INSERT INTO products (sku,name,category,format,status,purity_label,description,featured,is_public,sort_order) VALUES (:sku,:name,:category,:format,:status,:purity,:description,:featured,:public,:sort)';
                    $params = compact('sku','name','category','format','status','description','featured','sort');
                    $params += ['purity'=>$purity ?: null,'public'=>$public];
                }
                $pdo->prepare($sql)->execute($params);
                $savedId=$id>0?$id:(int)$pdo->lastInsertId();admin_audit($pdo,$id>0?'product.update':'product.create','product',(string)$savedId,'Saved product master record',['sku'=>$sku,'public'=>$public]);
                header('Location: products.php?saved=1' . ($held ? '&held=1' : '')); exit;
            } catch (Throwable $e) { $error = 'Could not save product. Check that the SKU is unique.'; }
        }
    }
}
if ($pdo && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id=:id'); $stmt->execute(['id'=>(int)$_GET['edit']]); $editing = $stmt->fetch() ?: null;
}
$products = $pdo ? $pdo->query('SELECT * FROM products ORDER BY sort_order, name')->fetchAll() : [];
?>
<div class="admin-heading"><div><div class="eyebrow">Catalogue</div><h1>Products</h1></div></div>
<?php if (isset($_GET['saved'])): ?><div class="alert success">Product saved.</div><?php endif; ?><?php if (isset($_GET['held'])): ?><div class="alert error">Public listing was held by the V4 publication gate. <a href="launch.php">Open Launch controls</a> to review the blockers.</div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="admin-two-col"><section class="card"><h2><?= $editing ? 'Edit product' : 'Add product' ?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>"><div class="form-grid"><div class="field"><label>SKU</label><input name="sku" required maxlength="50" value="<?= e($editing['sku'] ?? '') ?>"></div><div class="field"><label>Name</label><input name="name" required maxlength="120" value="<?= e($editing['name'] ?? '') ?>"></div><div class="field"><label>Category</label><input name="category" maxlength="80" value="<?= e($editing['category'] ?? '') ?>"></div><div class="field"><label>Format</label><input name="format" required maxlength="180" value="<?= e($editing['format'] ?? 'Lyophilised research material') ?>"></div><div class="field"><label>Status</label><input name="status" maxlength="60" value="<?= e($editing['status'] ?? 'Supplier qualification') ?>"></div><div class="field"><label>Purity label</label><input name="purity_label" maxlength="40" placeholder="e.g. 99.2%" value="<?= e($editing['purity_label'] ?? '') ?>"></div><div class="field"><label>Sort order</label><input name="sort_order" type="number" value="<?= (int)($editing['sort_order'] ?? 0) ?>"></div><div class="field admin-checks"><label><input type="checkbox" name="featured" <?= !empty($editing['featured']) ? 'checked' : '' ?>> Featured</label><label><input type="checkbox" name="is_public" <?= !empty($editing['is_public']) ? 'checked' : '' ?>> Public</label></div><div class="field full"><label>Description</label><textarea name="description" maxlength="5000"><?= e($editing['description'] ?? '') ?></textarea></div><div class="field full"><button class="btn primary" type="submit">Save product</button></div></div></form></section>
<section class="card"><h2>Current catalogue</h2><div class="table-wrap"><table class="data-table"><thead><tr><th>SKU</th><th>Name</th><th>State</th><th></th></tr></thead><tbody><?php foreach ($products as $p): ?><tr><td><?= e($p['sku']) ?></td><td><?= e($p['name']) ?></td><td><?= $p['is_public'] ? 'Public' : 'Draft' ?></td><td><a href="products.php?edit=<?= (int)$p['id'] ?>">Edit</a></td></tr><?php endforeach; ?><?php if (!$products): ?><tr><td colspan="4">No products yet.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php require __DIR__ . '/_footer.php'; ?>
