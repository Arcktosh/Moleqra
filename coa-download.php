<?php
require_once __DIR__ . '/includes/auth.php';
$pdo=db();$id=(int)($_GET['id']??0);
if(!$pdo||$id<1){http_response_code(404);exit('Document not found.');}
$stmt=$pdo->prepare('SELECT c.*,p.is_public product_public FROM coa_documents c JOIN products p ON p.id=c.product_id WHERE c.id=:id');$stmt->execute(['id'=>$id]);$row=$stmt->fetch();
$allowed=$row && (($row['is_public'] && $row['product_public']) || admin_logged_in());
if(!$allowed){http_response_code(404);exit('Document not found.');}
$root=realpath(__DIR__.'/storage/coa');$path=realpath(__DIR__.'/'.$row['file_path']);
if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(404);exit('Document not found.');}
header('Content-Type: application/pdf');header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="Moleqra-COA-'.preg_replace('/[^A-Za-z0-9._-]/','-',(string)$row['batch_number']).'.pdf"');header('X-Content-Type-Options: nosniff');readfile($path);exit;
