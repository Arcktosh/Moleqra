<?php
require_once __DIR__.'/../includes/bootstrap.php';$token=(string)($_GET['token']??'');header('Location: ../order.php?token='.rawurlencode($token));exit;
