<?php

declare(strict_types=1);

require_once __DIR__ . '/commerce.php';

function commercial_ops_tables(): array
{
    return ['commerce_notifications','sales_invoices','shipping_rules','order_shipping_quotes','order_refunds','payment_reconciliations'];
}

function commercial_ops_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();if(!$pdo||!commerce_schema_ready($pdo))return false;foreach(commercial_ops_tables() as $table)if(!db_table_exists($table,$pdo))return false;return true;
}
