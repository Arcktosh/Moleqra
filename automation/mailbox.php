<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/mailbox.php';
$file=__DIR__.'/../config/automation.php';$cfg=is_file($file)?(array)(require $file):[];
if(PHP_SAPI!=='cli'){
    $expected=(string)($cfg['mailbox_key']??'');$header=(string)($_SERVER['HTTP_AUTHORIZATION']??'');$provided=str_starts_with($header,'Bearer ')?substr($header,7):'';
    if($expected===''||$provided===''||!hash_equals($expected,$provided)){http_response_code(403);exit('Forbidden');}
}
$pdo=db();if(!$pdo||!communications_schema_ready($pdo)){http_response_code(503);exit('Communications are not ready.');}
try{$result=mailbox_sync($pdo);header('Content-Type: application/json; charset=UTF-8');echo json_encode($result,JSON_UNESCAPED_SLASHES);}
catch(Throwable $e){error_log('Moleqra mailbox sync failed: '.$e->getMessage());http_response_code(500);echo 'Mailbox sync failed.';}
