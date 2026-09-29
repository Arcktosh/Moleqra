<?php

declare(strict_types=1);

require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/outreach_automation.php';

function automation_config(): array
{
    $file=__DIR__.'/../config/automation.php';return is_file($file)?(array)(require $file):[];
}

$cfg=automation_config();$batch=max(1,min(20,(int)($cfg['batch_limit']??5)));
if(PHP_SAPI!=='cli'){
    $expected=(string)($cfg['outreach_key']??'');$header=(string)($_SERVER['HTTP_AUTHORIZATION']??'');$provided=str_starts_with($header,'Bearer ')?substr($header,7):'';
    if($expected===''||$provided===''||!hash_equals($expected,$provided)){http_response_code(403);header('Content-Type: text/plain; charset=UTF-8');exit('Forbidden');}
}
$pdo=db();if(!$pdo||!outreach_schema_ready($pdo)){http_response_code(503);exit('Outreach automation is not ready.');}
try{$result=outreach_process_running($pdo,$batch);header('Content-Type: application/json; charset=UTF-8');echo json_encode($result,JSON_UNESCAPED_SLASHES);}
catch(Throwable $e){error_log('Moleqra outreach automation failed: '.$e->getMessage());http_response_code(500);echo 'Automation failed.';}
