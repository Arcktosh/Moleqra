<?php

declare(strict_types=1);

require_once __DIR__ . '/sourcing.php';

function procurement_tables(): array
{
    return ['procurement_scenarios', 'product_market_reviews', 'procurement_decisions'];
}

function procurement_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= db();
    if (!$pdo || !sourcing_schema_ready($pdo)) return false;
    foreach (procurement_tables() as $table) {
        if (!db_table_exists($table, $pdo)) return false;
    }
    return true;
}

function procurement_base_currency(): string
{
    return sourcing_currency((string) config('base_currency', 'ZAR'));
}

function procurement_primary_market(): string
{
    $value = strtoupper(trim((string) config('primary_market', 'ZA')));
    return preg_match('/^[A-Z0-9_-]{2,12}$/', $value) ? $value : 'ZA';
}

function procurement_decimal(mixed $value): float
{
    if ($value === null || $value === '') return 0.0;
    return max(0.0, (float) $value);
}

function procurement_calculate(array $scenario): array
{
    $quantity = max(1, (int)($scenario['quantity'] ?? 1));
    $unitPrice = procurement_decimal($scenario['unit_price'] ?? 0);
    $fx = max(0.000001, (float)($scenario['fx_to_base'] ?? 1));
    $goodsQuote = $quantity * $unitPrice;
    $goodsBase = $goodsQuote * $fx;
    $extrasBase = 0.0;
    foreach (['shipping_cost','duty_tax_cost','lab_testing_cost','packaging_label_cost','payment_fee_cost','other_cost'] as $field) {
        $extrasBase += procurement_decimal($scenario[$field] ?? 0) * $fx;
    }
    $landedTotal = $goodsBase + $extrasBase;
    return [
        'quantity' => $quantity,
        'goods_quote' => $goodsQuote,
        'goods_base' => $goodsBase,
        'extras_base' => $extrasBase,
        'landed_total_base' => $landedTotal,
        'landed_unit_base' => $landedTotal / $quantity,
    ];
}

function procurement_money_base(float $amount): string
{
    return procurement_base_currency() . ' ' . number_format($amount, 2);
}

function procurement_market_review_statuses(): array
{
    return ['Review required', 'External review required', 'Internal review complete', 'Not in scope'];
}

function procurement_decision_statuses(): array
{
    return ['Evaluating', 'Test order', 'Approved sourcing', 'Hold', 'Rejected'];
}

function product_publication_gate(PDO $pdo, int $productId, ?string $marketCode = null): array
{
    if (!procurement_schema_ready($pdo)) {
        return ['allowed' => true, 'blockers' => [], 'market' => $marketCode ?: procurement_primary_market()];
    }

    $market = $marketCode ?: procurement_primary_market();
    $blockers = [];

    $stmt = $pdo->prepare('SELECT * FROM product_market_reviews WHERE product_id=:product_id AND market_code=:market LIMIT 1');
    $stmt->execute(['product_id' => $productId, 'market' => $market]);
    $review = $stmt->fetch() ?: null;
    if (!$review) {
        $blockers[] = 'No market/compliance review record exists for ' . $market . '.';
    } else {
        if (!empty($review['listing_hold'])) $blockers[] = 'The market review has an active public-listing hold.';
        if (($review['review_status'] ?? '') !== 'Internal review complete') $blockers[] = 'The market review is not marked Internal review complete.';
    }

    $stmt = $pdo->prepare('SELECT d.*, sp.supplier_id, sp.coa_available, s.status AS supplier_status
                           FROM procurement_decisions d
                           LEFT JOIN supplier_products sp ON sp.id=d.preferred_supplier_product_id
                           LEFT JOIN suppliers s ON s.id=sp.supplier_id
                           WHERE d.product_id=:product_id AND d.market_code=:market LIMIT 1');
    $stmt->execute(['product_id' => $productId, 'market' => $market]);
    $decision = $stmt->fetch() ?: null;
    if (!$decision || ($decision['decision_status'] ?? '') !== 'Approved sourcing') {
        $blockers[] = 'No Approved sourcing decision exists for this market.';
    } else {
        if (empty($decision['preferred_supplier_product_id'])) $blockers[] = 'The sourcing decision has no selected supplier offer.';
        if (($decision['supplier_status'] ?? '') !== 'Qualified') $blockers[] = 'The selected supplier is not marked Qualified.';
        if (empty($decision['coa_available'])) $blockers[] = 'The selected supplier offer is not marked as having a COA available.';
        if (!empty($decision['supplier_id'])) {
            $q = $pdo->prepare('SELECT * FROM supplier_qualifications WHERE supplier_id=:id');
            $q->execute(['id' => (int)$decision['supplier_id']]);
            $qualification = $q->fetch() ?: null;
            if (!$qualification) {
                $blockers[] = 'The selected supplier has no qualification evidence record.';
            } else {
                $requiredControls = [
                    'business_verified' => 'business identity',
                    'batch_specific_coa' => 'batch-specific COA',
                    'coa_identity_matches' => 'COA identity match',
                    'ruo_label_confirmed' => 'research-use-only labelling',
                    'shipping_confirmed' => 'shipping/fulfilment process',
                ];
                foreach ($requiredControls as $field => $label) {
                    if (empty($qualification[$field])) $blockers[] = 'Supplier qualification is missing: ' . $label . '.';
                }
            }
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM coa_documents WHERE product_id=:product_id AND is_public=1');
    $stmt->execute(['product_id' => $productId]);
    if ((int)$stmt->fetchColumn() < 1) $blockers[] = 'No public batch COA is linked to this product.';

    return ['allowed' => count($blockers) === 0, 'blockers' => $blockers, 'market' => $market];
}
