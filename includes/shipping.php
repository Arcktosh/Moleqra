<?php

declare(strict_types=1);

require_once __DIR__ . '/commerce_core.php';

function shipping_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= db();
    return $pdo && db_table_exists('shipping_rules', $pdo) && db_table_exists('order_shipping_quotes', $pdo);
}

function shipping_quote(PDO $pdo, float $orderValue, string $province = '', string $countryCode = 'ZA'): array
{
    $countryCode = strtoupper(trim($countryCode ?: 'ZA'));
    $province = trim($province);
    if (shipping_schema_ready($pdo)) {
        $stmt = $pdo->prepare("SELECT * FROM shipping_rules
            WHERE is_active=1 AND country_code=:country
              AND (province IS NULL OR province='' OR LOWER(province)=LOWER(:province))
              AND min_order<=:order1
              AND (max_order IS NULL OR max_order>=:order2)
            ORDER BY CASE WHEN province IS NULL OR province='' THEN 1 ELSE 0 END, priority ASC, id ASC
            LIMIT 1");
        $stmt->execute(['country'=>$countryCode,'province'=>$province,'order1'=>$orderValue,'order2'=>$orderValue]);
        $rule = $stmt->fetch();
        if ($rule) {
            $rate = (float)$rule['rate'];
            $freeThreshold = $rule['free_threshold'] !== null ? (float)$rule['free_threshold'] : null;
            if ($freeThreshold !== null && $orderValue >= $freeThreshold) $rate = 0.0;
            return [
                'rule_id'=>(int)$rule['id'],
                'rule_name'=>(string)$rule['name'],
                'amount'=>round(max(0,$rate),2),
                'country_code'=>$countryCode,
                'province'=>$province,
                'estimated'=>false,
            ];
        }
    }
    $fallback=(float)commerce_config('flat_shipping_rate',0);
    $free=(float)commerce_config('free_shipping_threshold',0);
    if($free>0 && $orderValue>=$free)$fallback=0.0;
    return ['rule_id'=>null,'rule_name'=>'Standard shipping','amount'=>round(max(0,$fallback),2),'country_code'=>$countryCode,'province'=>$province,'estimated'=>$province===''];
}

function shipping_rules(PDO $pdo): array
{
    if (!shipping_schema_ready($pdo)) return [];
    return $pdo->query('SELECT * FROM shipping_rules ORDER BY priority,id')->fetchAll();
}
