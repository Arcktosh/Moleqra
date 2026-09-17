<?php
$pageTitle = 'COA Library | Moleqra';
$pageDescription = 'Moleqra batch documentation and certificate of analysis library.';
require_once __DIR__ . '/includes/bootstrap.php';
$rows=[];$pdo=db();
if($pdo){try{$rows=$pdo->query('SELECT c.id,c.batch_number,c.lab_name,c.purity_label,c.tested_at,c.sha256,p.name product_name,p.sku FROM coa_documents c JOIN products p ON p.id=c.product_id WHERE c.is_public=1 AND p.is_public=1 ORDER BY c.created_at DESC')->fetchAll();}catch(Throwable $e){$rows=[];}}
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Batch documentation</div><h1>COA library.</h1><p>Published records are batch-specific. Where a PDF is available, the displayed SHA-256 digest can be used to verify the downloaded file.</p></div></section>
<section class="section"><div class="container"><div class="card"><div class="table-wrap"><table class="data-table" aria-label="COA library"><thead><tr><th>Batch</th><th>Material</th><th>Laboratory</th><th>Result</th><th>Document</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=e($r['batch_number'])?></td><td><?=e($r['product_name'])?><br><span class="small muted"><?=e($r['sku'])?></span></td><td><?=e($r['lab_name']?:'—')?></td><td><?=e($r['purity_label']?:'—')?></td><td><a href="coa-download.php?id=<?=(int)$r['id']?>">View PDF</a><br><code class="small" title="SHA-256 <?=e($r['sha256'])?>"><?=e(substr($r['sha256'],0,16))?>…</code></td></tr><?php endforeach;?>
<?php if(!$rows):?><tr><td>—</td><td>—</td><td>—</td><td>—</td><td>Awaiting approved launch batch</td></tr><?php endif;?></tbody></table></div></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
