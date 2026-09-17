<?php
$pageTitle = 'Research Catalogue | Moleqra';
$pageDescription = 'Preview Moleqra\'s planned research-use-only peptide catalogue and documentation model.';
require __DIR__ . '/includes/header.php';
$products = [
    ['sku' => 'MQ-BPC157', 'name' => 'BPC-157', 'format' => 'Lyophilised research material', 'status' => 'Supplier qualification'],
    ['sku' => 'MQ-TB500', 'name' => 'TB-500', 'format' => 'Lyophilised research material', 'status' => 'Supplier qualification'],
    ['sku' => 'MQ-GHKCU', 'name' => 'GHK-Cu', 'format' => 'Research material', 'status' => 'Supplier qualification'],
];
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Catalogue preview</div><h1>Focused research materials.</h1><p>The launch catalogue below is provisional. Products will only become commercially available after supplier qualification and batch documentation review.</p></div></section>
<section class="section"><div class="container">
    <div class="notice" style="margin-bottom:1.4rem"><strong>No products are currently offered for sale.</strong> This catalogue is a supplier-onboarding preview and does not constitute an offer of medicinal, therapeutic or human-use products.</div>
    <div class="product-grid">
        <?php foreach ($products as $product): ?>
            <article class="product-card">
                <span class="sku"><?= e($product['sku']) ?></span>
                <h2 style="font-size:1.7rem;margin-top:.7rem"><?= e($product['name']) ?></h2>
                <p><?= e($product['format']) ?>. Batch documentation requirements include analytical identity and purity evidence appropriate to the material.</p>
                <div class="product-footer"><span class="tag"><?= e($product['status']) ?></span><a href="quality.php">Quality →</a></div>
            </article>
        <?php endforeach; ?>
    </div>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
