<?php

declare(strict_types=1);

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/invoices.php';


function notification_url(string $path): string
{
    $base=rtrim((string)config('base_url',''),'/');
    return $base!=='' ? $base.'/'.ltrim($path,'/') : ltrim($path,'/');
}

function notification_order(PDO $pdo,int $orderId): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM sales_orders WHERE id=:id');$stmt->execute(['id'=>$orderId]);$order=$stmt->fetch();if(!$order)return null;
    $order['items']=function_exists('commerce_order_items')?commerce_order_items($pdo,$orderId):[];return $order;
}

function notification_order_rows(array $order): string
{
    $rows='';foreach($order['items'] as $item){$label=e($item['product_name']).(!empty($item['variant_label'])?' · '.e($item['variant_label']):'');$rows.='<tr><td style="padding:7px 0;border-bottom:1px solid #e5e8ec">'.$label.' × '.e((string)$item['quantity']).'</td><td style="padding:7px 0;border-bottom:1px solid #e5e8ec;text-align:right">'.e($order['currency']).' '.number_format((float)$item['line_total'],2).'</td></tr>';}
    return '<table style="width:100%;border-collapse:collapse">'.$rows.'</table>';
}

function notify_order_created(PDO $pdo,int $orderId): void
{
    $order=notification_order($pdo,$orderId);if(!$order)return;
    $url=notification_url('order.php?token='.$order['order_token']);
    $body='<p>We received order <strong>'.e($order['order_number']).'</strong>. Payment is still pending.</p>'.notification_order_rows($order).'<p><strong>Total: '.e($order['currency']).' '.number_format((float)$order['grand_total'],2).'</strong></p><p><a href="'.e($url).'">View order status</a></p>';
    mailer_send($pdo,'order_created',$order['email'],'Moleqra order '.$order['order_number'],mailer_layout('Order received',$body),(int)$order['id'],$order['customer_id']?(int)$order['customer_id']:null);
}

function notify_payment_received(PDO $pdo,int $orderId): void
{
    $order=notification_order($pdo,$orderId);if(!$order)return;$invoice=invoice_issue_if_needed($pdo,$orderId);
    $body='<p>Payment for <strong>'.e($order['order_number']).'</strong> has been confirmed.</p>'.notification_order_rows($order).'<p><strong>Total paid: '.e($order['currency']).' '.number_format((float)$order['grand_total'],2).'</strong></p>';
    if($invoice)$body.='<p><a href="'.e(notification_url('invoice.php?token='.$order['order_token'])).'">View invoice '.e($invoice['invoice_number']).'</a></p>';
    mailer_send($pdo,'payment_received',$order['email'],'Payment confirmed · '.$order['order_number'],mailer_layout('Payment confirmed',$body),(int)$order['id'],$order['customer_id']?(int)$order['customer_id']:null);
}

function notify_order_dispatched(PDO $pdo,int $orderId): void
{
    $order=notification_order($pdo,$orderId);if(!$order)return;$stmt=$pdo->prepare('SELECT * FROM order_shipments WHERE order_id=:id ORDER BY created_at DESC LIMIT 1');$stmt->execute(['id'=>$orderId]);$shipment=$stmt->fetch()?:[];
    $body='<p>Your order <strong>'.e($order['order_number']).'</strong> has been dispatched.</p><p>Courier: '.e((string)($shipment['courier']??'Courier')).($shipment['service']?' · '.e($shipment['service']):'').'</p>';
    if(!empty($shipment['tracking_number']))$body.='<p>Tracking number: <strong>'.e($shipment['tracking_number']).'</strong></p>';
    $body.='<p><a href="'.e(notification_url('order.php?token='.$order['order_token'])).'">View order status</a></p>';
    mailer_send($pdo,'order_dispatched',$order['email'],'Order dispatched · '.$order['order_number'],mailer_layout('Order dispatched',$body),(int)$order['id'],$order['customer_id']?(int)$order['customer_id']:null);
}

function notify_payment_reminder(PDO $pdo,int $orderId): void
{
    $order=notification_order($pdo,$orderId);if(!$order||$order['payment_status']!=='Pending')throw new RuntimeException('Only pending-payment orders can receive a payment reminder.');
    $body='<p>Order <strong>'.e($order['order_number']).'</strong> is still awaiting payment.</p><p><strong>Total: '.e($order['currency']).' '.number_format((float)$order['grand_total'],2).'</strong></p><p><a href="'.e(notification_url('order.php?token='.$order['order_token'])).'">Resume payment / view order</a></p>';
    mailer_send($pdo,'payment_reminder',$order['email'],'Payment reminder · '.$order['order_number'],mailer_layout('Payment reminder',$body),(int)$order['id'],$order['customer_id']?(int)$order['customer_id']:null);
}

function notify_refund_processed(PDO $pdo,int $refundId): void
{
    $stmt=$pdo->prepare('SELECT r.*,o.email,o.customer_id,o.order_token,o.order_number FROM order_refunds r JOIN sales_orders o ON o.id=r.order_id WHERE r.id=:id');$stmt->execute(['id'=>$refundId]);$refund=$stmt->fetch();if(!$refund)return;
    $body='<p>A refund has been recorded for order <strong>'.e($refund['order_number']).'</strong>.</p><p><strong>'.e($refund['currency']).' '.number_format((float)$refund['amount'],2).'</strong></p><p>Reason: '.e($refund['reason']).'</p><p><a href="'.e(notification_url('refund-receipt.php?token='.$refund['order_token'].'&refund='.$refund['id'])).'">View refund receipt</a></p>';
    mailer_send($pdo,'refund_processed_'.(int)$refund['id'],$refund['email'],'Refund processed · '.$refund['order_number'],mailer_layout('Refund processed',$body),(int)$refund['order_id'],$refund['customer_id']?(int)$refund['customer_id']:null);
}
