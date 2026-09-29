<?php

declare(strict_types=1);

require_once __DIR__.'/mailer.php';

function newsletter_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();
    return $pdo && db_table_exists('newsletter_subscribers',$pdo) && db_table_exists('newsletter_campaigns',$pdo) && db_table_exists('newsletter_queue',$pdo);
}

function newsletter_ip_hash(): ?string
{
    $ip=trim((string)($_SERVER['REMOTE_ADDR']??''));
    return $ip===''?null:hash('sha256',$ip);
}

function newsletter_subscribe(PDO $pdo,string $email,?int $customerId=null,string $source='Website'): void
{
    $email=strtolower(trim($email));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
    $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$unsubscribe=bin2hex(random_bytes(32));$unsubHash=hash('sha256',$unsubscribe);
    $stmt=$pdo->prepare("INSERT INTO newsletter_subscribers(customer_id,email,status,source,confirm_token_hash,confirm_expires_at,unsubscribe_token_hash,consent_ip_hash) VALUES(:customer,:email,'Pending',:source,:confirm,DATE_ADD(NOW(),INTERVAL 24 HOUR),:unsub,:ip)
        ON DUPLICATE KEY UPDATE customer_id=COALESCE(VALUES(customer_id),customer_id),status=IF(status='Subscribed','Subscribed','Pending'),source=VALUES(source),confirm_token_hash=IF(status='Subscribed',confirm_token_hash,VALUES(confirm_token_hash)),confirm_expires_at=IF(status='Subscribed',confirm_expires_at,VALUES(confirm_expires_at)),unsubscribe_token_hash=COALESCE(unsubscribe_token_hash,VALUES(unsubscribe_token_hash)),unsubscribed_at=NULL,consent_ip_hash=VALUES(consent_ip_hash)");
    $stmt->execute(['customer'=>$customerId,'email'=>$email,'source'=>substr($source,0,80),'confirm'=>$hash,'unsub'=>$unsubHash,'ip'=>newsletter_ip_hash()]);
    if(newsletter_mail_ready()){
        $base=rtrim((string)config('base_url',''),'/');
        $url=$base.'/newsletter-confirm.php?token='.rawurlencode($token);
        $body='<p>Thanks for subscribing to updates from '.e((string)config('site_name','Moleqra')).'.</p><p><a href="'.e($url).'">Confirm your subscription</a></p><p>If you did not request this, you can ignore this message.</p>';
        mailer_send($pdo,'newsletter_confirm',$email,'Confirm your '.config('site_name','Moleqra').' updates subscription',mailer_layout('Confirm your subscription',$body),null,$customerId);
    }
}

function newsletter_mail_ready(): bool
{
    $cfg=mail_config();return !empty($cfg['enabled']) && strtolower((string)($cfg['transport']??''))==='mail' && rtrim((string)config('base_url',''),'/')!=='';
}

function newsletter_confirm(PDO $pdo,string $token): bool
{
    if(!preg_match('/^[a-f0-9]{64}$/',$token))return false;
    $hash=hash('sha256',$token);
    $stmt=$pdo->prepare("UPDATE newsletter_subscribers SET status='Subscribed',confirmed_at=COALESCE(confirmed_at,NOW()),confirm_token_hash=NULL,confirm_expires_at=NULL WHERE confirm_token_hash=:hash AND confirm_expires_at>=NOW()");
    $stmt->execute(['hash'=>$hash]);
    return $stmt->rowCount()===1;
}

function newsletter_unsubscribe(PDO $pdo,string $token): bool
{
    if(!preg_match('/^[a-f0-9]{64}$/',$token))return false;
    $hash=hash('sha256',$token);
    $stmt=$pdo->prepare("UPDATE newsletter_subscribers SET status='Unsubscribed',unsubscribed_at=NOW(),confirm_token_hash=NULL,confirm_expires_at=NULL WHERE unsubscribe_token_hash=:hash");
    $stmt->execute(['hash'=>$hash]);
    return $stmt->rowCount()===1;
}

function newsletter_queue_campaign(PDO $pdo,int $campaignId): int
{
    $s=$pdo->prepare('SELECT * FROM newsletter_campaigns WHERE id=:id');$s->execute(['id'=>$campaignId]);$c=$s->fetch();
    if(!$c)throw new RuntimeException('Newsletter campaign not found.');
    $insert=$pdo->prepare("INSERT IGNORE INTO newsletter_queue(campaign_id,subscriber_id,recipient,subject,body_html,status,scheduled_at)
      SELECT :campaign,n.id,n.email,:subject,:body,'Pending',COALESCE(:scheduled,NOW())
      FROM newsletter_subscribers n WHERE n.status='Subscribed'");
    $insert->execute(['campaign'=>$campaignId,'subject'=>$c['subject_template'],'body'=>$c['body_html'],'scheduled'=>$c['scheduled_at']?:null]);
    return $insert->rowCount();
}

function newsletter_process_campaign(PDO $pdo,int $campaignId,?int $limit=null): array
{
    if(!newsletter_mail_ready())throw new RuntimeException('Outbound mail and public base URL must be configured before newsletter sending.');
    $s=$pdo->prepare('SELECT * FROM newsletter_campaigns WHERE id=:id');$s->execute(['id'=>$campaignId]);$c=$s->fetch();
    if(!$c)throw new RuntimeException('Newsletter campaign not found.');
    if($c['status']!=='Running')throw new RuntimeException('Only running newsletter campaigns can send.');
    if($c['scheduled_at'] && strtotime((string)$c['scheduled_at'])>time())return ['sent'=>0,'failed'=>0,'remaining'=>0];
    $batch=max(1,min(100,$limit??(int)$c['batch_limit']));
    $q=$pdo->prepare("SELECT q.*,n.unsubscribe_token_hash FROM newsletter_queue q JOIN newsletter_subscribers n ON n.id=q.subscriber_id WHERE q.campaign_id=:id AND q.status='Pending' AND q.scheduled_at<=NOW() AND n.status='Subscribed' ORDER BY q.id LIMIT $batch");
    $q->execute(['id'=>$campaignId]);$sent=0;$failed=0;
    foreach($q->fetchAll() as $row){
        try{
            $claim=$pdo->prepare("UPDATE newsletter_queue SET status='Sending',attempts=attempts+1,last_error=NULL WHERE id=:id AND status='Pending'");$claim->execute(['id'=>$row['id']]);if($claim->rowCount()!==1)continue;
            $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
            $pdo->prepare('UPDATE newsletter_subscribers SET unsubscribe_token_hash=:hash WHERE id=:id')->execute(['hash'=>$hash,'id'=>$row['subscriber_id']]);
            $base=rtrim((string)config('base_url',''),'/');
            $body=(string)$row['body_html'].'<p style="margin-top:28px;font-size:12px;color:#65717e">You are receiving this because you subscribed to '.e((string)config('site_name','Moleqra')).' updates. <a href="'.e($base.'/newsletter-unsubscribe.php?token='.$token).'">Unsubscribe</a>.</p>';
            $ok=mailer_send($pdo,'newsletter_'.$campaignId.'_'.(int)$row['subscriber_id'],(string)$row['recipient'],(string)$row['subject'],mailer_layout('Updates from '.config('site_name','Moleqra'),$body),null,null);
            if(!$ok)throw new RuntimeException('Mail transport returned failure.');
            $pdo->prepare("UPDATE newsletter_queue SET status='Sent',sent_at=NOW() WHERE id=:id")->execute(['id'=>$row['id']]);
            $pdo->prepare('UPDATE newsletter_subscribers SET last_sent_at=NOW() WHERE id=:id')->execute(['id'=>$row['subscriber_id']]);$sent++;
        }catch(Throwable $e){
            $failed++;$pdo->prepare("UPDATE newsletter_queue SET status=IF(attempts>=3,'Failed','Pending'),last_error=:error,scheduled_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE) WHERE id=:id")->execute(['error'=>substr($e->getMessage(),0,1000),'id'=>$row['id']]);
        }
    }
    $pdo->prepare('UPDATE newsletter_campaigns SET last_processed_at=NOW() WHERE id=:id')->execute(['id'=>$campaignId]);
    $r=$pdo->prepare("SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id=:id AND status='Pending'");$r->execute(['id'=>$campaignId]);$remaining=(int)$r->fetchColumn();
    if($remaining===0)$pdo->prepare("UPDATE newsletter_campaigns SET status='Completed' WHERE id=:id AND status='Running'")->execute(['id'=>$campaignId]);
    return ['sent'=>$sent,'failed'=>$failed,'remaining'=>$remaining];
}
