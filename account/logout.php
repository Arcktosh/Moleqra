<?php
require_once __DIR__.'/../includes/bootstrap.php';require_once __DIR__.'/../includes/account_auth.php';if($_SERVER['REQUEST_METHOD']==='POST'&&csrf_valid($_POST['csrf_token']??null))customer_logout();header('Location: ../index.php');exit;
