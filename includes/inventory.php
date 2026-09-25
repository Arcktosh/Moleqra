<?php

declare(strict_types=1);

require_once __DIR__ . '/procurement.php';

function inventory_tables(): array
{
    return ['purchase_orders', 'purchase_order_items', 'inventory_batches', 'inventory_release_checks', 'inventory_movements'];
}

function inventory_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= db();
    if (!$pdo || !procurement_schema_ready($pdo)) return false;
    foreach (inventory_tables() as $table) {
        if (!db_table_exists($table, $pdo)) return false;
    }
    return true;
}

function inventory_batch_statuses(): array
{
    return ['Quarantine', 'Released', 'Hold', 'Rejected', 'Depleted'];
}

function purchase_order_statuses(): array
{
    return ['Draft', 'Ordered', 'Partial', 'Received', 'Cancelled'];
}

function inventory_decimal(mixed $value): float
{
    if ($value === null || $value === '') return 0.0;
    return round((float)$value, 3);
}

function inventory_batch(PDO $pdo, int $batchId): ?array
{
    $stmt = $pdo->prepare('SELECT b.*, p.sku, p.name AS product_name, s.name AS supplier_name,
                                  c.batch_number AS coa_batch_number, c.lab_name AS coa_lab_name,
                                  po.po_number, poi.quantity_ordered
                           FROM inventory_batches b
                           JOIN products p ON p.id=b.product_id
                           JOIN suppliers s ON s.id=b.supplier_id
                           LEFT JOIN coa_documents c ON c.id=b.coa_document_id
                           LEFT JOIN purchase_order_items poi ON poi.id=b.purchase_order_item_id
                           LEFT JOIN purchase_orders po ON po.id=poi.purchase_order_id
                           WHERE b.id=:id LIMIT 1');
    $stmt->execute(['id' => $batchId]);
    return $stmt->fetch() ?: null;
}

function inventory_release_gate(PDO $pdo, int $batchId): array
{
    $batch = inventory_batch($pdo, $batchId);
    if (!$batch) return ['allowed' => false, 'blockers' => ['Inventory batch not found.']];

    $stmt = $pdo->prepare('SELECT * FROM inventory_release_checks WHERE batch_id=:id');
    $stmt->execute(['id' => $batchId]);
    $checks = $stmt->fetch() ?: [];
    $blockers = [];

    if (empty($batch['coa_document_id'])) {
        $blockers[] = 'No COA document is linked to this inventory batch.';
    } elseif ((string)$batch['coa_batch_number'] !== (string)$batch['batch_number']) {
        $blockers[] = 'The linked COA batch number does not exactly match the received batch number.';
    }

    $required = [
        'quantity_verified' => 'received quantity verification',
        'packaging_ok' => 'packaging/condition check',
        'coa_linked_verified' => 'COA linkage verification',
        'batch_coa_match' => 'batch-to-COA match check',
        'storage_ok' => 'storage condition/location check',
    ];
    foreach ($required as $field => $label) {
        if (empty($checks[$field])) $blockers[] = 'Release check incomplete: ' . $label . '.';
    }

    if (inventory_decimal($batch['units_on_hand']) <= 0) $blockers[] = 'The batch has no on-hand units.';
    return ['allowed' => !$blockers, 'blockers' => $blockers, 'batch' => $batch, 'checks' => $checks];
}

function inventory_receive_batch(PDO $pdo, array $data, ?int $adminId = null): int
{
    $productId = (int)($data['product_id'] ?? 0);
    $supplierId = (int)($data['supplier_id'] ?? 0);
    $poItemId = (int)($data['purchase_order_item_id'] ?? 0) ?: null;
    $coaId = (int)($data['coa_document_id'] ?? 0) ?: null;
    $batchNumber = trim((string)($data['batch_number'] ?? ''));
    $receivedAt = trim((string)($data['received_at'] ?? '')) ?: date('Y-m-d');
    $qty = inventory_decimal($data['units_received'] ?? 0);
    if ($productId < 1 || $supplierId < 1 || $batchNumber === '' || $qty <= 0) {
        throw new RuntimeException('Product, supplier, batch number and a positive received quantity are required.');
    }

    if ($coaId) {
        $stmt = $pdo->prepare('SELECT product_id FROM coa_documents WHERE id=:id');
        $stmt->execute(['id' => $coaId]);
        if ((int)$stmt->fetchColumn() !== $productId) throw new RuntimeException('The selected COA belongs to a different product.');
    }

    $pdo->beginTransaction();
    try {
        if ($poItemId) {
            $stmt = $pdo->prepare('SELECT poi.*, po.supplier_id FROM purchase_order_items poi JOIN purchase_orders po ON po.id=poi.purchase_order_id WHERE poi.id=:id FOR UPDATE');
            $stmt->execute(['id' => $poItemId]);
            $item = $stmt->fetch();
            if (!$item || (int)$item['product_id'] !== $productId || (int)$item['supplier_id'] !== $supplierId) {
                throw new RuntimeException('The selected PO line does not match the product and supplier.');
            }
            $remaining = inventory_decimal($item['quantity_ordered']) - inventory_decimal($item['quantity_received']);
            if ($qty - $remaining > 0.0005) {
                throw new RuntimeException('Received quantity exceeds the remaining quantity on this PO line. Adjust the PO line first if the supplier shipped an approved overage.');
            }
        }

        $stmt = $pdo->prepare('INSERT INTO inventory_batches
            (product_id,supplier_id,purchase_order_item_id,coa_document_id,batch_number,received_at,expiry_date,retest_date,status,units_received,units_on_hand,unit_cost_base,storage_location,notes)
            VALUES (:product_id,:supplier_id,:po_item,:coa,:batch,:received,:expiry,:retest,\'Quarantine\',:received_qty,:on_hand,:unit_cost,:storage,:notes)');
        $stmt->execute([
            'product_id' => $productId, 'supplier_id' => $supplierId, 'po_item' => $poItemId, 'coa' => $coaId,
            'batch' => $batchNumber, 'received' => $receivedAt,
            'expiry' => trim((string)($data['expiry_date'] ?? '')) ?: null,
            'retest' => trim((string)($data['retest_date'] ?? '')) ?: null,
            'received_qty' => $qty, 'on_hand' => $qty,
            'unit_cost' => ($data['unit_cost_base'] ?? '') === '' ? null : max(0, (float)$data['unit_cost_base']),
            'storage' => trim((string)($data['storage_location'] ?? '')) ?: null,
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
        ]);
        $batchId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO inventory_movements (batch_id,movement_type,quantity_delta,reference,reason,created_by) VALUES (:batch,\'Receipt\',:qty,:ref,:reason,:admin)');
        $stmt->execute(['batch' => $batchId, 'qty' => $qty, 'ref' => $data['reference'] ?? null, 'reason' => 'Initial receipt into quarantine', 'admin' => $adminId]);

        if ($poItemId) {
            $stmt = $pdo->prepare('UPDATE purchase_order_items SET quantity_received=quantity_received+:qty WHERE id=:id');
            $stmt->execute(['qty' => $qty, 'id' => $poItemId]);
            inventory_refresh_purchase_order_status($pdo, $poItemId);
        }
        $pdo->commit();
        return $batchId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function inventory_refresh_purchase_order_status(PDO $pdo, int $poItemId): void
{
    $stmt = $pdo->prepare('SELECT purchase_order_id FROM purchase_order_items WHERE id=:id');
    $stmt->execute(['id' => $poItemId]);
    $poId = (int)$stmt->fetchColumn();
    if ($poId < 1) return;

    $stmt = $pdo->prepare('SELECT SUM(quantity_ordered) ordered_qty, SUM(quantity_received) received_qty FROM purchase_order_items WHERE purchase_order_id=:id');
    $stmt->execute(['id' => $poId]);
    $totals = $stmt->fetch() ?: ['ordered_qty' => 0, 'received_qty' => 0];
    $ordered = inventory_decimal($totals['ordered_qty']);
    $received = inventory_decimal($totals['received_qty']);
    $status = $received <= 0 ? 'Ordered' : ($received + 0.0005 >= $ordered && $ordered > 0 ? 'Received' : 'Partial');
    $pdo->prepare('UPDATE purchase_orders SET status=:status WHERE id=:id AND status<>\'Cancelled\'')->execute(['status' => $status, 'id' => $poId]);
}

function inventory_apply_movement(PDO $pdo, int $batchId, string $type, float $delta, string $reason, ?string $reference, ?int $adminId): float
{
    $allowedTypes = ['Adjustment', 'Sample', 'Write-off', 'Return', 'Correction'];
    if (!in_array($type, $allowedTypes, true)) throw new RuntimeException('Invalid movement type.');
    $delta = round($delta, 3);
    if (abs($delta) < 0.0005) throw new RuntimeException('Movement quantity cannot be zero.');
    if (trim($reason) === '') throw new RuntimeException('A reason is required for every stock movement.');
    if (in_array($type, ['Sample', 'Write-off', 'Return'], true)) $delta = -abs($delta);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT units_on_hand,status FROM inventory_batches WHERE id=:id FOR UPDATE');
        $stmt->execute(['id' => $batchId]);
        $batch = $stmt->fetch();
        if (!$batch) throw new RuntimeException('Inventory batch not found.');
        $newQty = round(inventory_decimal($batch['units_on_hand']) + $delta, 3);
        if ($newQty < -0.0005) throw new RuntimeException('This movement would make stock negative.');
        if ($newQty < 0) $newQty = 0;

        $pdo->prepare('UPDATE inventory_batches SET units_on_hand=:qty, status=CASE WHEN :qty_zero=1 AND status=\'Released\' THEN \'Depleted\' WHEN :qty_positive=1 AND status=\'Depleted\' THEN \'Hold\' ELSE status END WHERE id=:id')
            ->execute(['qty' => $newQty, 'qty_zero' => $newQty <= 0 ? 1 : 0, 'qty_positive' => $newQty > 0 ? 1 : 0, 'id' => $batchId]);
        $pdo->prepare('INSERT INTO inventory_movements (batch_id,movement_type,quantity_delta,reference,reason,created_by) VALUES (:batch,:type,:delta,:reference,:reason,:admin)')
            ->execute(['batch' => $batchId, 'type' => $type, 'delta' => $delta, 'reference' => $reference ?: null, 'reason' => $reason, 'admin' => $adminId]);
        $pdo->commit();
        return $newQty;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function inventory_set_status(PDO $pdo, int $batchId, string $status, ?int $adminId): void
{
    if (!in_array($status, inventory_batch_statuses(), true)) throw new RuntimeException('Invalid batch status.');
    $batch = inventory_batch($pdo, $batchId);
    if (!$batch) throw new RuntimeException('Inventory batch not found.');
    $from = (string)$batch['status'];
    if ($status === 'Released') {
        if (!in_array($from, ['Quarantine', 'Hold', 'Released'], true)) throw new RuntimeException('Only Quarantine or Hold stock can be internally released.');
        $gate = inventory_release_gate($pdo, $batchId);
        if (!$gate['allowed']) throw new RuntimeException('Release blocked: ' . implode(' ', $gate['blockers']));
    }
    if ($status === 'Depleted' && inventory_decimal($batch['units_on_hand']) > 0) {
        throw new RuntimeException('A batch with on-hand units cannot be marked Depleted.');
    }
    if ($from === $status) return;

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE inventory_batches SET status=:status WHERE id=:id')->execute(['status' => $status, 'id' => $batchId]);
        $pdo->prepare('INSERT INTO inventory_movements (batch_id,movement_type,quantity_delta,reference,reason,created_by) VALUES (:batch,\'Status\',0,NULL,:reason,:admin)')
            ->execute(['batch' => $batchId, 'reason' => 'Disposition changed from ' . $from . ' to ' . $status, 'admin' => $adminId]);
        if ($status === 'Released') {
            $pdo->prepare('UPDATE inventory_release_checks SET released_by=:admin,released_at=NOW() WHERE batch_id=:batch')
                ->execute(['admin' => $adminId, 'batch' => $batchId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
