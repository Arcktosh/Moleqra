<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = db();
$error = '';
$success = false;
$hasAdmin = false;
if ($pdo) {
    try { $hasAdmin = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0; } catch (Throwable $e) { $error = 'Database schema is not installed. Import database/schema.sql first.'; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && !$hasAdmin && !$error) {
    $name = trim((string)($_POST['display_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!csrf_valid($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Please try again.';
    elseif (text_length($name) < 2 || text_length($name) > 100) $error = 'Enter a valid display name.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    elseif (strlen($password) < 12) $error = 'Use a password of at least 12 characters.';
    else {
        $stmt = $pdo->prepare('INSERT INTO admin_users (email, password_hash, display_name) VALUES (:email,:hash,:name)');
        $stmt->execute(['email'=>$email,'hash'=>password_hash($password, PASSWORD_DEFAULT),'name'=>$name]);
        $success = true; $hasAdmin = true;
    }
}
?>
<!doctype html><html lang="en-ZA"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin setup | Moleqra</title><link rel="stylesheet" href="../assets/css/site.css"><link rel="stylesheet" href="../assets/css/admin.css"></head><body class="admin-login-body"><main class="admin-login-card"><a class="brand" href="../index.php"><span class="brand-mark">M</span><span class="brand-word">MOLEQRA</span></a><h1>Admin setup</h1>
<?php if (!$pdo): ?><div class="alert error">Configure MySQL first using <code>config/database.example.php</code>.</div><?php elseif ($success): ?><div class="alert success">Administrator created. <a href="login.php">Sign in now</a>.</div><?php elseif ($hasAdmin): ?><div class="notice">Setup is locked because an administrator already exists. <a href="login.php">Go to sign in</a>.</div><?php elseif ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if ($pdo && !$hasAdmin && !$error): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="field"><label>Display name</label><input name="display_name" maxlength="100" required></div><div class="field"><label>Email</label><input type="email" name="email" maxlength="180" required></div><div class="field"><label>Password</label><input type="password" name="password" minlength="12" autocomplete="new-password" required></div><button class="btn primary" type="submit">Create administrator</button></form><?php endif; ?>
</main></body></html>
