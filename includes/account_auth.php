<?php

declare(strict_types=1);

require_once __DIR__ . '/commerce.php';

function customer_user(): ?array
{
    if (empty($_SESSION['customer_id'])) return null;
    $pdo=db(); if(!$pdo)return null;
    $select='SELECT c.id,c.email,c.first_name,c.last_name,c.company,c.phone';
    $join='';
    if(db_table_exists('customer_account_security',$pdo)){$select.=',s.email_verified_at';$join=' LEFT JOIN customer_account_security s ON s.customer_id=c.id ';}
    else $select.=',NULL email_verified_at';
    $stmt=$pdo->prepare($select.' FROM customer_accounts c '.$join.' WHERE c.id=:id AND c.is_active=1');
    $stmt->execute(['id'=>(int)$_SESSION['customer_id']]); return $stmt->fetch() ?: null;
}

function customer_authenticate(string $email,string $password): array
{
    $pdo=db();if(!$pdo||!commerce_schema_ready($pdo))return ['ok'=>false,'reason'=>'invalid'];
    $stmt=$pdo->prepare('SELECT * FROM customer_accounts WHERE email=:email AND is_active=1');$stmt->execute(['email'=>strtolower(trim($email))]);$u=$stmt->fetch();
    if(!$u||!password_verify($password,$u['password_hash']))return ['ok'=>false,'reason'=>'invalid'];
    if((bool)commerce_config('require_verified_email',false) && db_table_exists('customer_account_security',$pdo)){
        $check=$pdo->prepare('SELECT email_verified_at FROM customer_account_security WHERE customer_id=:id');$check->execute(['id'=>$u['id']]);
        if(!$check->fetchColumn())return ['ok'=>false,'reason'=>'unverified'];
    }
    session_regenerate_id(true);$_SESSION['customer_id']=(int)$u['id'];$pdo->prepare('UPDATE customer_accounts SET last_login_at=NOW() WHERE id=:id')->execute(['id'=>$u['id']]);return ['ok'=>true,'reason'=>'ok'];
}

function customer_login(string $email,string $password): bool { return (bool)customer_authenticate($email,$password)['ok']; }

function customer_logout(): void
{
    unset($_SESSION['customer_id']);session_regenerate_id(true);
}

function customer_register(array $data): int
{
    $pdo=db();if(!$pdo||!commerce_schema_ready($pdo))throw new RuntimeException('Customer accounts are unavailable.');
    $email=strtolower(trim((string)($data['email']??'')));$password=(string)($data['password']??'');$first=trim((string)($data['first_name']??''));$last=trim((string)($data['last_name']??''));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');if(strlen($password)<10)throw new RuntimeException('Use a password of at least 10 characters.');if($first===''||$last==='')throw new RuntimeException('First and last name are required.');
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('INSERT INTO customer_accounts(email,password_hash,first_name,last_name,company,phone) VALUES(:email,:hash,:first,:last,:company,:phone)');$stmt->execute(['email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT),'first'=>$first,'last'=>$last,'company'=>trim((string)($data['company']??''))?:null,'phone'=>trim((string)($data['phone']??''))?:null]);$id=(int)$pdo->lastInsertId();
        if(db_table_exists('customer_account_security',$pdo))$pdo->prepare('INSERT INTO customer_account_security(customer_id,password_changed_at) VALUES(:id,NOW())')->execute(['id'=>$id]);
        $pdo->commit();return $id;
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((int)($e->errorInfo[1]??0)===1062)throw new RuntimeException('An account already exists for this email address.');throw $e;}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
