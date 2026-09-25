<?php

declare(strict_types=1);

require_once __DIR__ . '/commerce.php';

function mail_config(): array
{
    static $cfg=null;if($cfg!==null)return $cfg;$file=__DIR__.'/../config/mail.php';$cfg=is_file($file)?(require $file):[];return is_array($cfg)?$cfg:[];
}

function mailer_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= db();return $pdo && db_table_exists('commerce_notifications',$pdo);
}

function mailer_send(PDO $pdo,string $type,string $recipient,string $subject,string $html,?int $orderId=null,?int $customerId=null): bool
{
    $cfg=mail_config();$enabled=(bool)($cfg['enabled']??false);$transport=(string)($cfg['transport']??'log');
    if($orderId && mailer_schema_ready($pdo)){$check=$pdo->prepare("SELECT COUNT(*) FROM commerce_notifications WHERE order_id=:order AND notification_type=:type AND status IN ('Sent','Logged')");$check->execute(['order'=>$orderId,'type'=>$type]);if((int)$check->fetchColumn()>0)return true;}
    $status=$enabled?'Pending':'Logged';$id=0;
    if(mailer_schema_ready($pdo)){
        $stmt=$pdo->prepare('INSERT INTO commerce_notifications(order_id,customer_id,notification_type,recipient,subject,transport,status) VALUES(:order_id,:customer_id,:type,:recipient,:subject,:transport,:status)');
        $stmt->execute(['order_id'=>$orderId,'customer_id'=>$customerId,'type'=>$type,'recipient'=>$recipient,'subject'=>$subject,'transport'=>$transport,'status'=>$status]);$id=(int)$pdo->lastInsertId();
    }
    if(!$enabled || $transport==='log')return true;
    if($transport!=='mail'){
        if($id)$pdo->prepare("UPDATE commerce_notifications SET status='Failed',error_message='Unsupported mail transport.' WHERE id=:id")->execute(['id'=>$id]);
        return false;
    }
    $fromEmail=trim((string)($cfg['from_email']??config('contact_email','')));$fromName=trim((string)($cfg['from_name']??config('site_name','Moleqra')));$reply=trim((string)($cfg['reply_to']??''));
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||!filter_var($fromEmail,FILTER_VALIDATE_EMAIL)){
        if($id)$pdo->prepare("UPDATE commerce_notifications SET status='Failed',error_message='Mail addresses are not configured.' WHERE id=:id")->execute(['id'=>$id]);
        return false;
    }
    $headers=['MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','From: '.$fromName.' <'.$fromEmail.'>'];if(filter_var($reply,FILTER_VALIDATE_EMAIL))$headers[]='Reply-To: '.$reply;
    $ok=@mail($recipient,$subject,$html,implode("\r\n",$headers));
    if($id)$pdo->prepare("UPDATE commerce_notifications SET status=:status,error_message=:error,sent_at=CASE WHEN :ok=1 THEN NOW() ELSE sent_at END WHERE id=:id")->execute(['status'=>$ok?'Sent':'Failed','error'=>$ok?null:'PHP mail() returned false.','ok'=>$ok?1:0,'id'=>$id]);
    return $ok;
}

function mailer_layout(string $title,string $body): string
{
    $site=e((string)config('site_name','Moleqra'));
    return '<!doctype html><html><body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#17202a"><div style="max-width:680px;margin:0 auto;padding:28px"><div style="background:#0b1020;color:#fff;padding:20px 24px;font-weight:700;letter-spacing:.08em">'.$site.'</div><div style="background:#fff;padding:26px;border:1px solid #dde3ea"><h1 style="font-size:24px;margin:0 0 18px">'.e($title).'</h1>'.$body.'<p style="margin-top:28px;color:#65717e;font-size:13px">Research-use-only materials are not for human or veterinary administration.</p></div></div></body></html>';
}
