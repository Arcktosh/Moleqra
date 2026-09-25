<?php
$rootPrefix='../';$pageTitle='Reset password | Moleqra';$pageRobots='noindex,nofollow';require_once __DIR__.'/../includes/bootstrap.php';require_once __DIR__.'/../includes/customer_security.php';$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null))$error='Your session expired.';else{
        $last=(int)($_SESSION['password_reset_requested_at']??0);if(time()-$last>=30){$_SESSION['password_reset_requested_at']=time();$pdo=db();if($pdo){try{customer_request_password_reset($pdo,(string)($_POST['email']??''));}catch(Throwable $e){error_log('Moleqra password reset request failed: '.$e->getMessage());}}}
        $message='If that email address is registered, a password reset link has been sent.';
    }
}
require __DIR__.'/../includes/header.php';?>
<section class="page-hero"><div class="container"><div class="eyebrow">Account recovery</div><h1>Reset your password</h1></div></section><section class="section"><div class="container narrow"><?php if($message):?><div class="alert success"><?= e($message) ?></div><?php endif;?><?php if($error):?><div class="alert error"><?= e($error) ?></div><?php endif;?><form method="post" class="card"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="field"><label>Email</label><input type="email" name="email" required autocomplete="email"></div><button class="btn primary">Send reset link</button></form><p><a href="login.php">Back to sign in</a></p></div></section><?php require __DIR__.'/../includes/footer.php';?>
