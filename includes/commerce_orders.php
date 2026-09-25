<?php

declare(strict_types=1);

function commerce_order_number(): string
{
    return 'MQ-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function commerce_allocate_order(PDO $pdo, int $orderId, int $orderItemId, int $productId, float $qty, int $reservationMinutes): void
{
    $remaining=round($qty,3);
    $stmt=$pdo->prepare("SELECT b.id,b.units_on_hand FROM inventory_batches b WHERE b.product_id=:product AND b.status='Released' AND b.units_on_hand>0
        AND (b.expiry_date IS NULL OR b.expiry_date>=CURDATE()) AND (b.retest_date IS NULL OR b.retest_date>CURDATE())
        ORDER BY COALESCE(b.expiry_date,'9999-12-31'),b.received_at,b.id FOR UPDATE");
    $stmt->execute(['product'=>$productId]);
    foreach($stmt->fetchAll() as $batch){
        $reserved=commerce_reserved_quantity($pdo,(int)$batch['id']);$available=max(0,(float)$batch['units_on_hand']-$reserved);if($available<=0)continue;
        $take=min($remaining,$available);$expires=(new DateTimeImmutable('now'))->modify('+' . max(5,$reservationMinutes) . ' minutes')->format('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO stock_reservations(order_id,order_item_id,batch_id,quantity,status,expires_at) VALUES(:order_id,:item,:batch,:qty,'Active',:expires)")
            ->execute(['order_id'=>$orderId,'item'=>$orderItemId,'batch'=>$batch['id'],'qty'=>$take,'expires'=>$expires]);
        $remaining=round($remaining-$take,3);if($remaining<=0.0005)break;
    }
    if($remaining>0.0005)throw new RuntimeException('Insufficient released stock is available for one or more cart items.');
}

function commerce_create_order(PDO $pdo, array $customer, ?int $customerId = null): array
{
    $cart=commerce_cart_details($pdo,['province'=>(string)($customer['province']??''),'country_code'=>(string)($customer['country_code']??commerce_config('country_code','ZA'))]);
    if(!$cart['items'])throw new RuntimeException('Your cart is empty or the selected stock is no longer available.');
    if($cart['grand_total']<5)throw new RuntimeException('Order total is below the payment gateway minimum.');
    foreach(['email','first_name','last_name','address_line1','city','province','postal_code'] as $field) if(trim((string)($customer[$field]??''))==='')throw new RuntimeException('Please complete all required checkout fields.');
    if(!filter_var($customer['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
    if(empty($customer['ruo_acknowledged'])||empty($customer['terms_accepted']))throw new RuntimeException('The research-use acknowledgement and terms acceptance are required.');

    commerce_cleanup_expired_reservations($pdo);$pdo->beginTransaction();
    try{
        $orderNumber=commerce_order_number();$token=bin2hex(random_bytes(32));
        $stmt=$pdo->prepare("INSERT INTO sales_orders(order_number,order_token,customer_id,status,payment_status,fulfilment_status,currency,subtotal,shipping_total,tax_total,grand_total,email,first_name,last_name,company,phone,address_line1,address_line2,city,province,postal_code,country_code,payment_method,ruo_acknowledged_at,terms_accepted_at,customer_note)
            VALUES(:number,:token,:customer,'Pending payment','Pending','Unfulfilled',:currency,:subtotal,:shipping,:tax,:grand,:email,:first,:last,:company,:phone,:address1,:address2,:city,:province,:postal,:country,'payfast',NOW(),NOW(),:note)");
        $stmt->execute(['number'=>$orderNumber,'token'=>$token,'customer'=>$customerId,'currency'=>commerce_currency(),'subtotal'=>$cart['subtotal'],'shipping'=>$cart['shipping'],'tax'=>$cart['tax'],'grand'=>$cart['grand_total'],'email'=>trim($customer['email']),'first'=>trim($customer['first_name']),'last'=>trim($customer['last_name']),'company'=>trim((string)($customer['company']??''))?:null,'phone'=>trim((string)($customer['phone']??''))?:null,'address1'=>trim($customer['address_line1']),'address2'=>trim((string)($customer['address_line2']??''))?:null,'city'=>trim($customer['city']),'province'=>trim($customer['province']),'postal'=>trim($customer['postal_code']),'country'=>strtoupper(trim((string)($customer['country_code']??commerce_config('country_code','ZA')))),'note'=>trim((string)($customer['customer_note']??''))?:null]);
        $orderId=(int)$pdo->lastInsertId();
        if(function_exists('shipping_schema_ready') && shipping_schema_ready($pdo) && !empty($cart['shipping_quote'])){
            $q=$cart['shipping_quote'];
            $pdo->prepare('INSERT INTO order_shipping_quotes(order_id,shipping_rule_id,rule_name,amount,country_code,province) VALUES(:order,:rule,:name,:amount,:country,:province)')->execute(['order'=>$orderId,'rule'=>$q['rule_id']?:null,'name'=>$q['rule_name'],'amount'=>$q['amount'],'country'=>$q['country_code'],'province'=>$q['province']?:null]);
        }
        $hasOptions=function_exists('storefront_schema_ready')&&storefront_schema_ready($pdo);
        foreach($cart['items'] as $item){
            $p=$item['product'];$v=$item['variant'];$t=$item['totals'];
            $stmt=$pdo->prepare('INSERT INTO sales_order_items(order_id,product_id,sku,product_name,quantity,unit_price,tax_rate,tax_amount,line_total) VALUES(:order,:product,:sku,:name,:qty,:price,:rate,:tax,:line)');
            $stmt->execute(['order'=>$orderId,'product'=>$p['id'],'sku'=>$v['sku']?:$p['sku'],'name'=>$p['name'],'qty'=>$item['quantity'],'price'=>$v['price'],'rate'=>$v['tax_rate'],'tax'=>$t['tax'],'line'=>$t['total']]);
            $itemId=(int)$pdo->lastInsertId();
            if($hasOptions){
                $pdo->prepare('INSERT INTO sales_order_item_options(order_item_id,variant_id,variant_sku,variant_label,units_per_sale,inventory_quantity) VALUES(:item,:variant,:sku,:label,:units,:inventory)')
                    ->execute(['item'=>$itemId,'variant'=>(int)$v['id']>0?(int)$v['id']:null,'sku'=>$v['sku']?:null,'label'=>$v['label']?:null,'units'=>$v['units_per_sale']??1,'inventory'=>$item['inventory_quantity']]);
            }
            commerce_allocate_order($pdo,$orderId,$itemId,(int)$p['id'],(float)$item['inventory_quantity'],(int)commerce_config('reservation_minutes',45));
        }
        $pdo->commit();commerce_cart_clear();
        return ['id'=>$orderId,'order_number'=>$orderNumber,'order_token'=>$token,'grand_total'=>$cart['grand_total']];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function commerce_order_items(PDO $pdo,int $orderId): array
{
    if(function_exists('storefront_schema_ready')&&storefront_schema_ready($pdo)){
        $stmt=$pdo->prepare('SELECT i.*,o.variant_id,o.variant_sku,o.variant_label,o.units_per_sale,o.inventory_quantity FROM sales_order_items i LEFT JOIN sales_order_item_options o ON o.order_item_id=i.id WHERE i.order_id=:id ORDER BY i.id');
    }else{$stmt=$pdo->prepare('SELECT i.*,NULL variant_id,NULL variant_sku,NULL variant_label,1 units_per_sale,i.quantity inventory_quantity FROM sales_order_items i WHERE i.order_id=:id ORDER BY i.id');}
    $stmt->execute(['id'=>$orderId]);return $stmt->fetchAll();
}

function commerce_order_by_token(PDO $pdo,string $token): ?array
{
    if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;
    $stmt=$pdo->prepare('SELECT * FROM sales_orders WHERE order_token=:token LIMIT 1');$stmt->execute(['token'=>$token]);$order=$stmt->fetch();if(!$order)return null;
    $order['items']=commerce_order_items($pdo,(int)$order['id']);
    $stmt=$pdo->prepare('SELECT courier,service,tracking_number,dispatched_at,delivered_at,notes FROM order_shipments WHERE order_id=:id ORDER BY created_at DESC');$stmt->execute(['id'=>$order['id']]);$order['shipments']=$stmt->fetchAll();
    return $order;
}
