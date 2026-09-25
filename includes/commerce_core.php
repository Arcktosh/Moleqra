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

function commerce_enabled(): bool
{
    return (bool)commerce_config('enabled', false);
}

function commerce_currency(): string
{
    return strtoupper((string)commerce_config('currency', procurement_base_currency()));
}

function commerce_money(float $value): string
{
    return commerce_currency() . ' ' . number_format($value, 2, '.', ',');
}

function commerce_cart(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
    return $_SESSION['cart'];
}

function commerce_cart_count(): float
{
    return array_sum(array_map('floatval', commerce_cart()));
}

function commerce_cart_set(int $productId, float $qty): void
{
    if ($productId < 1) return;
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
    if ($qty <= 0) unset($_SESSION['cart'][$productId]);
    else $_SESSION['cart'][$productId] = round($qty, 3);
}

function commerce_cart_clear(): void
{
    $_SESSION['cart'] = [];
}

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

function commerce_product(PDO $pdo, int $productId): ?array
{
    $stmt = $pdo->prepare('SELECT p.*,pc.price,pc.currency,pc.tax_rate,pc.tax_inclusive,pc.cart_enabled,pc.min_qty,pc.max_qty,pc.sale_unit_label,pc.shipping_class
        FROM products p JOIN product_commerce pc ON pc.product_id=p.id WHERE p.id=:id AND p.is_public=1');
    $stmt->execute(['id'=>$productId]);
    $p = $stmt->fetch();
    if (!$p || !procurement_schema_ready($pdo) || !product_publication_gate($pdo, $productId)['allowed']) return null;
    $p['available_qty'] = commerce_available_quantity($pdo, $productId);
    return $p;
}

function commerce_public_products(PDO $pdo): array
{
    $rows = $pdo->query('SELECT p.*,pc.price,pc.currency,pc.tax_rate,pc.tax_inclusive,pc.cart_enabled,pc.min_qty,pc.max_qty,pc.sale_unit_label
        FROM products p JOIN product_commerce pc ON pc.product_id=p.id WHERE p.is_public=1 AND pc.cart_enabled=1 AND pc.price IS NOT NULL AND pc.price>0 ORDER BY p.sort_order,p.name')->fetchAll();
    $out=[];
    foreach($rows as $row){
        if (!product_publication_gate($pdo,(int)$row['id'])['allowed']) continue;
        $row['available_qty']=commerce_available_quantity($pdo,(int)$row['id']);
        $out[]=$row;
    }
    return $out;
}

function commerce_price_totals(array $product, float $qty): array
{
    $qty = round($qty,3);
    $price = (float)$product['price'];
    $rate = max(0,(float)$product['tax_rate']);
    $base = round($price*$qty,2);
    if (!empty($product['tax_inclusive'])) {
        $total = $base;
        $tax = $rate > 0 ? round($total - ($total/(1+$rate/100)),2) : 0.0;
    } else {
        $tax = round($base*$rate/100,2);
        $total = round($base+$tax,2);
    }
    return ['subtotal'=>$base,'tax'=>$tax,'total'=>$total];
}

function commerce_cart_details(PDO $pdo): array
{
    $items=[];$subtotal=0.0;$tax=0.0;$total=0.0;
    foreach(commerce_cart() as $productId=>$qty){
        $p=commerce_product($pdo,(int)$productId);
        if(!$p || empty($p['cart_enabled']) || $p['price']===null) continue;
        $qty=round((float)$qty,3);
        $min=max(0.001,(float)$p['min_qty']);$max=$p['max_qty']!==null?(float)$p['max_qty']:null;
        if($qty<$min)$qty=$min;if($max!==null&&$qty>$max)$qty=$max;
        if($qty>(float)$p['available_qty'])$qty=(float)$p['available_qty'];
        if($qty<=0)continue;
        $t=commerce_price_totals($p,$qty);$subtotal+=$t['subtotal'];$tax+=$t['tax'];$total+=$t['total'];
        $items[]=['product'=>$p,'quantity'=>$qty,'totals'=>$t];
    }
    $shipping=0.0;
    if($items){$free=(float)commerce_config('free_shipping_threshold',0);$shipping=($free>0&&$total>=$free)?0.0:(float)commerce_config('flat_shipping_rate',0);}
    return ['items'=>$items,'subtotal'=>round($subtotal,2),'tax'=>round($tax,2),'shipping'=>round($shipping,2),'grand_total'=>round($total+$shipping,2)];
}
