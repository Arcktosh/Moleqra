<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Contact | Moleqra';
$pageDescription = 'Contact Moleqra regarding supplier partnerships, research catalogue enquiries and business matters.';
$errors = [];
$success = false;
$topic = $_GET['topic'] ?? $_POST['topic'] ?? 'general';
$allowedTopics = ['general', 'supplier', 'research'];
if (!in_array($topic, $allowedTopics, true)) {
    $topic = 'general';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $company = trim((string)($_POST['company'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $website = trim((string)($_POST['website'] ?? ''));

    if (!csrf_valid($_POST['csrf_token'] ?? null)) $errors[] = 'Your form session expired. Please try again.';
    if ($website !== '') $errors[] = 'Unable to submit this enquiry.';
    if (text_length($name) < 2 || text_length($name) > 100) $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 180) $errors[] = 'Please enter a valid email address.';
    if (text_length($company) > 150) $errors[] = 'Company name is too long.';
    if (text_length($message) < 20 || text_length($message) > 5000) $errors[] = 'Please provide between 20 and 5,000 characters.';

    $lastSubmit = (int)($_SESSION['last_enquiry_at'] ?? 0);
    if ($lastSubmit > 0 && (time() - $lastSubmit) < 20) $errors[] = 'Please wait before submitting another enquiry.';

    if (!$errors) {
        $record = [
            'submitted_at' => gmdate('c'),
            'topic' => $topic,
            'name' => $name,
            'email' => $email,
            'company' => $company,
            'message' => $message,
            'ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
        ];
        $saved = false;
        $pdo = db();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('INSERT INTO enquiries (topic,name,email,company,message,ip_hash) VALUES (:topic,:name,:email,:company,:message,:ip_hash)');
                $saved = $stmt->execute([
                    'topic' => $topic,
                    'name' => $name,
                    'email' => $email,
                    'company' => $company ?: null,
                    'message' => $message,
                    'ip_hash' => $record['ip_hash'],
                ]);
            } catch (Throwable $e) {
                error_log('Moleqra enquiry database save failed: ' . $e->getMessage());
            }
        }
        if (!$saved) {
            $storage = __DIR__ . '/storage/enquiries.jsonl';
            $saved = @file_put_contents($storage, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
        }
        if (!$saved) {
            $errors[] = 'We could not store your enquiry. Please contact Moleqra directly.';
        } else {
            $_SESSION['last_enquiry_at'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $success = true;
            $_POST = [];
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Contact</div><h1>Start a conversation.</h1><p>Use this form for supplier partnerships, business enquiries or research catalogue questions. Please do not submit requests for dosing, treatment, self-administration or other human-use guidance.</p></div></section>
<section class="section"><div class="container grid-2">
    <div>
        <h2>Enquiry details</h2>
        <p class="muted">Supplier enquiries should include your company name, fulfilment location, MOQ, wholesale terms and the types of batch documentation you can provide.</p>
        <div class="card" style="margin-top:1.4rem"><h3>Launch status</h3><p>Moleqra is currently qualifying suppliers and is not yet accepting commercial product orders.</p></div>
    </div>
    <div class="card">
        <?php if ($success): ?><div class="alert success">Thank you. Your enquiry has been recorded.</div><?php endif; ?>
        <?php if ($errors): ?><div class="alert error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <form method="post" action="contact.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div style="position:absolute;left:-10000px" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="form-grid">
                <div class="field"><label for="name">Name</label><input id="name" name="name" maxlength="100" required value="<?= e($_POST['name'] ?? '') ?>"></div>
                <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="180" required value="<?= e($_POST['email'] ?? '') ?>"></div>
                <div class="field"><label for="company">Company</label><input id="company" name="company" maxlength="150" value="<?= e($_POST['company'] ?? '') ?>"></div>
                <div class="field"><label for="topic">Topic</label><select id="topic" name="topic"><option value="general"<?= $topic === 'general' ? ' selected' : '' ?>>General business</option><option value="supplier"<?= $topic === 'supplier' ? ' selected' : '' ?>>Supplier partnership</option><option value="research"<?= $topic === 'research' ? ' selected' : '' ?>>Research catalogue</option></select></div>
                <div class="field full"><label for="message">Message</label><textarea id="message" name="message" maxlength="5000" required><?= e($_POST['message'] ?? '') ?></textarea></div>
                <div class="field full"><button class="btn primary" type="submit">Submit enquiry</button><span class="form-note small">By submitting, you agree that Moleqra may use the information to respond to your enquiry.</span></div>
            </div>
        </form>
    </div>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
