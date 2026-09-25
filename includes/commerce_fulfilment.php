<?php

declare(strict_types=1);

function commerce_order_reservations_complete(PDO $pdo, int $orderId): bool
{
    $withOptions=function_exists('storefront_schema_ready')&&storefront_schema_ready($pdo);
    $target=$withOptions?'COALESCE(o.inventory_quantity,i.quantity)':'i.quantity';
    $join=$withOptions?' LEFT JOIN sales_order_item_options o ON o.order_item_id=i.id ':'';
    $stmt=$pdo->prepare("SELECT i.id,$target required_qty,COALESCE(SUM(CASE WHEN r.status IN ('Active','Confirmed') THEN r.quantity ELSE 0 END),0) reserved_qty FROM sales_order_items i $join LEFT JOIN stock_reservations r ON r.order_item_id=i.id WHERE i.order_id=:id GROUP BY i.id,$target");
    $stmt->execute(['id'=>$orderId]);foreach($stmt->fetchAll() as $row){if((float)$row['reserved_qty']+0.0005<(float)$row['required_qty'])return false;}return true;
}

function commerce_allocate_missing_order_stock(PDO $pdo, int $orderId): void
{
    $withOptions=function_exists('storefront_schema_ready')&&storefront_schema_ready($pdo);$target=$withOptions?'COALESCE(o.inventory_quantity,i.quantity)':'i.quantity';$join=$withOptions?' LEFT JOIN sales_order_item_options o ON o.order_item_id=i.id ':'';
    $stmt=$pdo->prepare("SELECT i.id,i.product_id,$target required_qty,COALESCE(SUM(CASE WHEN r.status IN ('Active','Confirmed') THEN r.quantity ELSE 0 END),0) reserved_qty FROM sales_order_items i $join LEFT JOIN stock_reservations r ON r.order_item_id=i.id WHERE i.order_id=:id GROUP BY i.id,i.product_id,$target");
    $stmt->execute(['id'=>$orderId]);foreach($stmt->fetchAll() as $row){$missing=round((float)$row['required_qty']-(float)$row['reserved_qty'],3);if($missing>0.0005)commerce_allocate_order($pdo,$orderId,(int)$row['id'],(int)$row['product_id'],$missing,(int)commerce_config('reservation_minutes',45));}
}

function commerce_confirm_payment(PDO $pdo,int $orderId,string $gatewayPaymentId,string $gatewayStatus): void
{
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('SELECT * FROM sales_orders WHERE id=:id FOR UPDATE');$stmt->execute(['id'=>$orderId]);$order=$stmt->fetch();if(!$order)throw new RuntimeException('Order not found.');
        if($gatewayStatus==='COMPLETE'){
            $stockReady=commerce_order_reservations_complete($pdo,$orderId);
            if(!$stockReady){$pdo->exec('SAVEPOINT payment_stock');try{commerce_allocate_missing_order_stock($pdo,$orderId);$stockReady=commerce_order_reservations_complete($pdo,$orderId);}catch(Throwable $allocationError){$pdo->exec('ROLLBACK TO SAVEPOINT payment_stock');$stockReady=false;error_log('Moleqra paid order requires stock review: '.$allocationError->getMessage());}}
            $status=$stockReady?'Paid':'Paid - stock review';$pdo->prepare("UPDATE sales_orders SET status=:status,payment_status='Paid',paid_at=COALESCE(paid_at,NOW()),cancelled_at=NULL WHERE id=:id")->execute(['status'=>$status,'id'=>$orderId]);$pdo->prepare("UPDATE stock_reservations SET status='Confirmed',expires_at=NULL WHERE order_id=:id AND status='Active'")->execute(['id'=>$orderId]);
        }elseif(in_array($gatewayStatus,['FAILED','CANCELLED'],true)){$pdo->prepare("UPDATE sales_orders SET status='Payment failed',payment_status=:status WHERE id=:id AND payment_status<>'Paid'")->execute(['status'=>ucfirst(strtolower($gatewayStatus)),'id'=>$orderId]);$pdo->prepare("UPDATE stock_reservations SET status='Released' WHERE order_id=:id AND status='Active'")->execute(['id'=>$orderId]);}
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function commerce_fulfil_order(PDO $pdo,int $orderId,string $courier,?string $service,?string $tracking,?string $notes,?int $adminId): void
{
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('SELECT * FROM sales_orders WHERE id=:id FOR UPDATE');$stmt->execute(['id'=>$orderId]);$order=$stmt->fetch();
        if(!$order||!in_array($order['payment_status'],['Paid','Partially refunded'],true))throw new RuntimeException('Only paid orders can be fulfilled.');
        if($order['fulfilment_status']==='Fulfilled')throw new RuntimeException('This order is already fulfilled.');
        if(!commerce_order_reservations_complete($pdo,$orderId))throw new RuntimeException('This paid order does not have complete stock reservations and requires stock review before fulfilment.');
        $stmt=$pdo->prepare("SELECT r.*,b.units_on_hand FROM stock_reservations r JOIN inventory_batches b ON b.id=r.batch_id WHERE r.order_id=:id AND r.status='Confirmed' FOR UPDATE");$stmt->execute(['id'=>$orderId]);$rows=$stmt->fetchAll();if(!$rows)throw new RuntimeException('No confirmed stock reservations are available for this order.');
        foreach($rows as $r){$qty=(float)$r['quantity'];$newQty=round((float)$r['units_on_hand']-$qty,3);if($newQty< -0.0005)throw new RuntimeException('Reserved stock is no longer available.');if($newQty<0)$newQty=0;$pdo->prepare("UPDATE inventory_batches SET units_on_hand=:qty,status=CASE WHEN :zero=1 THEN 'Depleted' ELSE status END WHERE id=:id")->execute(['qty'=>$newQty,'zero'=>$newQty<=0?1:0,'id'=>$r['batch_id']]);$pdo->prepare("INSERT INTO inventory_movements(batch_id,movement_type,quantity_delta,reference,reason,created_by) VALUES(:batch,'Sale',:delta,:ref,:reason,:admin)")->execute(['batch'=>$r['batch_id'],'delta'=>-$qty,'ref'=>$order['order_number'],'reason'=>'Fulfilled sales order '.$order['order_number'],'admin'=>$adminId]);}
        $pdo->prepare("UPDATE stock_reservations SET status='Consumed' WHERE order_id=:id AND status='Confirmed'")->execute(['id'=>$orderId]);$pdo->prepare("INSERT INTO order_shipments(order_id,courier,service,tracking_number,dispatched_at,notes,created_by) VALUES(:order,:courier,:service,:tracking,NOW(),:notes,:admin)")->execute(['order'=>$orderId,'courier'=>$courier?:null,'service'=>$service?:null,'tracking'=>$tracking?:null,'notes'=>$notes?:null,'admin'=>$adminId]);$pdo->prepare("UPDATE sales_orders SET status='Fulfilled',fulfilment_status='Fulfilled' WHERE id=:id")->execute(['id'=>$orderId]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
