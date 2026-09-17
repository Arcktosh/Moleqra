<?php
$adminTitle = 'Launch controls';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/procurement.php';

$pdo = db();
$ready = procurement_schema_ready($pdo);
$error=''; $message='';
$productId = (int)($_GET['product'] ?? $_POST['product_id'] ?? 0);
$market = procurement_primary_market();

if ($pdo && $ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) $error='Your session expired. Please try again.';
    else {
        try {
            $action=(string)($_POST['action'] ?? '');
            $user=admin_user();
            if ($productId < 1) throw new RuntimeException('Choose a product.');
            if ($action === 'market_review') {
                $status=(string)($_POST['review_status'] ?? 'Review required');
                if (!in_array($status, procurement_market_review_statuses(), true)) $status='Review required';
                $hold=isset($_POST['listing_hold'])?1:0;
                $ref=trim((string)($_POST['review_reference'] ?? '')) ?: null;
                $notes=trim((string)($_POST['evidence_notes'] ?? '')) ?: null;
                $date=trim((string)($_POST['reviewed_at'] ?? '')) ?: null;
                $sql='INSERT INTO product_market_reviews (product_id,market_code,review_status,listing_hold,review_reference,evidence_notes,reviewed_by,reviewed_at) VALUES (:product,:market,:status,:hold,:ref,:notes,:user,:reviewed) ON DUPLICATE KEY UPDATE review_status=VALUES(review_status),listing_hold=VALUES(listing_hold),review_reference=VALUES(review_reference),evidence_notes=VALUES(evidence_notes),reviewed_by=VALUES(reviewed_by),reviewed_at=VALUES(reviewed_at)';
                $pdo->prepare($sql)->execute(['product'=>$productId,'market'=>$market,'status'=>$status,'hold'=>$hold,'ref'=>$ref,'notes'=>$notes,'user'=>$user['id']??null,'reviewed'=>$date]);
                $message='Market review updated.';
            } elseif ($action === 'decision') {
                $status=(string)($_POST['decision_status'] ?? 'Evaluating');
                if (!in_array($status, procurement_decision_statuses(), true)) $status='Evaluating';
                $offer=trim((string)($_POST['preferred_supplier_product_id'] ?? ''));
                $offerId=$offer===''?null:(int)$offer;
                $notes=trim((string)($_POST['decision_notes'] ?? '')) ?: null;
                $date=trim((string)($_POST['decided_at'] ?? '')) ?: null;
                $sql='INSERT INTO procurement_decisions (product_id,market_code,preferred_supplier_product_id,decision_status,decision_notes,decided_by,decided_at) VALUES (:product,:market,:offer,:status,:notes,:user,:decided) ON DUPLICATE KEY UPDATE preferred_supplier_product_id=VALUES(preferred_supplier_product_id),decision_status=VALUES(decision_status),decision_notes=VALUES(decision_notes),decided_by=VALUES(decided_by),decided_at=VALUES(decided_at)';
                $pdo->prepare($sql)->execute(['product'=>$productId,'market'=>$market,'offer'=>$offerId,'status'=>$status,'notes'=>$notes,'user'=>$user['id']??null,'decided'=>$date]);
                $message='Sourcing decision updated.';
            }
        } catch(Throwable $e) {
            error_log('Moleqra launch control failed: '.$e->getMessage());
            $error=$e instanceof RuntimeException?$e->getMessage():'The launch control could not be saved.';
        }
    }
}

$products=[]; $selected=null; $review=null; $decision=null; $offers=[]; $gate=null;
if ($pdo) $products=$pdo->query('SELECT id,sku,name,is_public FROM products ORDER BY name')->fetchAll();
if ($pdo && $ready && $productId>0) {
    $stmt=$pdo->prepare('SELECT * FROM products WHERE id=:id'); $stmt->execute(['id'=>$productId]); $selected=$stmt->fetch()?:null;
    if($selected){
        $stmt=$pdo->prepare('SELECT * FROM product_market_reviews WHERE product_id=:product AND market_code=:market'); $stmt->execute(['product'=>$productId,'market'=>$market]); $review=$stmt->fetch()?:null;
        $stmt=$pdo->prepare('SELECT * FROM procurement_decisions WHERE product_id=:product AND market_code=:market'); $stmt->execute(['product'=>$productId,'market'=>$market]); $decision=$stmt->fetch()?:null;
        $stmt=$pdo->prepare("SELECT sp.id,s.name AS supplier_name,s.status AS supplier_status,sp.coa_available,sp.wholesale_price,sp.currency,sp.lead_time_days FROM supplier_products sp JOIN suppliers s ON s.id=sp.supplier_id WHERE sp.product_id=:product ORDER BY s.name"); $stmt->execute(['product'=>$productId]); $offers=$stmt->fetchAll();
        $gate=product_publication_gate($pdo,$productId,$market);
    }
}
?>
<div class="admin-heading"><div><div class="eyebrow">Publication governance</div><h1>Launch controls</h1><p class="muted">Primary market: <?= e($market) ?>. A pass means the configured internal prerequisites are complete; it is not regulatory approval or legal advice.</p></div><span class="tag"><?= e($market) ?></span></div>
<?php if(!$ready): ?><div class="alert error">The procurement/launch upgrade is not installed. <a href="system.php">Run the V4 upgrade</a>.</div><?php endif; ?>
<?php if($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?><?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if($ready): ?>
<section class="card"><form method="get" class="inline-select"><label for="product">Product</label><select id="product" name="product" onchange="this.form.submit()"><option value="">Select product</option><?php foreach($products as $p): ?><option value="<?= (int)$p['id'] ?>"<?= $productId===(int)$p['id']?' selected':'' ?>><?= e($p['sku'].' — '.$p['name']) ?></option><?php endforeach; ?></select><noscript><button class="btn">Open</button></noscript></form></section>
<?php if($selected && $gate): ?>
<section class="card admin-section-gap launch-gate <?= $gate['allowed']?'gate-pass':'gate-hold' ?>"><div class="section-heading"><div><span class="muted">Public listing gate</span><h2><?= $gate['allowed']?'PASS':'HOLD' ?> — <?= e($selected['sku'].' '.$selected['name']) ?></h2></div><span class="status-pill <?= $gate['allowed']?'status-qualified':'status-rejected' ?>"><?= $gate['allowed']?'Prerequisites complete':'Blocked' ?></span></div>
<?php if($gate['blockers']): ?><ul class="admin-list"><?php foreach($gate['blockers'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul><?php else: ?><p class="muted">All configured internal publication prerequisites are currently satisfied.</p><?php endif; ?>
<p class="muted">Current product flag: <strong><?= !empty($selected['is_public'])?'Public':'Draft' ?></strong>. Product publication is still an explicit admin action on the Products screen.</p></section>
<div class="admin-two-col admin-section-gap">
<section class="card"><h2>Market/compliance review</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="market_review"><div class="field"><label>Review status</label><select name="review_status"><?php foreach(procurement_market_review_statuses() as $s): ?><option<?= ($review['review_status']??'Review required')===$s?' selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div><div class="field admin-checks"><label><input type="checkbox" name="listing_hold" <?= !isset($review['listing_hold'])||!empty($review['listing_hold'])?'checked':'' ?>> Keep public-listing hold active</label></div><div class="field"><label>Review reference</label><input name="review_reference" maxlength="180" value="<?= e($review['review_reference']??'') ?>" placeholder="Internal file, adviser reference, memo ID"></div><div class="field"><label>Reviewed date</label><input type="date" name="reviewed_at" value="<?= e($review['reviewed_at']??'') ?>"></div><div class="field"><label>Evidence / scope notes</label><textarea name="evidence_notes" maxlength="8000"><?= e($review['evidence_notes']??'') ?></textarea></div><button class="btn primary">Save market review</button></form></section>
<section class="card"><h2>Sourcing decision</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= $productId ?>"><input type="hidden" name="action" value="decision"><div class="field"><label>Decision status</label><select name="decision_status"><?php foreach(procurement_decision_statuses() as $s): ?><option<?= ($decision['decision_status']??'Evaluating')===$s?' selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div><div class="field"><label>Selected supplier offer</label><select name="preferred_supplier_product_id"><option value="">No selection</option><?php foreach($offers as $o): ?><option value="<?= (int)$o['id'] ?>"<?= (int)($decision['preferred_supplier_product_id']??0)===(int)$o['id']?' selected':'' ?>><?= e($o['supplier_name'].' — '.$o['supplier_status'].' — '.sourcing_money($o['wholesale_price'],$o['currency'])) ?></option><?php endforeach; ?></select></div><div class="field"><label>Decision date</label><input type="date" name="decided_at" value="<?= e($decision['decided_at']??'') ?>"></div><div class="field"><label>Decision notes</label><textarea name="decision_notes" maxlength="8000"><?= e($decision['decision_notes']??'') ?></textarea></div><button class="btn primary">Save sourcing decision</button></form></section>
</div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
