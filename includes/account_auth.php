<?php

declare(strict_types=1);

require_once __DIR__ . '/commerce.php';

function customer_user(): ?array
{
    if (empty($_SESSION['customer_id'])) return null;
    $pdo=db(); if(!$pdo)return null;
    $stmt=$pdo->prepare('SELECT id,email,first_name,last_name,company,phone FROM customer_accounts WHERE id=:id AND is_active=1');
    $stmt->execute(['id'=>(int)$_SESSION['customer_id']]); return $stmt->fetch() ?: null;
}

function customer_login(string $email,string $password): bool
{
    $pdo=db();if(!$pdo||!commerce_schema_ready($pdo))return false;
    $stmt=$pdo->prepare('SELECT * FROM customer_accounts WHERE email=:email AND is_active=1');$stmt->execute(['email'=>strtolower(trim($email))]);$u=$stmt->fetch();
    if(!$u||!password_verify($password,$u['password_hash']))return false;
    session_regenerate_id(true);$_SESSION['customer_id']=(int)$u['id'];$pdo->prepare('UPDATE customer_accounts SET last_login_at=NOW() WHERE id=:id')->execute(['id'=>$u['id']]);return true;
}

function customer_logout(): void
{
    unset($_SESSION['customer_id']);session_regenerate_id(true);
}

function customer_register(array $data): int
{
    $pdo=db();if(!$pdo||!commerce_schema_ready($pdo))throw new RuntimeException('Customer accounts are unavailable.');
    $email=strtolower(trim((string)($data['email']??'')));$password=(string)($data['password']??'');$first=trim((string)($data['first_name']??''));$last=trim((string)($data['last_name']??''));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');if(strlen($password)<10)throw new RuntimeException('Use a password of at least 10 characters.');if($first===''||$last==='')throw new RuntimeException('First and last name are required.');
    try{$stmt=$pdo->prepare('INSERT INTO customer_accounts(email,password_hash,first_name,last_name,company,phone) VALUES(:email,:hash,:first,:last,:company,:phone)');$stmt->execute(['email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT),'first'=>$first,'last'=>$last,'company'=>trim((string)($data['company']??''))?:null,'phone'=>trim((string)($data['phone']??''))?:null]);}
    catch(PDOException $e){if((int)($e->errorInfo[1]??0)===1062)throw new RuntimeException('An account already exists for this email address.');throw $e;}
    return (int)$pdo->lastInsertId();
}
