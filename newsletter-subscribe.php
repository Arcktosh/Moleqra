<?php
$pageTitle='Newsletter subscription | Moleqra';$pageRobots='noindex,nofollow';
require_once __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/newsletter.php';require_once __DIR__.'/includes/account_auth.php';
$pdo=db();$message='';$error='';$email=trim((string)($_POST['email']??''));
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null))$error='Your session expired. Please try again.';
    elseif(!$pdo||!newsletter_schema_ready($pdo))$error='Newsletter subscriptions are not available yet.';
    elseif(!empty($_POST['website'])){$message='Please check your inbox to continue.';}
    else{
        try{$user=customer_user();newsletter_subscribe($pdo,$email,$user?(int)$user['id']:null,'Website footer');$message=newsletter_mail_ready()?'Please check your inbox and confirm your subscription.':'Your subscription request has been saved. Confirmation will become available when email delivery is enabled.';}
        catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'The subscription could not be saved.';}
    }
}
require __DIR__.'/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Updates</div><h1>Newsletter subscription</h1><p>Product, documentation, platform and research-community updates from Moleqra.</p></div></section>
<section class="section"><div class="container narrow"><?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><div class="card"><p>Subscriptions use confirmation before activation and every newsletter includes an unsubscribe link.</p><a class="btn" href="index.php">Back to site</a></div></div></section>
<?php require __DIR__.'/includes/footer.php';?>
