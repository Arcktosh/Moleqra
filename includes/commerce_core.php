<?php

declare(strict_types=1);

require_once __DIR__ . '/inventory.php';

function commerce_config(?string $key = null, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/../config/commerce.php';
        $cfg = is_file($file) ? (require $file) : [];
    }
    return $key === null ? $cfg : ($cfg[$key] ?? $default);
}

function commerce_tables(): array
{
    return ['customer_accounts','customer_addresses','product_commerce','sales_orders','sales_order_items','stock_reservations','payment_transactions','order_shipments'];
}

function commerce_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= db();
    if (!$pdo || !inventory_schema_ready($pdo)) return false;
    foreach (commerce_tables() as $table) if (!db_table_exists($table, $pdo)) return false;
    return true;
}

function commerce_enabled(): bool { return (bool)commerce_config('enabled', false); }
function commerce_currency(): string { return strtoupper((string)commerce_config('currency', procurement_base_currency())); }
function commerce_money(float $value): string { return commerce_currency() . ' ' . number_format($value, 2, '.', ','); }

function commerce_cart_item_key(int $productId,int $variantId=0): string
{
    return $productId.':'.max(0,$variantId);
}

function commerce_cart_parse_key(string|int $key): array
{
    $key=(string)$key;
    if(str_contains($key,':')){[$p,$v]=array_pad(explode(':',$key,2),2,'0');return ['product_id'=>(int)$p,'variant_id'=>(int)$v];}
    return ['product_id'=>(int)$key,'variant_id'=>0];
}

function commerce_cart(): array
{
    if (!isset($_SESSION['cart_v8']) || !is_array($_SESSION['cart_v8'])) {
        $_SESSION['cart_v8']=[];
        if(isset($_SESSION['cart']) && is_array($_SESSION['cart'])){
            foreach($_SESSION['cart'] as $productId=>$qty){if((float)$qty>0)$_SESSION['cart_v8'][commerce_cart_item_key((int)$productId,0)]=round((float)$qty,3);}
        }
        unset($_SESSION['cart']);
    }
    return $_SESSION['cart_v8'];
}

function commerce_cart_count(): float { return array_sum(array_map('floatval', commerce_cart())); }

function commerce_cart_set(int $productId, float|int $variantIdOrQty, ?float $qty=null): void
{
    if($productId<1)return;
    $variantId=$qty===null?0:(int)$variantIdOrQty;
    $quantity=$qty===null?(float)$variantIdOrQty:$qty;
    commerce_cart();
    $key=commerce_cart_item_key($productId,$variantId);
    if($quantity<=0)unset($_SESSION['cart_v8'][$key]);else $_SESSION['cart_v8'][$key]=round($quantity,3);
}

function commerce_cart_quantity(int $productId,int $variantId=0): float
{
    return (float)(commerce_cart()[commerce_cart_item_key($productId,$variantId)]??0);
}

function commerce_cart_clear(): void { $_SESSION['cart_v8']=[];unset($_SESSION['cart']); }

function commerce_cleanup_expired_reservations(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->query("SELECT DISTINCT order_id FROM stock_reservations WHERE status='Active' AND expires_at IS NOT NULL AND expires_at < NOW() FOR UPDATE");
        $orderIds = array_map('intval', array_column($stmt->fetchAll(), 'order_id'));
        if ($orderIds) {
            $ids = implode(',', $orderIds);
            $pdo->exec("UPDATE stock_reservations SET status='Released' WHERE order_id IN ($ids) AND status='Active'");
            $pdo->exec("UPDATE sales_orders SET status='Expired',payment_status='Expired' WHERE id IN ($ids) AND payment_status='Pending'");
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Moleqra reservation cleanup failed: ' . $e->getMessage());
    }
}

function commerce_reserved_quantity(PDO $pdo, int $batchId): float
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM stock_reservations WHERE batch_id=:batch AND status IN ('Active','Confirmed') AND (expires_at IS NULL OR expires_at >= NOW())");
    $stmt->execute(['batch'=>$batchId]);
    return inventory_decimal($stmt->fetchColumn());
}

function commerce_available_quantity(PDO $pdo, int $productId): float
{
    commerce_cleanup_expired_reservations($pdo);
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(b.units_on_hand),0) on_hand,
        COALESCE((SELECT SUM(r.quantity) FROM stock_reservations r JOIN inventory_batches rb ON rb.id=r.batch_id WHERE rb.product_id=:p2 AND r.status IN ('Active','Confirmed') AND (r.expires_at IS NULL OR r.expires_at>=NOW())),0) reserved
        FROM inventory_batches b WHERE b.product_id=:p1 AND b.status='Released' AND b.units_on_hand>0
        AND (b.expiry_date IS NULL OR b.expiry_date>=CURDATE()) AND (b.retest_date IS NULL OR b.retest_date>CURDATE())");
    $stmt->execute(['p1'=>$productId,'p2'=>$productId]);
    $row = $stmt->fetch() ?: ['on_hand'=>0,'reserved'=>0];
    return max(0, round((float)$row['on_hand'] - (float)$row['reserved'], 3));
}

function commerce_fallback_variant(array $product): array
{
    return [
        'id'=>0,'product_id'=>(int)$product['id'],'sku'=>(string)$product['sku'],'label'=>(string)($product['sale_unit_label']??'unit'),
        'units_per_sale'=>1.0,'price'=>(float)$product['price'],'currency'=>(string)($product['currency']??commerce_currency()),
        'tax_rate'=>(float)($product['tax_rate']??0),'tax_inclusive'=>(int)($product['tax_inclusive']??1),'cart_enabled'=>(int)($product['cart_enabled']??0),
        'min_qty'=>(float)($product['min_qty']??1),'max_qty'=>$product['max_qty']!==null?(float)$product['max_qty']:null,
        'sale_unit_label'=>(string)($product['sale_unit_label']??'unit'),'sort_order'=>0,
    ];
}

function commerce_variant(PDO $pdo,array $product,int $variantId=0): ?array
{
    $variants=function_exists('storefront_variants')?storefront_variants($pdo,(int)$product['id'],true):[];
    if($variants){
        if($variantId>0){foreach($variants as $v)if((int)$v['id']===$variantId)return $v;return null;}
        return $variants[0];
    }
    $fallback=commerce_fallback_variant($product);
    return !empty($fallback['cart_enabled']) && $product['price']!==null ? $fallback : null;
}

function commerce_variant_available_quantity(PDO $pdo,int $productId,array $variant): float
{
    $units=max(0.001,(float)($variant['units_per_sale']??1));
    $base=commerce_available_quantity($pdo,$productId);
    return floor(($base/$units)*1000)/1000;
}

function commerce_product(PDO $pdo, int $productId): ?array
{
    $stmt = $pdo->prepare('SELECT p.*,pc.price,pc.currency,pc.tax_rate,pc.tax_inclusive,pc.cart_enabled,pc.min_qty,pc.max_qty,pc.sale_unit_label,pc.shipping_class
        FROM products p JOIN product_commerce pc ON pc.product_id=p.id WHERE p.id=:id AND p.is_public=1');
    $stmt->execute(['id'=>$productId]);
    $p = $stmt->fetch();
    if (!$p || !procurement_schema_ready($pdo) || !product_publication_gate($pdo, $productId)['allowed']) return null;
    if(function_exists('storefront_meta'))$p=array_merge($p,storefront_meta($pdo,$productId));
    $p['available_base_qty'] = commerce_available_quantity($pdo, $productId);
    $p['variants']=function_exists('storefront_variants')?storefront_variants($pdo,$productId,true):[];
    if($p['variants']){
        $prices=array_map(fn($v)=>(float)$v['price'],$p['variants']);$p['price']=min($prices);$p['cart_enabled']=1;$p['sale_unit_label']='pack';
        $p['available_qty']=max(array_map(fn($v)=>commerce_variant_available_quantity($pdo,$productId,$v),$p['variants']));
    }else{$p['available_qty']=$p['available_base_qty'];}
    return $p;
}

function commerce_public_products(PDO $pdo): array
{
    $rows = $pdo->query('SELECT p.*,pc.price,pc.currency,pc.tax_rate,pc.tax_inclusive,pc.cart_enabled,pc.min_qty,pc.max_qty,pc.sale_unit_label
        FROM products p JOIN product_commerce pc ON pc.product_id=p.id WHERE p.is_public=1 ORDER BY p.sort_order,p.name')->fetchAll();
    $out=[];
    foreach($rows as $row){
        if (!product_publication_gate($pdo,(int)$row['id'])['allowed']) continue;
        if(function_exists('storefront_meta'))$row=array_merge($row,storefront_meta($pdo,(int)$row['id']));
        $row['available_base_qty']=commerce_available_quantity($pdo,(int)$row['id']);
        $variants=function_exists('storefront_variants')?storefront_variants($pdo,(int)$row['id'],true):[];
        $row['variants']=$variants;
        if($variants){$row['price']=min(array_map(fn($v)=>(float)$v['price'],$variants));$row['cart_enabled']=1;$row['sale_unit_label']='pack';$row['available_qty']=max(array_map(fn($v)=>commerce_variant_available_quantity($pdo,(int)$row['id'],$v),$variants));}
        else{
            if(empty($row['cart_enabled']) || $row['price']===null || (float)$row['price']<=0)continue;
            $row['available_qty']=$row['available_base_qty'];
        }
        $out[]=$row;
    }
    return $out;
}

function commerce_price_totals(array $offer, float $qty): array
{
    $qty = round($qty,3);$price=(float)$offer['price'];$rate=max(0,(float)$offer['tax_rate']);$base=round($price*$qty,2);
    if (!empty($offer['tax_inclusive'])) {$total=$base;$tax=$rate>0?round($total-($total/(1+$rate/100)),2):0.0;}
    else {$tax=round($base*$rate/100,2);$total=round($base+$tax,2);}
    return ['subtotal'=>$base,'tax'=>$tax,'total'=>$total];
}

function commerce_cart_details(PDO $pdo, array $destination = []): array
{
    $items=[];$subtotal=0.0;$tax=0.0;$total=0.0;
    foreach(commerce_cart() as $key=>$qty){
        $ids=commerce_cart_parse_key($key);$p=commerce_product($pdo,$ids['product_id']);if(!$p)continue;
        $variant=commerce_variant($pdo,$p,$ids['variant_id']);if(!$variant || empty($variant['cart_enabled']) || $variant['price']===null)continue;
        $qty=round((float)$qty,3);$min=max(0.001,(float)$variant['min_qty']);$max=$variant['max_qty']!==null?(float)$variant['max_qty']:null;
        if($qty<$min)$qty=$min;if($max!==null&&$qty>$max)$qty=$max;
        $available=commerce_variant_available_quantity($pdo,(int)$p['id'],$variant);if($qty>$available)$qty=$available;if($qty<=0)continue;
        $t=commerce_price_totals($variant,$qty);$subtotal+=$t['subtotal'];$tax+=$t['tax'];$total+=$t['total'];
        $items[]=['key'=>(string)$key,'product'=>$p,'variant'=>$variant,'quantity'=>$qty,'inventory_quantity'=>round($qty*(float)$variant['units_per_sale'],3),'available_qty'=>$available,'totals'=>$t];
    }
    $shippingQuote=['rule_id'=>null,'rule_name'=>'Standard shipping','amount'=>0.0,'country_code'=>(string)commerce_config('country_code','ZA'),'province'=>'','estimated'=>true];
    if($items){$province=trim((string)($destination['province']??''));$country=strtoupper(trim((string)($destination['country_code']??commerce_config('country_code','ZA'))));if(function_exists('shipping_quote'))$shippingQuote=shipping_quote($pdo,$total,$province,$country);else{$shippingQuote['amount']=(float)commerce_config('flat_shipping_rate',0);$free=(float)commerce_config('free_shipping_threshold',0);if($free>0&&$total>=$free)$shippingQuote['amount']=0.0;}}
    $shipping=round((float)$shippingQuote['amount'],2);
    return ['items'=>$items,'subtotal'=>round($subtotal,2),'tax'=>round($tax,2),'shipping'=>$shipping,'shipping_quote'=>$shippingQuote,'grand_total'=>round($total+$shipping,2)];
}
