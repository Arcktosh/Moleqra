<?php
require_once __DIR__ . '/../includes/auth.php';
if (admin_logged_in()) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (admin_login($email, $password)) {
        header('Location: index.php'); exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html><html lang="en-ZA"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin login | Moleqra</title><link rel="stylesheet" href="../assets/css/site.css"><link rel="stylesheet" href="../assets/css/admin.css"></head>
<body class="admin-login-body"><main class="admin-login-card"><a class="brand" href="../index.php"><span class="brand-mark">M</span><span class="brand-word">MOLEQRA</span></a><h1>Admin sign in</h1>
<?php if (!db_ready()): ?><div class="alert error">Database is not configured. Copy <code>config/database.example.php</code> to <code>config/database.php</code>, update credentials, then import <code>database/schema.sql</code>.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="field"><label for="email">Email</label><input id="email" type="email" name="email" autocomplete="username" required></div><div class="field"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required></div><button class="btn primary" type="submit">Sign in</button></form>
<p class="muted small">First deployment? <a href="setup.php">Create the first administrator</a>.</p></main></body></html>
