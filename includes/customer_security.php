<?php

declare(strict_types=1);

require_once __DIR__.'/account_auth.php';
require_once __DIR__.'/mailer.php';
require_once __DIR__.'/seo.php';

function customer_security_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();return $pdo && db_table_exists('customer_account_security',$pdo) && db_table_exists('customer_security_tokens',$pdo);
}

function customer_security_row(PDO $pdo,int $customerId): ?array
{
    if(!customer_security_ready($pdo))return null;
    $stmt=$pdo->prepare('SELECT * FROM customer_account_security WHERE customer_id=:id');$stmt->execute(['id'=>$customerId]);return $stmt->fetch()?:null;
}

function customer_email_verified(PDO $pdo,int $customerId): bool
{
    $row=customer_security_row($pdo,$customerId);return $row ? !empty($row['email_verified_at']) : true;
}

function customer_security_issue_token(PDO $pdo,int $customerId,string $type,int $minutes): string
{
    if(!customer_security_ready($pdo))throw new RuntimeException('Account security upgrade is not installed.');
    $raw=bin2hex(random_bytes(32));$hash=hash('sha256',$raw);$expires=(new DateTimeImmutable('now'))->modify('+'.max(5,$minutes).' minutes')->format('Y-m-d H:i:s');$ip=trim((string)($_SERVER['REMOTE_ADDR']??''));
    $pdo->prepare('UPDATE customer_security_tokens SET used_at=COALESCE(used_at,NOW()) WHERE customer_id=:customer AND token_type=:type AND used_at IS NULL')->execute(['customer'=>$customerId,'type'=>$type]);
    $pdo->prepare('INSERT INTO customer_security_tokens(customer_id,token_type,token_hash,expires_at,request_ip_hash) VALUES(:customer,:type,:hash,:expires,:ip)')->execute(['customer'=>$customerId,'type'=>$type,'hash'=>$hash,'expires'=>$expires,'ip'=>$ip!==''?hash('sha256',$ip):null]);
    return $raw;
}

function customer_security_consume_token(PDO $pdo,string $raw,string $type): ?array
{
    if(!customer_security_ready($pdo)||!preg_match('/^[a-f0-9]{64}$/',$raw))return null;
    $hash=hash('sha256',$raw);$stmt=$pdo->prepare('SELECT t.*,c.email,c.first_name,c.last_name FROM customer_security_tokens t JOIN customer_accounts c ON c.id=t.customer_id WHERE t.token_hash=:hash AND t.token_type=:type AND t.used_at IS NULL AND t.expires_at>=NOW() LIMIT 1');$stmt->execute(['hash'=>$hash,'type'=>$type]);return $stmt->fetch()?:null;
}

function customer_send_verification(PDO $pdo,int $customerId): void
{
    $stmt=$pdo->prepare('SELECT id,email,first_name FROM customer_accounts WHERE id=:id AND is_active=1');$stmt->execute(['id'=>$customerId]);$user=$stmt->fetch();if(!$user)return;
    $token=customer_security_issue_token($pdo,$customerId,'verify_email',1440);$url=seo_url('account/verify-email.php?token='.$token);if($url==='')return;
    $pdo->prepare('INSERT INTO customer_account_security(customer_id,verification_sent_at) VALUES(:id,NOW()) ON DUPLICATE KEY UPDATE verification_sent_at=NOW()')->execute(['id'=>$customerId]);
    $body='<p>Hello '.e($user['first_name']).',</p><p>Confirm your email address for your Moleqra customer account.</p><p><a href="'.e($url).'">Verify email address</a></p><p>This link expires after 24 hours.</p>';
    mailer_send($pdo,'email_verification',(string)$user['email'],'Verify your Moleqra email',mailer_layout('Verify your email',$body),null,$customerId);
}

function customer_request_password_reset(PDO $pdo,string $email): void
{
    if(!customer_security_ready($pdo))return;
    $stmt=$pdo->prepare('SELECT id,email,first_name FROM customer_accounts WHERE email=:email AND is_active=1 LIMIT 1');$stmt->execute(['email'=>strtolower(trim($email))]);$user=$stmt->fetch();
    if(!$user){password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT);return;}
    $recent=$pdo->prepare("SELECT COUNT(*) FROM customer_security_tokens WHERE customer_id=:id AND token_type='password_reset' AND created_at>=DATE_SUB(NOW(),INTERVAL 2 MINUTE)");$recent->execute(['id'=>$user['id']]);if((int)$recent->fetchColumn()>0)return;
    $token=customer_security_issue_token($pdo,(int)$user['id'],'password_reset',60);$url=seo_url('account/reset-password.php?token='.$token);if($url==='')return;
    $body='<p>Hello '.e($user['first_name']).',</p><p>A password reset was requested for your Moleqra account.</p><p><a href="'.e($url).'">Reset password</a></p><p>This single-use link expires after 60 minutes. If you did not request it, you can ignore this message.</p>';
    mailer_send($pdo,'password_reset_'.date('YmdHi'),(string)$user['email'],'Reset your Moleqra password',mailer_layout('Reset your password',$body),null,(int)$user['id']);
}

function customer_reset_password(PDO $pdo,string $rawToken,string $password,string $confirm): bool
{
    if($password!==$confirm)throw new RuntimeException('The password confirmation does not match.');
    if(strlen($password)<10)throw new RuntimeException('Use a password of at least 10 characters.');
    $token=customer_security_consume_token($pdo,$rawToken,'password_reset');if(!$token)return false;
    $pdo->beginTransaction();
    try{
        $pdo->prepare('UPDATE customer_accounts SET password_hash=:hash WHERE id=:id')->execute(['hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$token['customer_id']]);
        $pdo->prepare("UPDATE customer_security_tokens SET used_at=NOW() WHERE customer_id=:id AND token_type='password_reset' AND used_at IS NULL")->execute(['id'=>$token['customer_id']]);
        $pdo->prepare('INSERT INTO customer_account_security(customer_id,password_changed_at) VALUES(:id,NOW()) ON DUPLICATE KEY UPDATE password_changed_at=NOW()')->execute(['id'=>$token['customer_id']]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    $body='<p>Your Moleqra account password was changed successfully.</p><p>If you did not make this change, contact Moleqra immediately.</p>';
    mailer_send($pdo,'password_changed_'.date('YmdHis'),(string)$token['email'],'Your Moleqra password was changed',mailer_layout('Password changed',$body),null,(int)$token['customer_id']);
    return true;
}
