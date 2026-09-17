<?php

declare(strict_types=1);

function supplier_ops_load(PDO $pdo, int $id): array
{
    $stmt=$pdo->prepare('SELECT * FROM supplier_qualifications WHERE supplier_id=:id'); $stmt->execute(['id'=>$id]); $qualification=$stmt->fetch() ?: [];
    $stmt=$pdo->prepare('SELECT so.*,au.display_name FROM supplier_outreach so LEFT JOIN admin_users au ON au.id=so.created_by WHERE so.supplier_id=:id ORDER BY so.contacted_at DESC,so.id DESC'); $stmt->execute(['id'=>$id]); $outreach=$stmt->fetchAll();
    $stmt=$pdo->prepare('SELECT sp.*,p.sku,p.name AS product_name FROM supplier_products sp JOIN products p ON p.id=sp.product_id WHERE sp.supplier_id=:id ORDER BY p.name'); $stmt->execute(['id'=>$id]); $offers=$stmt->fetchAll();
    $stmt=$pdo->prepare('SELECT * FROM supplier_test_orders WHERE supplier_id=:id ORDER BY ordered_at DESC,id DESC'); $stmt->execute(['id'=>$id]); $tests=$stmt->fetchAll();
    $products=$pdo->query('SELECT id,sku,name FROM products ORDER BY name')->fetchAll();
    return compact('qualification','outreach','offers','tests','products');
}
