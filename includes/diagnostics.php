<?php

declare(strict_types=1);

require_once __DIR__.'/commerce.php';
require_once __DIR__.'/commercial_ops.php';
require_once __DIR__.'/storefront.php';
require_once __DIR__.'/payments.php';
require_once __DIR__.'/mailer.php';
require_once __DIR__.'/courier.php';

function diagnostic_item(string $label,string $status,string $detail,string $fix=''): array
{
    return compact('label','status','detail','fix');
}

function technical_launch_diagnostics(?PDO $pdo=null): array
{
    $pdo ??= db();$items=[];
    $items[]=diagnostic_item('PHP runtime',version_compare(PHP_VERSION,'8.1.0','>=')?'pass':'fail','PHP '.PHP_VERSION,version_compare(PHP_VERSION,'8.1.0','>=')?'':'Use PHP 8.1 or newer.');
    $base=(string)config('base_url','');$baseOk=filter_var($base,FILTER_VALIDATE_URL)&&str_starts_with(strtolower($base),'https://');
    $items[]=diagnostic_item('Public HTTPS base URL',$baseOk?'pass':'fail',$base!==''?$base:'Not configured','Set config/app.php base_url to the final HTTPS origin.');
    $https=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off';$items[]=diagnostic_item('Current request HTTPS',$https?'pass':'warn',$https?'HTTPS detected':'HTTPS not detected for this request','Use HTTPS on staging and production.');
    $items[]=diagnostic_item('Database',$pdo?'pass':'fail',$pdo?'Connected':'Not configured','Configure config/database.php and test the connection.');
    if($pdo){
        $schemaChecks=[['Supplier operations',sourcing_schema_ready($pdo),'V2'],['Procurement/launch',procurement_schema_ready($pdo),'V4'],['Inventory',inventory_schema_ready($pdo),'V5'],['Commerce',commerce_schema_ready($pdo),'V6'],['Commercial operations',commercial_ops_schema_ready($pdo),'V7'],['Storefront hardening',storefront_schema_ready($pdo),'V8']];
        foreach($schemaChecks as [$label,$ok,$version])$items[]=diagnostic_item($label,$ok?'pass':'fail',$ok?'Ready':$version.' upgrade required',$ok?'':'Run the upgrade from System.');
    }
    $storage=__DIR__.'/../storage';$writable=is_dir($storage)&&is_writable($storage);$items[]=diagnostic_item('Protected storage',$writable?'pass':'fail',$writable?'Writable':'Not writable','Make storage writable by PHP and keep direct web access blocked.');
    $contact=(string)config('contact_email','');$items[]=diagnostic_item('Contact email',filter_var($contact,FILTER_VALIDATE_EMAIL)?'pass':'warn',$contact?:'Not configured','Set a monitored contact email before launch.');
    $companyAddress=trim((string)config('company_address',''));$items[]=diagnostic_item('Company address',$companyAddress!==''?'pass':'warn',$companyAddress!==''?'Configured':'Not configured','Add the legal/operating address before issuing customer-facing invoices.');
    $paymentConfigured=payfast_configured();$paymentDetail=$paymentConfigured?(payfast_sandbox()?'Configured · sandbox':'Configured · live'):'Credentials not configured';$items[]=diagnostic_item('PayFast',$paymentConfigured?(payfast_sandbox()?'warn':'pass'):'fail',$paymentDetail,$paymentConfigured?(payfast_sandbox()?'Complete sandbox ITN tests before live mode.':'Confirm live credentials only after sandbox sign-off.'):'Create config/payment.php from the example file.');
    $mail=mail_config();$mailEnabled=!empty($mail['enabled']);$mailTransport=strtolower((string)($mail['transport']??'log'));$mailDeliverable=$mailEnabled&&$mailTransport==='mail';$items[]=diagnostic_item('Transactional email',$mailDeliverable?'pass':'warn',$mailEnabled?'Enabled · '.$mailTransport:'Log-only / disabled','Configure and test outbound email before relying on verification, reset and order emails.');
    $verificationRequired=(bool)commerce_config('require_verified_email',false);$verificationReady=!$verificationRequired||($baseOk&&$mailDeliverable);$items[]=diagnostic_item('Required email verification',$verificationReady?'pass':'fail',$verificationRequired?($verificationReady?'Required · delivery ready':'Required · dependencies incomplete'):'Optional / not enforced',$verificationReady?'':($baseOk?'Enable a tested outbound mail transport before enforcing verification.':'Configure the final HTTPS base_url and tested outbound mail before enforcing verification.'));
    if($pdo&&commerce_schema_ready($pdo)){$public=(int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_public=1')->fetchColumn();$items[]=diagnostic_item('Public catalogue',$public>0?'pass':'warn',$public.' public product'.($public===1?'':'s'),$public>0?'':'Publish only after the launch gate passes.');}
    if($pdo&&db_table_exists('coa_documents',$pdo)){$coas=(int)$pdo->query('SELECT COUNT(*) FROM coa_documents WHERE is_public=1')->fetchColumn();$items[]=diagnostic_item('Public COA records',$coas>0?'pass':'warn',$coas.' public COA record'.($coas===1?'':'s'),$coas>0?'':'Publish batch documentation before commercial launch where applicable.');}
    if($pdo&&storefront_schema_ready($pdo)){$variants=(int)$pdo->query('SELECT COUNT(*) FROM product_variants WHERE cart_enabled=1')->fetchColumn();$slugs=(int)$pdo->query("SELECT COUNT(*) FROM product_storefront WHERE slug IS NOT NULL AND slug<>'' AND seo_indexable=1")->fetchColumn();$items[]=diagnostic_item('Sellable pack sizes',$variants>0?'pass':'warn',$variants.' enabled pack size'.($variants===1?'':'s'),$variants>0?'':'Configure pack sizes in Storefront.');$items[]=diagnostic_item('SEO product slugs',$slugs>0?'pass':'warn',$slugs.' indexable product slug'.($slugs===1?'':'s'),$slugs>0?'':'Save storefront slugs/meta before indexing.');}
    $fail=count(array_filter($items,fn($i)=>$i['status']==='fail'));$warn=count(array_filter($items,fn($i)=>$i['status']==='warn'));
    return ['items'=>$items,'fail'=>$fail,'warn'=>$warn,'pass'=>count($items)-$fail-$warn];
}
