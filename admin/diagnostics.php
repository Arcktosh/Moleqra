<?php
$adminTitle='Diagnostics';require __DIR__.'/_header.php';require_once __DIR__.'/../includes/diagnostics.php';
$pdo=db();$mailTestMessage='';$mailTestError='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null))$mailTestError='Your session expired.';
    else{
        $action=(string)($_POST['action']??'');
        try{
            if($action==='smtp_probe'){
                $cfg=mail_config();
                if(strtolower((string)($cfg['transport']??''))!=='smtp')throw new RuntimeException('Mail transport is not set to SMTP.');
                $probe=smtp_probe($cfg);
                if(!$probe['ok'])throw new RuntimeException($probe['detail']);
                $mailTestMessage=$probe['detail'].' Connection time: '.(int)$probe['elapsed_ms'].' ms.';
            }elseif($action==='send_test_email'){
                $recipient=trim((string)($_POST['recipient']??''));
                if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid test recipient email address.');
                if(!$pdo)throw new RuntimeException('Database connection is required for the audited test-email action.');
                if(!mailer_delivery_ready())throw new RuntimeException('Outbound mail is not fully configured.');
                $html=mailer_layout('Moleqra email test','<p>This is a test message from the Moleqra back-office diagnostics screen.</p><p>If you received this message, the configured outbound mail transport completed successfully from the application.</p>');
                if(!mailer_send($pdo,'diagnostic_test',$recipient,'Moleqra outbound email test',$html,null,null))throw new RuntimeException('The mail transport reported a delivery failure. Check the server error log and notification audit records.');
                $mailTestMessage='Test email accepted by the configured transport for '.$recipient.'.';
            }
        }catch(Throwable $e){$mailTestError=$e->getMessage();}
    }
}
$result=technical_launch_diagnostics($pdo);
?>
<div class="admin-heading"><div><div class="eyebrow">Pre-launch</div><h1>Technical diagnostics</h1></div><div class="diagnostic-summary"><span class="diag pass"><?= $result['pass'] ?> pass</span><span class="diag warn"><?= $result['warn'] ?> warnings</span><span class="diag fail"><?= $result['fail'] ?> failures</span></div></div>
<div class="notice"><strong>Scope:</strong> these checks cover application/deployment readiness only. They are not a legal, regulatory, tax, product-safety or payment-provider certification.</div>
<?php if($mailTestMessage):?><div class="alert success"><?=e($mailTestMessage)?></div><?php endif;?>
<?php if($mailTestError):?><div class="alert error"><?=e($mailTestError)?></div><?php endif;?>
<section class="card admin-section-gap">
  <h2>Outbound email test</h2>
  <p class="muted">Use the SMTP connection test first. It opens the configured socket, negotiates TLS where required and authenticates, but does not send a message.</p>
  <div class="admin-actions">
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="smtp_probe">
      <button class="btn" type="submit">Test SMTP connection</button>
    </form>
    <form method="post" class="inline-select">
      <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="send_test_email">
      <input type="email" name="recipient" required placeholder="recipient@example.com" value="<?=e((string)config('contact_email',''))?>">
      <button class="btn primary" type="submit">Send test email</button>
    </form>
  </div>
  <p class="muted">A successful SMTP connection proves socket/TLS/authentication. A successful test send means the SMTP server accepted the message; final inbox delivery still depends on the mail server and domain reputation/SPF/DKIM/DMARC.</p>
</section>
<section class="card admin-section-gap"><div class="diagnostic-list"><?php foreach($result['items'] as $item):?><article class="diagnostic-row"><span class="diag <?= e($item['status']) ?>"><?= e(strtoupper($item['status'])) ?></span><div><h3><?= e($item['label']) ?></h3><p><?= e($item['detail']) ?></p><?php if($item['fix']):?><small class="muted"><?= e($item['fix']) ?></small><?php endif;?></div></article><?php endforeach;?></div></section>
<section class="card admin-section-gap"><h2>Launch sequence</h2><ol class="admin-list"><li>Resolve all technical failures above.</li><li>Complete PayFast sandbox checkout, ITN, cancellation and refund-reconciliation tests on public HTTPS staging.</li><li>Test outbound account verification, password reset, payment, dispatch and refund emails.</li><li>Confirm catalogue publication, batch/COA release controls, shipping rules and invoice identity details.</li><li>Run legal/regulatory/privacy/tax review separately before enabling real sales.</li></ol></section>
<?php require __DIR__.'/_footer.php';?>
