<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$adminTitle = $adminTitle ?? 'Admin';
$current = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$user = admin_user();
?>
<!doctype html>
<html lang="en-ZA">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($adminTitle) ?> | Moleqra Admin</title>
<link rel="stylesheet" href="../assets/css/site.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">
<header class="admin-topbar">
  <a class="brand" href="index.php"><span class="brand-mark">M</span><span class="brand-word">MOLEQRA ADMIN</span></a>
  <div class="admin-user"><span><?= e($user['display_name'] ?? '') ?></span><a href="../index.php" target="_blank" rel="noopener">View site</a></div>
</header>
<div class="admin-shell">
<aside class="admin-sidebar" aria-label="Admin navigation">
  <a class="<?= $current === 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a>
  <a class="<?= $current === 'products.php' ? 'active' : '' ?>" href="products.php">Products</a>
  <a class="<?= $current === 'suppliers.php' ? 'active' : '' ?>" href="suppliers.php">Suppliers</a>
  <a class="<?= $current === 'coa.php' ? 'active' : '' ?>" href="coa.php">COAs</a>
  <a class="<?= $current === 'enquiries.php' ? 'active' : '' ?>" href="enquiries.php">Enquiries</a>
  <form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button type="submit" class="admin-link-button">Sign out</button></form>
</aside>
<main class="admin-main">
