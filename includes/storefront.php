<?php

declare(strict_types=1);

function storefront_tables(): array
{
    return ['product_storefront','product_variants','product_related_products','sales_order_item_options','customer_account_security','customer_security_tokens','admin_audit_log'];
}

function storefront_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();
    if (!$pdo || !commerce_schema_ready($pdo)) return false;
    foreach (storefront_tables() as $table) if (!db_table_exists($table,$pdo)) return false;
    return true;
}

function storefront_slugify(string $value): string
{
    $value=trim($value);
    if (function_exists('iconv')) {
        $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
        if (is_string($ascii)) $value=$ascii;
    }
    $value=strtolower($value);
    $value=preg_replace('/[^a-z0-9]+/','-',$value) ?: '';
    return trim($value,'-');
}

function storefront_meta(PDO $pdo,int $productId): array
{
    if (!storefront_schema_ready($pdo)) return [];
    $stmt=$pdo->prepare('SELECT * FROM product_storefront WHERE product_id=:id');
    $stmt->execute(['id'=>$productId]);
    return $stmt->fetch() ?: [];
}

function storefront_variants(PDO $pdo,int $productId,bool $cartOnly=false): array
{
    if (!storefront_schema_ready($pdo)) return [];
    $sql='SELECT * FROM product_variants WHERE product_id=:id';
    if ($cartOnly) $sql.=' AND cart_enabled=1';
    $sql.=' ORDER BY sort_order,label,id';
    $stmt=$pdo->prepare($sql);$stmt->execute(['id'=>$productId]);
    return $stmt->fetchAll();
}

function storefront_variant(PDO $pdo,int $productId,int $variantId): ?array
{
    if ($variantId<1 || !storefront_schema_ready($pdo)) return null;
    $stmt=$pdo->prepare('SELECT * FROM product_variants WHERE id=:variant AND product_id=:product LIMIT 1');
    $stmt->execute(['variant'=>$variantId,'product'=>$productId]);
    return $stmt->fetch() ?: null;
}

function storefront_product_by_slug(PDO $pdo,string $slug): ?array
{
    if (!storefront_schema_ready($pdo) || $slug==='') return null;
    $stmt=$pdo->prepare('SELECT product_id FROM product_storefront WHERE slug=:slug LIMIT 1');
    $stmt->execute(['slug'=>$slug]);
    $id=(int)$stmt->fetchColumn();
    return $id>0 ? commerce_product($pdo,$id) : null;
}

function storefront_product_path(array $product): string
{
    $slug=trim((string)($product['slug']??''));
    return $slug!=='' ? 'product.php?slug='.rawurlencode($slug) : 'product.php?id='.(int)$product['id'];
}

function storefront_categories(array $products): array
{
    $categories=[];
    foreach($products as $product){$cat=trim((string)($product['category']??''));if($cat!=='')$categories[$cat]=true;}
    $out=array_keys($categories);natcasesort($out);return array_values($out);
}

function storefront_stock_message(array $product): string
{
    $available=(float)($product['available_base_qty']??$product['available_qty']??0);
    $custom=trim((string)($product['stock_message']??''));
    if($available<=0)return 'Out of stock';
    if($custom!=='')return $custom;
    $threshold=max(0,(float)($product['low_stock_threshold']??5));
    if($threshold>0 && $available<=$threshold)return 'Low stock';
    return 'In stock';
}

function storefront_related_products(PDO $pdo,int $productId,int $limit=4): array
{
    if(!storefront_schema_ready($pdo))return [];
    $stmt=$pdo->prepare('SELECT p.id FROM product_related_products r JOIN products p ON p.id=r.related_product_id WHERE r.product_id=:id AND p.is_public=1 ORDER BY r.sort_order,p.name LIMIT '.max(1,min(12,$limit)));
    $stmt->execute(['id'=>$productId]);$out=[];
    foreach($stmt->fetchAll() as $row){$p=commerce_product($pdo,(int)$row['id']);if($p)$out[]=$p;}
    return $out;
}


function storefront_summary(array $product): string
{
    foreach(['short_description','description','format'] as $field){$value=trim((string)($product[$field]??''));if($value!=='')return $value;}
    return (string)($product['name']??'Research material');
}

function storefront_search_haystack(array $product): string
{
    return strtolower(implode(' ',[
        (string)($product['sku']??''),(string)($product['name']??''),(string)($product['category']??''),
        (string)($product['format']??''),(string)($product['description']??''),(string)($product['short_description']??''),
        (string)($product['search_keywords']??'')
    ]));
}
