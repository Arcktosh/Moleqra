<?php
require_once __DIR__ . '/bootstrap.php';
$pageTitle = $pageTitle ?? config('site_name');
$rootPrefix = $rootPrefix ?? '';
require_once __DIR__ . '/commerce.php';
require_once __DIR__ . '/seo.php';
$pageDescription = $pageDescription ?? 'Moleqra provides research-use-only peptide materials with batch documentation and transparent quality controls.';
$pageRobots=$pageRobots??null;
$script=(string)($_SERVER['PHP_SELF']??'');$basename=basename($script);
if($pageRobots===null && (str_contains($script,'/account/')||str_contains($script,'/payment/')||in_array($basename,['order.php','invoice.php','invoice-pdf.php','refund-receipt.php'],true)))$pageRobots='noindex,nofollow';
$canonicalUrl=$canonicalUrl??'';
if($canonicalUrl==='' && $rootPrefix==='' && seo_base_url()!=='')$canonicalUrl=seo_url($basename);
$referrerPolicy=$referrerPolicy??'';
$structuredData=$structuredData??[];
if(!is_array($structuredData))$structuredData=[];
if($basename==='index.php' && $rootPrefix==='' && seo_base_url()!==''){
    $structuredData[]=['@context'=>'https://schema.org','@type'=>'Organization','name'=>(string)config('company_name','Moleqra'),'url'=>seo_base_url(),'email'=>(string)config('contact_email','')];
    $structuredData[]=['@context'=>'https://schema.org','@type'=>'WebSite','name'=>(string)config('site_name','Moleqra'),'url'=>seo_base_url()];
}
?>
<!doctype html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if($pageRobots): ?><meta name="robots" content="<?= e($pageRobots) ?>"><?php endif; ?>
    <meta name="theme-color" content="#0b1020">
    <?php if($referrerPolicy): ?><meta name="referrer" content="<?= e($referrerPolicy) ?>"><?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <?php if($canonicalUrl): ?><meta property="og:url" content="<?= e($canonicalUrl) ?>"><link rel="canonical" href="<?= e($canonicalUrl) ?>"><?php endif; ?>
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e($rootPrefix) ?>assets/css/site.css">
    <link rel="stylesheet" href="<?= e($rootPrefix) ?>assets/css/commerce.css">
    <?php foreach($structuredData as $schema): ?><script type="application/ld+json"><?= seo_json($schema) ?></script><?php endforeach; ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="<?= e($rootPrefix) ?>index.php" aria-label="Moleqra home"><span class="brand-mark" aria-hidden="true">M</span><span class="brand-word">MOLEQRA</span></a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">Menu</button>
        <nav id="primary-nav" class="primary-nav" aria-label="Primary navigation">
            <a href="<?= e($rootPrefix) ?>catalog.php"<?= nav_active('catalog.php') ?>>Catalog</a>
            <a href="<?= e($rootPrefix) ?>quality.php"<?= nav_active('quality.php') ?>>Quality</a>
            <a href="<?= e($rootPrefix) ?>coa.php"<?= nav_active('coa.php') ?>>COA Library</a>
            <a href="<?= e($rootPrefix) ?>suppliers.php"<?= nav_active('suppliers.php') ?>>Suppliers</a>
            <a href="<?= e($rootPrefix) ?>about.php"<?= nav_active('about.php') ?>>About</a>
            <a href="<?= e($rootPrefix) ?>account/<?= !empty($_SESSION['customer_id']) ? 'index.php' : 'login.php' ?>">Account</a>
            <a href="<?= e($rootPrefix) ?>cart.php">Cart<?php if (commerce_cart_count() > 0): ?> <span class="cart-badge"><?= e((string)commerce_cart_count()) ?></span><?php endif; ?></a>
            <a class="nav-cta" href="<?= e($rootPrefix) ?>contact.php"<?= nav_active('contact.php') ?>>Contact</a>
        </nav>
    </div>
</header>
<main id="main">
