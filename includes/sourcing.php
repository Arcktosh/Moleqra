<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function sourcing_tables(): array
{
    return [
        'supplier_qualifications',
        'supplier_products',
        'supplier_outreach',
        'supplier_test_orders',
    ];
}

function sourcing_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= db();
    if (!$pdo) {
        return false;
    }
    foreach (sourcing_tables() as $table) {
        if (!db_table_exists($table, $pdo)) {
            return false;
        }
    }
    return true;
}

function sourcing_control_labels(): array
{
    return [
        'business_verified' => 'Business identity verified',
        'sample_coa_received' => 'Recent sample COA received',
        'batch_specific_coa' => 'COA is batch-specific',
        'hplc_present' => 'HPLC evidence present',
        'mass_spec_present' => 'Mass-spec evidence present',
        'coa_identity_matches' => 'COA identity matches offered material',
        'ruo_label_confirmed' => 'Research-use-only labelling confirmed',
        'private_label_confirmed' => 'Private-label terms confirmed',
        'shipping_confirmed' => 'Shipping/fulfilment process confirmed',
        'payment_terms_confirmed' => 'Commercial/payment terms confirmed',
        'returns_process_confirmed' => 'Returns/non-conformance process confirmed',
    ];
}

function sourcing_readiness(?array $qualification): array
{
    $controls = sourcing_control_labels();
    $complete = 0;
    foreach ($controls as $field => $_label) {
        if (!empty($qualification[$field])) {
            $complete++;
        }
    }
    $total = count($controls);
    $percent = $total > 0 ? (int) round(($complete / $total) * 100) : 0;

    return [
        'complete' => $complete,
        'total' => $total,
        'percent' => $percent,
    ];
}

function sourcing_readiness_label(int $percent): string
{
    if ($percent >= 100) return 'Controls complete';
    if ($percent >= 70) return 'Advanced review';
    if ($percent >= 35) return 'In review';
    return 'Early review';
}

function sourcing_currency(string $value): string
{
    $value = strtoupper(trim($value));
    return preg_match('/^[A-Z]{3}$/', $value) ? $value : 'ZAR';
}

function sourcing_money(mixed $amount, string $currency): string
{
    if ($amount === null || $amount === '') return '—';
    return e(sourcing_currency($currency)) . ' ' . number_format((float)$amount, 2);
}
