<?php
$pageTitle='Checkout | Moleqra';$pageRobots='noindex,nofollow';
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/commerce.php';
require_once __DIR__.'/includes/account_auth.php';
require_once __DIR__.'/includes/customer_addresses.php';
require_once __DIR__.'/includes/notifications.php';

function checkout_quote_token(array $cart,array $data): string
{
    $lines=[];foreach((array)($cart['items']??[]) as $item){$lines[]=implode(':',[(string)($item['key']??''),number_format((float)($item['quantity']??0),3,'.',''),number_format((float)($item['variant']['price']??0),2,'.','')]);}
    $payload=implode('|',[
        strtoupper(trim((string)($data['country_code']??'ZA'))),
        strtolower(trim((string)($data['province']??''))),
        implode(',',$lines),
        number_format((float)($cart['shipping']??0),2,'.',''),
        number_format((float)($cart['grand_total']??0),2,'.',''),
    ]);
    return hash_hmac('sha256',$payload,csrf_token());
}

function checkout_validate_contact(array $data): void
{
    foreach(['email','first_name','last_name','address_line1','city','province','postal_code'] as $field){
        if(trim((string)($data[$field]??''))==='')throw new RuntimeException('Please complete all required checkout fields.');
    }
    if(!filter_var((string)$data['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
    if(empty($data['ruo_acknowledged'])||empty($data['terms_accepted']))throw new RuntimeException('The research-use acknowledgement and terms acceptance are required.');
}

$pdo=db();$error='';$user=customer_user();$defaultAddress=null;$review=false;
if($user && $pdo && commerce_schema_ready($pdo))$defaultAddress=customer_default_address($pdo,(int)$user['id']);
if(!$pdo||!commerce_schema_ready($pdo)||!commerce_enabled()){http_response_code(503);$error='Online checkout is not currently available.';}

$prefill=[
    'first_name'=>$_POST['first_name']??($user['first_name']??''),'last_name'=>$_POST['last_name']??($user['last_name']??''),'email'=>$_POST['email']??($user['email']??''),
    'phone'=>$_POST['phone']??($defaultAddress['phone']??($user['phone']??'')),'company'=>$_POST['company']??($defaultAddress['company']??($user['company']??'')),
    'address_line1'=>$_POST['address_line1']??($defaultAddress['address_line1']??''),'address_line2'=>$_POST['address_line2']??($defaultAddress['address_line2']??''),
    'city'=>$_POST['city']??($defaultAddress['city']??''),'province'=>$_POST['province']??($defaultAddress['province']??''),'postal_code'=>$_POST['postal_code']??($defaultAddress['postal_code']??''),
    'country_code'=>$_POST['country_code']??($defaultAddress['country_code']??commerce_config('country_code','ZA')),
];
$destination=['province'=>$prefill['province'],'country_code'=>$prefill['country_code']];
$cart=$pdo&&commerce_schema_ready($pdo)?commerce_cart_details($pdo,$destination):['items'=>[],'grand_total'=>0,'subtotal'=>0,'tax'=>0,'shipping'=>0,'shipping_quote'=>['rule_name'=>'Shipping','estimated'=>true]];

if($_SERVER['REQUEST_METHOD']==='POST'&&!$error){
    if(!csrf_valid($_POST['csrf_token']??null))$error='Your session expired.';
    else{
        $data=$_POST;$data['ruo_acknowledged']=isset($_POST['ruo_acknowledged']);$data['terms_accepted']=isset($_POST['terms_accepted']);$data['country_code']=$prefill['country_code'];
        try{
            checkout_validate_contact($data);
            $cart=commerce_cart_details($pdo,['province'=>$data['province'],'country_code'=>$data['country_code']]);
            if(!$cart['items'])throw new RuntimeException('Your cart is empty or the selected stock is no longer available.');
            if((string)($_POST['action']??'review')==='place'){
                $expected=checkout_quote_token($cart,$data);
                if(!hash_equals($expected,(string)($_POST['quote_token']??'')))throw new RuntimeException('The shipping quote changed. Please review the updated total before continuing.');
                $order=commerce_create_order($pdo,$data,$user['id']??null);
                if($user && !empty($_POST['save_address'])){
                    try{customer_save_checkout_address($pdo,(int)$user['id'],$data);}catch(Throwable $addressError){error_log('Moleqra address save failed: '.$addressError->getMessage());}
                }
                try{notify_order_created($pdo,(int)$order['id']);}catch(Throwable $mailError){error_log('Moleqra order notification failed: '.$mailError->getMessage());}
                header('Location: payment/payfast-start.php?token='.$order['order_token']);exit;
            }
            $review=true;
        }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Checkout could not be completed.';}
    }
}
$quoteToken=$review?checkout_quote_token($cart,$prefill):'';
require __DIR__.'/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Secure checkout</div><h1><?= $review?'Review order':'Checkout' ?></h1><p><?= $review?'Confirm the delivery details and final server-calculated total before payment.':'Your payment will be completed on PayFast\'s hosted payment page.' ?></p></div></section>
<section class="section"><div class="container">
<?php if($error):?><div class="alert error"><?= e($error) ?></div><?php endif;?>
<?php if($cart['items']):?>
<div class="checkout-layout">
<form method="post" class="card"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="country_code" value="<?= e($prefill['country_code']) ?>"><?php if($review):?><input type="hidden" name="quote_token" value="<?= e($quoteToken) ?>"><?php endif;?>
<h2>Contact &amp; shipping</h2>
<?php if($user && $defaultAddress&&!$review):?><p class="muted">Your default saved address has been loaded. You can change it for this order.</p><?php endif;?>
<div class="form-grid">
<div class="field"><label>First name</label><input name="first_name" required maxlength="100" value="<?= e($prefill['first_name']) ?>"></div>
<div class="field"><label>Last name</label><input name="last_name" required maxlength="100" value="<?= e($prefill['last_name']) ?>"></div>
<div class="field"><label>Email</label><input type="email" name="email" required maxlength="180" value="<?= e($prefill['email']) ?>"></div>
<div class="field"><label>Phone</label><input name="phone" maxlength="60" value="<?= e($prefill['phone']) ?>"></div>
<div class="field full"><label>Company / research organisation</label><input name="company" maxlength="160" value="<?= e($prefill['company']) ?>"></div>
<div class="field full"><label>Address</label><input name="address_line1" required maxlength="180" value="<?= e($prefill['address_line1']) ?>"></div>
<div class="field full"><label>Address line 2</label><input name="address_line2" maxlength="180" value="<?= e($prefill['address_line2']) ?>"></div>
<div class="field"><label>City</label><input name="city" required maxlength="120" value="<?= e($prefill['city']) ?>"></div>
<div class="field"><label>Province</label><input name="province" required maxlength="120" value="<?= e($prefill['province']) ?>" placeholder="e.g. Gauteng"></div>
<div class="field"><label>Postal code</label><input name="postal_code" required maxlength="30" value="<?= e($prefill['postal_code']) ?>"></div>
<div class="field full"><label>Order note</label><textarea name="customer_note"><?= e($_POST['customer_note']??'') ?></textarea></div>
</div>
<?php if($user):?><label class="checkout-save-address"><input type="checkbox" name="save_address" value="1"<?= isset($_POST['save_address'])?' checked':'' ?>> Save this as my default address</label><?php endif;?>
<div class="checkout-consents">
<label><input type="checkbox" name="ruo_acknowledged" required<?= isset($_POST['ruo_acknowledged'])?' checked':'' ?>> I confirm this order is for legitimate research/laboratory purposes and acknowledge the products are not for human or veterinary use.</label>
<label><input type="checkbox" name="terms_accepted" required<?= isset($_POST['terms_accepted'])?' checked':'' ?>> I accept Moleqra's terms, including the refund/return and delivery terms shown during checkout, and the privacy notice.</label>
</div>
<div class="checkout-policy-summary"><strong>Refunds &amp; delivery:</strong> Eligible refunds are processed back through the original payment route where supported. Dispatch occurs only after confirmed payment and stock allocation. Opened, compromised or non-resalable research materials may not be returnable. Full details are in the <a href="terms.php" target="_blank">terms</a>.</div>
<?php if($review):?><div class="alert success">Final shipping rule: <?= e($cart['shipping_quote']['rule_name']??'Shipping') ?> · <?= $cart['shipping']?commerce_money($cart['shipping']):'Free' ?>. If you change the province, review the total again.</div><button class="btn primary" type="submit" name="action" value="place">Confirm &amp; continue to PayFast</button> <button class="btn" type="submit" name="action" value="review">Recalculate total</button><?php else:?><button class="btn primary" type="submit" name="action" value="review">Review shipping &amp; total</button><?php endif;?>
</form>
<aside class="card checkout-sidebar"><h2><?= $review?'Final order total':'Order estimate' ?></h2><?php foreach($cart['items'] as $item):?><p><span><?= e($item['product']['name'].(!empty($item['variant']['label'])?' · '.$item['variant']['label']:'')) ?> × <?= e((string)$item['quantity']) ?></span><strong><?= commerce_money($item['totals']['total']) ?></strong></p><?php endforeach;?><hr><p><span><?= e($cart['shipping_quote']['rule_name']??'Shipping') ?></span><strong><?= $cart['shipping']?commerce_money($cart['shipping']):'Free' ?></strong></p><p class="checkout-total"><span>Total</span><strong><?= commerce_money($cart['grand_total']) ?></strong></p><small class="muted"><?= $review?'This total was calculated server-side for the submitted destination.':'Enter your delivery province and review the order to lock the payment total.' ?></small></aside>
</div>
<?php elseif(!$error):?><div class="card"><h2>Your cart is empty.</h2><a class="btn" href="catalog.php">Browse catalogue</a></div><?php endif;?>
</div></section>
<?php require __DIR__.'/includes/footer.php';?>
