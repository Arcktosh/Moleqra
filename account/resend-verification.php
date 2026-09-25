<?php
$rootPrefix='../';$pageTitle='Resend verification | Moleqra';$pageRobots='noindex,nofollow';require_once __DIR__.'/../includes/bootstrap.php';require_once __DIR__.'/../includes/customer_security.php';$user=customer_user();$message='';$error='';$prefill=$user['email']??'';$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null))$error='Your session expired.';else{
        $last=(int)($_SESSION['verification_requested_at']??0);if(time()-$last>=30){$_SESSION['verification_requested_at']=time();$email=strtolower(trim((string)($_POST['email']??'')));if($pdo&&filter_var($email,FILTER_VALIDATE_EMAIL)){try{$s=$pdo->prepare('SELECT id FROM customer_accounts WHERE email=:email AND is_active=1 LIMIT 1');$s->execute(['email'=>$email]);$id=(int)$s->fetchColumn();if($id>0&&!customer_email_verified($pdo,$id))customer_send_verification($pdo,$id);}catch(Throwable $e){error_log('Moleqra verification resend failed: '.$e->getMessage());}}}
        $message='If that account requires verification, a fresh verification link has been requested.';
    }
}
require __DIR__.'/../includes/header.php';?>
<section class="page-hero"><div class="container"><div class="eyebrow">Customer account</div><h1>Resend verification</h1></div></section><section class="section"><div class="container narrow"><?php if($message):?><div class="alert success"><?= e($message) ?></div><?php endif;?><?php if($error):?><div class="alert error"><?= e($error) ?></div><?php endif;?><form method="post" class="card"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="field"><label>Email</label><input type="email" name="email" required value="<?= e($prefill) ?>" autocomplete="email"></div><button class="btn primary">Request verification email</button></form><p><a href="login.php">Back to sign in</a>.</p></div></section><?php require __DIR__.'/../includes/footer.php';?>
