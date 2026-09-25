<?php

declare(strict_types=1);

require_once __DIR__ . '/commerce.php';

function refunds_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();return $pdo && db_table_exists('order_refunds',$pdo);
}

function refund_total_committed(PDO $pdo,int $orderId): float
{
    $stmt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM order_refunds WHERE order_id=:id AND status IN ('Requested','Processed')");$stmt->execute(['id'=>$orderId]);return round((float)$stmt->fetchColumn(),2);
}

function refund_request(PDO $pdo,int $orderId,float $amount,string $reason,?int $paymentTransactionId,?int $adminId): int
{
    if(!refunds_schema_ready($pdo))throw new RuntimeException('Refund tracking is unavailable.');
    $stmt=$pdo->prepare('SELECT * FROM sales_orders WHERE id=:id');$stmt->execute(['id'=>$orderId]);$order=$stmt->fetch();if(!$order||!in_array($order['payment_status'],['Paid','Partially refunded'],true))throw new RuntimeException('Refunds can only be requested for paid orders.');
    $available=round((float)$order['grand_total']-refund_total_committed($pdo,$orderId),2);$amount=round($amount,2);if($amount<=0||$amount>$available+0.001)throw new RuntimeException('Refund amount exceeds the remaining refundable amount.');
    $tmp='TMP-'.bin2hex(random_bytes(6));$stmt=$pdo->prepare("INSERT INTO order_refunds(order_id,payment_transaction_id,refund_number,amount,currency,reason,status,created_by) VALUES(:order,:payment,:number,:amount,:currency,:reason,'Requested',:admin)");
    $stmt->execute(['order'=>$orderId,'payment'=>$paymentTransactionId?:null,'number'=>$tmp,'amount'=>$amount,'currency'=>$order['currency'],'reason'=>trim($reason),'admin'=>$adminId]);$id=(int)$pdo->lastInsertId();$number='MQR-'.date('Ym').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);$pdo->prepare('UPDATE order_refunds SET refund_number=:number WHERE id=:id')->execute(['number'=>$number,'id'=>$id]);return $id;
}

function refund_mark_processed(PDO $pdo,int $refundId,string $gatewayReference='',string $notes=''): array
{
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('SELECT r.*,o.grand_total,o.fulfilment_status,o.payment_status,o.order_token,o.email,o.customer_id FROM order_refunds r JOIN sales_orders o ON o.id=r.order_id WHERE r.id=:id FOR UPDATE');$stmt->execute(['id'=>$refundId]);$refund=$stmt->fetch();if(!$refund)throw new RuntimeException('Refund record not found.');if($refund['status']==='Processed'){$pdo->commit();return $refund;}
        if($refund['status']!=='Requested')throw new RuntimeException('Only requested refunds can be marked processed.');
        $pdo->prepare("UPDATE order_refunds SET status='Processed',gateway_reference=:ref,notes=:notes,processed_at=NOW() WHERE id=:id")->execute(['ref'=>trim($gatewayReference)?:null,'notes'=>trim($notes)?:null,'id'=>$refundId]);
        $stmt=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM order_refunds WHERE order_id=:order AND status='Processed'");$stmt->execute(['order'=>$refund['order_id']]);$processed=round((float)$stmt->fetchColumn(),2);$isFull=$processed+0.001>=(float)$refund['grand_total'];
        if($isFull){
            $pdo->prepare("UPDATE sales_orders SET status='Refunded',payment_status='Refunded' WHERE id=:id")->execute(['id'=>$refund['order_id']]);
            if($refund['fulfilment_status']!=='Fulfilled')$pdo->prepare("UPDATE stock_reservations SET status='Released' WHERE order_id=:id AND status IN ('Active','Confirmed')")->execute(['id'=>$refund['order_id']]);
        }else{$pdo->prepare("UPDATE sales_orders SET status='Partially refunded',payment_status='Partially refunded' WHERE id=:id")->execute(['id'=>$refund['order_id']]);}
        $pdo->commit();$refund['status']='Processed';$refund['gateway_reference']=$gatewayReference;$refund['processed_at']=date('Y-m-d H:i:s');return $refund;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
