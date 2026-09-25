<?php

declare(strict_types=1);

require_once __DIR__ . '/commerce.php';

function payment_config(): array
{
    static $cfg=null;if($cfg!==null)return $cfg;$file=__DIR__.'/../config/payment.php';$cfg=is_file($file)?(require $file):[];return is_array($cfg)?$cfg:[];
}

function payfast_config(): array { return (array)(payment_config()['payfast']??[]); }
function payfast_sandbox(): bool { return (bool)(payfast_config()['sandbox']??true); }
function payfast_host(): string { return payfast_sandbox()?'sandbox.payfast.co.za':'www.payfast.co.za'; }
function payfast_process_url(): string { return 'https://'.payfast_host().'/eng/process'; }
function payfast_validate_url(): string { return 'https://'.payfast_host().'/eng/query/validate'; }
function payfast_configured(): bool { $c=payfast_config();return !empty($c['merchant_id'])&&!empty($c['merchant_key'])&&array_key_exists('passphrase',$c); }

function absolute_url(string $path): string
{
    $base=rtrim((string)config('base_url',''),'/');if($base==='')throw new RuntimeException('Set config/app.php base_url before payment testing.');return $base.'/'.ltrim($path,'/');
}

function payfast_param_string(array $data,bool $includePassphrase=true): string
{
    $parts=[];foreach($data as $key=>$value){if($key==='signature'||$value===''||$value===null)continue;$parts[]=$key.'='.urlencode(trim((string)$value));}
    if($includePassphrase){$pass=(string)(payfast_config()['passphrase']??'');if($pass!=='')$parts[]='passphrase='.urlencode(trim($pass));}
    return implode('&',$parts);
}

function payfast_signature(array $data): string { return md5(payfast_param_string($data,true)); }

function payfast_checkout_data(array $order): array
{
    $c=payfast_config();
    $data=[
        'merchant_id'=>(string)($c['merchant_id']??''),
        'merchant_key'=>(string)($c['merchant_key']??''),
        'return_url'=>absolute_url('payment/return.php?token='.$order['order_token']),
        'cancel_url'=>absolute_url('payment/cancel.php?token='.$order['order_token']),
        'notify_url'=>absolute_url('payment/payfast-itn.php'),
        'name_first'=>$order['first_name'],
        'name_last'=>$order['last_name'],
        'email_address'=>$order['email'],
        'm_payment_id'=>$order['order_number'],
        'amount'=>number_format((float)$order['grand_total'],2,'.',''),
        'item_name'=>'Moleqra '.$order['order_number'],
    ];
    $data['signature']=payfast_signature($data);return $data;
}

function payfast_source_valid(string $remoteIp): bool
{
    if(filter_var($remoteIp,FILTER_VALIDATE_IP)===false)return false;
    $hosts=['www.payfast.co.za','w1w.payfast.co.za','w2w.payfast.co.za','sandbox.payfast.co.za'];$ips=[];
    foreach($hosts as $host){$found=@gethostbynamel($host);if(is_array($found))$ips=array_merge($ips,$found);}if(in_array($remoteIp,array_unique($ips),true))return true;
    $cidrs=['197.97.145.144/28','41.74.179.192/27','102.216.36.0/28','102.216.36.128/28','144.126.193.139/32'];
    foreach($cidrs as $cidr)if(ipv4_in_cidr($remoteIp,$cidr))return true;return false;
}

function ipv4_in_cidr(string $ip,string $cidr): bool
{
    [$net,$prefix]=array_pad(explode('/',$cidr,2),2,'32');$ipn=ip2long($ip);$netn=ip2long($net);if($ipn===false||$netn===false)return false;$prefix=(int)$prefix;$mask=$prefix===0?0:(-1 << (32-$prefix));return (($ipn & $mask)===($netn & $mask));
}

function payfast_server_valid(string $paramString): bool
{
    if(function_exists('curl_init')){
        $ch=curl_init();curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);curl_setopt($ch,CURLOPT_HEADER,false);curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);curl_setopt($ch,CURLOPT_URL,payfast_validate_url());curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$paramString);curl_setopt($ch,CURLOPT_TIMEOUT,20);$response=curl_exec($ch);curl_close($ch);return $response==='VALID';
    }
    if((bool)ini_get('allow_url_fopen')){
        $context=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\n",'content'=>$paramString,'timeout'=>20,'ignore_errors'=>true],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);$response=@file_get_contents(payfast_validate_url(),false,$context);return $response==='VALID';
    }
    return false;
}
