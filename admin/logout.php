<?php
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid($_POST['csrf_token'] ?? null)) admin_logout();
header('Location: login.php');
exit;
