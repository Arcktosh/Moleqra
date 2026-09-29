<?php

declare(strict_types=1);

require_once __DIR__.'/mailer.php';

function order_feedback_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();return $pdo && db_table_exists('order_status_events',$pdo);
}

function order_feedback_log(PDO $pdo,int $orderId,string $eventType,string $statusLabel,?string $message=null,?int $adminId=null): void
{
    if(!order_feedback_schema_ready($pdo))return;
    $pdo->prepare('INSERT INTO order_status_events(order_id,event_type,status_label,customer_message,created_by) VALUES(:order,:event,:status,:message,:admin)')
        ->execute(['order'=>$orderId,'event'=>$eventType,'status'=>$statusLabel,'message'=>$message,'admin'=>$adminId]);
}

function order_feedback_send(PDO $pdo,int $orderId,string $eventType,string $title,string $message,?int $adminId=null): void
{
    $stmt=$pdo->prepare('SELECT id,order_number,order_token,email,customer_id,status,payment_status,fulfilment_status FROM sales_orders WHERE id=:id');
    $stmt->execute(['id'=>$orderId]);$order=$stmt->fetch();if(!$order)return;
    order_feedback_log($pdo,$orderId,$eventType,(string)$order['status'],$message,$adminId);
    $base=rtrim((string)config('base_url',''),'/');$url=$base!==''?$base.'/order.php?token='.$order['order_token']:'order.php?token='.$order['order_token'];
    $body='<p>'.e($message).'</p><p><strong>Order: '.e($order['order_number']).'</strong></p><p>Payment: '.e($order['payment_status']).'<br>Fulfilment: '.e($order['fulfilment_status']).'</p><p><a href="'.e($url).'">View current order status</a></p>';
    mailer_send($pdo,'order_status_'.$eventType,(string)$order['email'],$title.' · '.$order['order_number'],mailer_layout($title,$body),$orderId,$order['customer_id']?(int)$order['customer_id']:null);
}

function notify_order_cancelled(PDO $pdo,int $orderId,?int $adminId=null): void
{ order_feedback_send($pdo,$orderId,'cancelled','Order cancelled','Your unpaid order has been cancelled and any temporary stock reservation has been released.',$adminId); }
function notify_payment_failed(PDO $pdo,int $orderId): void
{ order_feedback_send($pdo,$orderId,'payment_failed','Payment not completed','The payment attempt for your order was not completed. No order will be dispatched until payment is confirmed.'); }
function notify_payment_cancelled(PDO $pdo,int $orderId): void
{ order_feedback_send($pdo,$orderId,'payment_cancelled','Payment cancelled','The payment process was cancelled. Your order remains unpaid and may expire if payment is not completed.'); }
function notify_stock_review(PDO $pdo,int $orderId): void
{ order_feedback_send($pdo,$orderId,'stock_review','Payment received — stock review','Payment was received, but the order requires a stock allocation review before dispatch. Our team will verify the order before fulfilment.'); }
function notify_order_expired(PDO $pdo,int $orderId): void
{ order_feedback_send($pdo,$orderId,'expired','Order expired','Your unpaid order expired and the temporary stock reservation was released. You may create a new order if you still wish to purchase the items.'); }
function notify_order_delivered(PDO $pdo,int $orderId,?int $adminId=null): void
{ order_feedback_send($pdo,$orderId,'delivered','Order delivered','Your order has been marked as delivered. Thank you for your order.',$adminId); }
