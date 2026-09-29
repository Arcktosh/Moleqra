<?php
$pageTitle='Unsubscribe | Moleqra';$pageRobots='noindex,nofollow';
require_once __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/newsletter.php';
$pdo=db();$ok=$pdo&&newsletter_schema_ready($pdo)&&newsletter_unsubscribe($pdo,(string)($_GET['token']??''));
require __DIR__.'/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Newsletter</div><h1><?= $ok?'You have been unsubscribed':'Unsubscribe link unavailable' ?></h1><p><?= $ok?'You will no longer receive Moleqra newsletter campaigns.':'This unsubscribe link is invalid or no longer active.' ?></p></div></section>
<section class="section"><div class="container narrow"><a class="btn" href="index.php">Return to site</a></div></section>
<?php require __DIR__.'/includes/footer.php';?>
