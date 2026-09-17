<?php
require_once __DIR__ . '/bootstrap.php';
$pageTitle = $pageTitle ?? config('site_name');
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
    <link rel="stylesheet" href="assets/css/site.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="index.php" aria-label="Moleqra home">
            <span class="brand-mark" aria-hidden="true">M</span>
            <span class="brand-word">MOLEQRA</span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">Menu</button>
        <nav id="primary-nav" class="primary-nav" aria-label="Primary navigation">
            <a href="catalog.php"<?= nav_active('catalog.php') ?>>Catalog</a>
            <a href="quality.php"<?= nav_active('quality.php') ?>>Quality</a>
            <a href="coa.php"<?= nav_active('coa.php') ?>>COA Library</a>
            <a href="suppliers.php"<?= nav_active('suppliers.php') ?>>Suppliers</a>
            <a href="about.php"<?= nav_active('about.php') ?>>About</a>
            <a class="nav-cta" href="contact.php"<?= nav_active('contact.php') ?>>Contact</a>
        </nav>
    </div>
</header>
<main id="main">
