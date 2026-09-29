<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';require_once __DIR__.'/../includes/newsletter.php';
$file=__DIR__.'/../config/automation.php';$cfg=is_file($file)?(array)(require $file):[];$batch=max(1,min(100,(int)($cfg['newsletter_batch_limit']??50)));
if(PHP_SAPI!=='cli'){$expected=(string)($cfg['newsletter_key']??'');$header=(string)($_SERVER['HTTP_AUTHORIZATION']??'');$provided=str_starts_with($header,'Bearer ')?substr($header,7):'';if($expected===''||$provided===''||!hash_equals($expected,$provided)){http_response_code(403);exit('Forbidden');}}
$pdo=db();if(!$pdo||!newsletter_schema_ready($pdo)){http_response_code(503);exit('Newsletter automation is not ready.');}
try{$rows=$pdo->query("SELECT id FROM newsletter_campaigns WHERE status='Running' AND (scheduled_at IS NULL OR scheduled_at<=NOW()) ORDER BY id LIMIT 10")->fetchAll();$out=['campaigns'=>0,'sent'=>0,'failed'=>0];foreach($rows as $row){$r=newsletter_process_campaign($pdo,(int)$row['id'],$batch);$out['campaigns']++;$out['sent']+=$r['sent'];$out['failed']+=$r['failed'];}header('Content-Type: application/json; charset=UTF-8');echo json_encode($out,JSON_UNESCAPED_SLASHES);}
catch(Throwable $e){error_log('Moleqra newsletter automation failed: '.$e->getMessage());http_response_code(500);echo 'Automation failed.';}
