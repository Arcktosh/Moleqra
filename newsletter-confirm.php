<?php
$pageTitle='Confirm newsletter | Moleqra';$pageRobots='noindex,nofollow';
require_once __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/newsletter.php';
$pdo=db();$ok=$pdo&&newsletter_schema_ready($pdo)&&newsletter_confirm($pdo,(string)($_GET['token']??''));
require __DIR__.'/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Newsletter</div><h1><?= $ok?'Subscription confirmed':'Confirmation unavailable' ?></h1><p><?= $ok?'You are now subscribed to Moleqra updates.':'This confirmation link is invalid, expired, or has already been used.' ?></p></div></section>
<section class="section"><div class="container narrow"><a class="btn primary" href="index.php">Return to Moleqra</a></div></section>
<?php require __DIR__.'/includes/footer.php';?>
