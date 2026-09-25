<?php
$pageTitle = 'Research Catalogue | Moleqra';
$pageDescription = 'Moleqra research catalogue and batch documentation status.';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/procurement.php';
require_once __DIR__ . '/includes/commerce.php';
$products = [];
$pdo = db();
$commerceReady = $pdo && commerce_schema_ready($pdo);
if ($pdo) {
    try {
        if ($commerceReady && commerce_enabled()) {
            $products = commerce_public_products($pdo);
        } else {
            $products = $pdo->query('SELECT id,sku,name,category,format,status,purity_label,description FROM products WHERE is_public=1 ORDER BY sort_order,name')->fetchAll();
            if (procurement_schema_ready($pdo)) $products = array_values(array_filter($products, fn($product) => product_publication_gate($pdo, (int)$product['id'])['allowed']));
        }
    } catch (Throwable $e) { $products = []; }
}
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Research catalogue</div><h1>Focused research materials.</h1><p>Public records appear only after internal supplier and documentation review. Catalogue publication is not a representation of suitability for human or veterinary use.</p></div></section>
<section class="section"><div class="container">
    <div class="notice" style="margin-bottom:1.4rem"><strong>Research use only.</strong> Moleqra does not provide dosing, treatment, self-administration, or therapeutic guidance.</div>
    <?php if ($products): ?><div class="product-grid">
        <?php foreach ($products as $product): ?><article class="product-card"><span class="sku"><?= e($product['sku']) ?></span><h2 style="font-size:1.7rem;margin-top:.7rem"><a href="product.php?id=<?= (int)$product['id'] ?>"><?= e($product['name']) ?></a></h2><?php if ($product['category']): ?><div class="kicker"><?= e($product['category']) ?></div><?php endif; ?><p><?= e($product['description'] ?: $product['format']) ?></p><?php if($commerceReady && isset($product['price'])):?><div class="catalog-commerce"><strong><?= commerce_money((float)$product['price']) ?></strong><span class="small muted"><?= (float)$product['available_qty']>0?e((string)$product['available_qty']).' available':'Out of stock' ?></span></div><?php endif; ?><div class="product-footer"><span class="tag"><?= e($product['status']) ?></span><a href="product.php?id=<?= (int)$product['id'] ?>">View →</a></div></article><?php endforeach; ?>
    </div><?php else: ?><div class="card"><h2 style="font-size:1.6rem">Catalogue onboarding</h2><p>No product records are currently published for online ordering. Moleqra is qualifying suppliers, reviewing batch documentation and preparing released inventory.</p></div><?php endif; ?>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
