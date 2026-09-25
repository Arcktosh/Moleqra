<?php

declare(strict_types=1);

require_once __DIR__ . '/commerce.php';

function invoice_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();return $pdo && db_table_exists('sales_invoices',$pdo);
}

function invoice_issue_if_needed(PDO $pdo,int $orderId): ?array
{
    if(!invoice_schema_ready($pdo))return null;
    $stmt=$pdo->prepare('SELECT * FROM sales_invoices WHERE order_id=:id LIMIT 1');$stmt->execute(['id'=>$orderId]);$existing=$stmt->fetch();if($existing)return $existing;
    $stmt=$pdo->prepare('SELECT * FROM sales_orders WHERE id=:id');$stmt->execute(['id'=>$orderId]);$order=$stmt->fetch();if(!$order||$order['payment_status']!=='Paid')return null;
    $number='MQI-'.date('Ym').'-'.str_pad((string)$orderId,6,'0',STR_PAD_LEFT);
    $stmt=$pdo->prepare('INSERT INTO sales_invoices(order_id,invoice_number,currency,subtotal,shipping_total,tax_total,grand_total) VALUES(:order,:number,:currency,:subtotal,:shipping,:tax,:grand)');
    try{$stmt->execute(['order'=>$orderId,'number'=>$number,'currency'=>$order['currency'],'subtotal'=>$order['subtotal'],'shipping'=>$order['shipping_total'],'tax'=>$order['tax_total'],'grand'=>$order['grand_total']]);}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1062)throw $e;}
    $stmt=$pdo->prepare('SELECT * FROM sales_invoices WHERE order_id=:id LIMIT 1');$stmt->execute(['id'=>$orderId]);return $stmt->fetch()?:null;
}

function invoice_for_order(PDO $pdo,int $orderId): ?array
{
    if(!invoice_schema_ready($pdo))return null;$stmt=$pdo->prepare('SELECT * FROM sales_invoices WHERE order_id=:id LIMIT 1');$stmt->execute(['id'=>$orderId]);return $stmt->fetch()?:null;
}
