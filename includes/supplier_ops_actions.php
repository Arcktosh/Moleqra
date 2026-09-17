<?php

declare(strict_types=1);

function supplier_ops_handle_post(PDO $pdo, array $supplier, int $id): array
{
    $error = '';
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return compact('error','message');
    if (!csrf_valid($_POST['csrf_token'] ?? null)) return ['error'=>'Your session expired. Please try again.','message'=>''];

    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'qualification') {
            $fields = array_keys(sourcing_control_labels()); $values = [];
            foreach ($fields as $field) $values[$field] = isset($_POST[$field]) ? 1 : 0;
            $notes = trim((string)($_POST['qualification_notes'] ?? ''));
            $reviewed = trim((string)($_POST['reviewed_at'] ?? '')) ?: null;
            $columns = implode(',', $fields);
            $placeholders = implode(',', array_map(fn($f) => ':' . $f, $fields));
            $updates = implode(',', array_map(fn($f) => $f . '=VALUES(' . $f . ')', $fields));
            $sql = "INSERT INTO supplier_qualifications (supplier_id,$columns,qualification_notes,reviewed_at) VALUES (:supplier_id,$placeholders,:notes,:reviewed) ON DUPLICATE KEY UPDATE $updates,qualification_notes=VALUES(qualification_notes),reviewed_at=VALUES(reviewed_at)";
            $pdo->prepare($sql)->execute($values + ['supplier_id'=>$id,'notes'=>$notes ?: null,'reviewed'=>$reviewed]);
            $message = 'Qualification controls updated.';
        } elseif ($action === 'outreach') {
            $channel = trim((string)($_POST['channel'] ?? 'Email'));
            $subject = trim((string)($_POST['subject'] ?? ''));
            $notes = trim((string)($_POST['outreach_notes'] ?? ''));
            $outcome = trim((string)($_POST['outcome'] ?? 'Awaiting response'));
            $raw = trim((string)($_POST['contacted_at'] ?? ''));
            $contacted = $raw !== '' ? str_replace('T',' ',$raw) : date('Y-m-d H:i:s');
            if (strlen($contacted) === 16) $contacted .= ':00';
            $follow = trim((string)($_POST['next_follow_up'] ?? '')) ?: null;
            if ($notes === '') throw new RuntimeException('Outreach notes are required.');
            $u = admin_user();
            $stmt = $pdo->prepare('INSERT INTO supplier_outreach (supplier_id,contacted_at,channel,subject,notes,outcome,next_follow_up,created_by) VALUES (:supplier_id,:contacted,:channel,:subject,:notes,:outcome,:followup,:created_by)');
            $stmt->execute(['supplier_id'=>$id,'contacted'=>$contacted,'channel'=>$channel,'subject'=>$subject ?: null,'notes'=>$notes,'outcome'=>$outcome,'followup'=>$follow,'created_by'=>$u['id'] ?? null]);
            if ($supplier['status'] === 'Prospect') $pdo->prepare("UPDATE suppliers SET status='Contacted' WHERE id=:id")->execute(['id'=>$id]);
            $message = 'Outreach entry added.';
        } elseif ($action === 'offer') {
            $productId = (int)($_POST['product_id'] ?? 0);
            if ($productId < 1) throw new RuntimeException('Choose a product.');
            $supplierSku = trim((string)($_POST['supplier_sku'] ?? ''));
            $availability = trim((string)($_POST['availability'] ?? 'Unknown'));
            $price = trim((string)($_POST['wholesale_price'] ?? ''));
            $currency = sourcing_currency((string)($_POST['currency'] ?? 'ZAR'));
            $moqUnits = trim((string)($_POST['moq_units'] ?? ''));
            $moqValue = trim((string)($_POST['moq_value'] ?? ''));
            $lead = trim((string)($_POST['lead_time_days'] ?? ''));
            $notes = trim((string)($_POST['offer_notes'] ?? ''));
            $sql = 'INSERT INTO supplier_products (supplier_id,product_id,supplier_sku,availability,wholesale_price,currency,moq_units,moq_value,lead_time_days,coa_available,private_label,dropship,notes) VALUES (:supplier_id,:product_id,:supplier_sku,:availability,:price,:currency,:moq_units,:moq_value,:lead,:coa,:private_label,:dropship,:notes) ON DUPLICATE KEY UPDATE supplier_sku=VALUES(supplier_sku),availability=VALUES(availability),wholesale_price=VALUES(wholesale_price),currency=VALUES(currency),moq_units=VALUES(moq_units),moq_value=VALUES(moq_value),lead_time_days=VALUES(lead_time_days),coa_available=VALUES(coa_available),private_label=VALUES(private_label),dropship=VALUES(dropship),notes=VALUES(notes)';
            $pdo->prepare($sql)->execute(['supplier_id'=>$id,'product_id'=>$productId,'supplier_sku'=>$supplierSku ?: null,'availability'=>$availability,'price'=>$price===''?null:(float)$price,'currency'=>$currency,'moq_units'=>$moqUnits===''?null:(int)$moqUnits,'moq_value'=>$moqValue===''?null:(float)$moqValue,'lead'=>$lead===''?null:(int)$lead,'coa'=>isset($_POST['coa_available'])?1:0,'private_label'=>isset($_POST['private_label'])?1:0,'dropship'=>isset($_POST['dropship'])?1:0,'notes'=>$notes ?: null]);
            $message = 'Supplier-product offer saved.';
        } elseif ($action === 'test_order') {
            $ordered = trim((string)($_POST['ordered_at'] ?? '')) ?: date('Y-m-d');
            $received = trim((string)($_POST['received_at'] ?? '')) ?: null;
            $amount = trim((string)($_POST['amount'] ?? ''));
            $currency = sourcing_currency((string)($_POST['currency'] ?? 'ZAR'));
            $pack = trim((string)($_POST['packaging_condition'] ?? ''));
            $packValue = $pack === '' ? null : max(1,min(5,(int)$pack));
            $stmt = $pdo->prepare('INSERT INTO supplier_test_orders (supplier_id,order_ref,ordered_at,received_at,amount,currency,status,courier,tracking_ref,packaging_condition,documentation_complete,batch_matches_coa,notes) VALUES (:supplier_id,:order_ref,:ordered,:received,:amount,:currency,:status,:courier,:tracking,:packaging,:docs,:batch_match,:notes)');
            $stmt->execute(['supplier_id'=>$id,'order_ref'=>trim((string)($_POST['order_ref']??'')) ?: null,'ordered'=>$ordered,'received'=>$received,'amount'=>$amount===''?null:(float)$amount,'currency'=>$currency,'status'=>trim((string)($_POST['test_status']??'Planned')),'courier'=>trim((string)($_POST['courier']??'')) ?: null,'tracking'=>trim((string)($_POST['tracking_ref']??'')) ?: null,'packaging'=>$packValue,'docs'=>isset($_POST['documentation_complete'])?1:0,'batch_match'=>isset($_POST['batch_matches_coa'])?1:0,'notes'=>trim((string)($_POST['test_notes']??'')) ?: null]);
            $message = 'Test order added.';
        }
    } catch (Throwable $e) {
        error_log('Moleqra supplier operation failed: ' . $e->getMessage());
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'The supplier operation could not be saved.';
    }
    return compact('error','message');
}
