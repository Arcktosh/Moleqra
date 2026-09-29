<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/smtp.php';
require_once __DIR__ . '/communications.php';

function mail_config(): array
{
    static $cfg=null;if($cfg!==null)return $cfg;$file=__DIR__.'/../config/mail.php';$cfg=is_file($file)?(require $file):[];return is_array($cfg)?$cfg:[];
}

function mailer_delivery_ready(): bool
{
    $cfg=mail_config();
    if(empty($cfg['enabled']))return false;
    $transport=strtolower(trim((string)($cfg['transport']??'log')));
    if($transport==='mail')return true;
    if($transport==='smtp'){
        $smtp=smtp_config($cfg);
        return $smtp['host']!=='' && (!$smtp['auth'] || (trim($smtp['username'])!=='' && $smtp['password']!==''));
    }
    return false;
}

function mailer_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= db();return $pdo && db_table_exists('commerce_notifications',$pdo);
}

function mailer_send(PDO $pdo,string $type,string $recipient,string $subject,string $html,?int $orderId=null,?int $customerId=null,bool $mirrorCommunication=true): bool
{
    $cfg=mail_config();$enabled=(bool)($cfg['enabled']??false);$transport=(string)($cfg['transport']??'log');
    if($orderId && mailer_schema_ready($pdo)){$check=$pdo->prepare("SELECT COUNT(*) FROM commerce_notifications WHERE order_id=:order AND notification_type=:type AND status IN ('Sent','Logged')");$check->execute(['order'=>$orderId,'type'=>$type]);if((int)$check->fetchColumn()>0)return true;}
    $status=$enabled?'Pending':'Logged';$id=0;
    if(mailer_schema_ready($pdo)){
        $stmt=$pdo->prepare('INSERT INTO commerce_notifications(order_id,customer_id,notification_type,recipient,subject,transport,status) VALUES(:order_id,:customer_id,:type,:recipient,:subject,:transport,:status)');
        $stmt->execute(['order_id'=>$orderId,'customer_id'=>$customerId,'type'=>$type,'recipient'=>$recipient,'subject'=>$subject,'transport'=>$transport,'status'=>$status]);$id=(int)$pdo->lastInsertId();
    }
    if(!$enabled || $transport==='log'){
        if($mirrorCommunication && $customerId && communications_schema_ready($pdo))communication_record_outbound($pdo,$recipient,$subject,$html,['customer_id'=>$customerId,'source'=>'System email','status'=>'Logged']);
        return true;
    }
    $fromEmail=trim((string)($cfg['from_email']??config('contact_email','')));$fromName=trim((string)($cfg['from_name']??config('site_name','Moleqra')));$reply=trim((string)($cfg['reply_to']??''));
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||!filter_var($fromEmail,FILTER_VALIDATE_EMAIL)){
        if($id)$pdo->prepare("UPDATE commerce_notifications SET status='Failed',error_message='Mail addresses are not configured.' WHERE id=:id")->execute(['id'=>$id]);
        return false;
    }
    $ok=false;$error=null;
    try{
        if($transport==='mail'){
            $headers=['MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','From: '.$fromName.' <'.$fromEmail.'>'];if(filter_var($reply,FILTER_VALIDATE_EMAIL))$headers[]='Reply-To: '.$reply;
            $ok=@mail($recipient,$subject,$html,implode("\r\n",$headers));
            if(!$ok)$error='PHP mail() returned false.';
        }elseif($transport==='smtp'){
            smtp_send($cfg,$recipient,$subject,$html,$fromEmail,$fromName,$reply);
            $ok=true;
        }else{
            $error='Unsupported mail transport.';
        }
    }catch(Throwable $e){
        $error=$e->getMessage();$ok=false;error_log('Moleqra mail delivery failed: '.$error);
    }
    if($id)$pdo->prepare("UPDATE commerce_notifications SET status=:status,error_message=:error,sent_at=CASE WHEN :ok=1 THEN NOW() ELSE sent_at END WHERE id=:id")->execute(['status'=>$ok?'Sent':'Failed','error'=>$ok?null:substr((string)$error,0,1000),'ok'=>$ok?1:0,'id'=>$id]);
    if($mirrorCommunication && $customerId && communications_schema_ready($pdo))communication_record_outbound($pdo,$recipient,$subject,$html,['customer_id'=>$customerId,'source'=>'System email','status'=>$ok?'Sent':'Failed','error'=>$ok?null:$error]);
    return $ok;
}

function mailer_layout(string $title,string $body): string
{
    $site=e((string)config('site_name','Moleqra'));$brand=branding_settings();$logo=branding_logo_path();$logoHtml='';
    $base=rtrim((string)config('base_url',''),'/');if($logo!==''&&$base!=='')$logoHtml='<img src="'.e($base.'/'.ltrim($logo,'/')).'" alt="" style="display:block;max-height:46px;max-width:180px;margin-bottom:12px">';
    return '<!doctype html><html><body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#17202a"><div style="max-width:680px;margin:0 auto;padding:28px"><div style="background:'.e($brand['background_color']).';color:'.e($brand['text_color']).';padding:20px 24px;font-weight:700;letter-spacing:.08em">'.$logoHtml.$site.'</div><div style="background:#fff;padding:26px;border:1px solid #dde3ea"><h1 style="font-size:24px;margin:0 0 18px;color:#17202a">'.e($title).'</h1>'.$body.'<p style="margin-top:28px;color:#65717e;font-size:13px">Research-use-only materials are not for human or veterinary administration.</p></div></div></body></html>';
}
