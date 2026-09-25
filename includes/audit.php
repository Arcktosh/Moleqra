<?php

declare(strict_types=1);

function audit_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();return $pdo && db_table_exists('admin_audit_log',$pdo);
}

function admin_audit(PDO $pdo,string $action,string $entityType,?string $entityId=null,string $summary='',array $metadata=[]): void
{
    if(!audit_schema_ready($pdo))return;
    $admin=admin_user();
    $ip=trim((string)($_SERVER['REMOTE_ADDR']??''));
    $ipHash=$ip!==''?hash('sha256',$ip):null;
    $json=$metadata ? json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null;
    $stmt=$pdo->prepare('INSERT INTO admin_audit_log(admin_user_id,action,entity_type,entity_id,summary,metadata_json,ip_hash) VALUES(:admin,:action,:type,:entity,:summary,:metadata,:ip)');
    $stmt->execute(['admin'=>$admin['id']??null,'action'=>$action,'type'=>$entityType,'entity'=>$entityId,'summary'=>$summary?:null,'metadata'=>$json?:null,'ip'=>$ipHash]);
}
