<?php
require_once __DIR__ . '/bootstrap.php';
$pageTitle = $pageTitle ?? config('site_name');
$rootPrefix = $rootPrefix ?? '';
require_once __DIR__ . '/commerce.php';
$pageDescription = $pageDescription ?? 'Moleqra provides research-use-only peptide materials with batch documentation and transparent quality controls.';
?>
<!doctype html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#0b1020">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e($rootPrefix) ?>assets/css/site.css">
    <link rel="stylesheet" href="<?= e($rootPrefix) ?>assets/css/commerce.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="<?= e($rootPrefix) ?>index.php" aria-label="Moleqra home">
            <span class="brand-mark" aria-hidden="true">M</span>
            <span class="brand-word">MOLEQRA</span>
        </a>
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
