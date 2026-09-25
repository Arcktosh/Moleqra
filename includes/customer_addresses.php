<?php

declare(strict_types=1);

require_once __DIR__ . '/account_auth.php';

function customer_addresses(PDO $pdo, int $customerId): array
{
    $stmt=$pdo->prepare('SELECT * FROM customer_addresses WHERE customer_id=:id ORDER BY is_default DESC,label,address_line1,id');
    $stmt->execute(['id'=>$customerId]);
    return $stmt->fetchAll();
}

function customer_default_address(PDO $pdo, int $customerId): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM customer_addresses WHERE customer_id=:id ORDER BY is_default DESC,id ASC LIMIT 1');
    $stmt->execute(['id'=>$customerId]);
    return $stmt->fetch() ?: null;
}

function customer_save_address(PDO $pdo, int $customerId, array $data, ?int $addressId = null): int
{
    foreach(['recipient_name','address_line1','city','province','postal_code'] as $field){
        if(trim((string)($data[$field]??''))==='') throw new RuntimeException('Please complete all required address fields.');
    }
    $makeDefault=!empty($data['is_default']);
    $pdo->beginTransaction();
    try{
        if($makeDefault)$pdo->prepare('UPDATE customer_addresses SET is_default=0 WHERE customer_id=:customer')->execute(['customer'=>$customerId]);
        $params=[
            'customer'=>$customerId,'label'=>trim((string)($data['label']??'Default'))?:'Default','recipient'=>trim((string)$data['recipient_name']),
            'company'=>trim((string)($data['company']??''))?:null,'line1'=>trim((string)$data['address_line1']),'line2'=>trim((string)($data['address_line2']??''))?:null,
            'city'=>trim((string)$data['city']),'province'=>trim((string)$data['province']),'postal'=>trim((string)$data['postal_code']),'country'=>strtoupper(trim((string)($data['country_code']??'ZA'))?:'ZA'),
            'phone'=>trim((string)($data['phone']??''))?:null,'default'=>$makeDefault?1:0,
        ];
        if($addressId){
            $params['id']=$addressId;
            $sql='UPDATE customer_addresses SET label=:label,recipient_name=:recipient,company=:company,address_line1=:line1,address_line2=:line2,city=:city,province=:province,postal_code=:postal,country_code=:country,phone=:phone,is_default=:default WHERE id=:id AND customer_id=:customer';
            $pdo->prepare($sql)->execute($params);
            $id=$addressId;
        }else{
            $sql='INSERT INTO customer_addresses(customer_id,label,recipient_name,company,address_line1,address_line2,city,province,postal_code,country_code,phone,is_default) VALUES(:customer,:label,:recipient,:company,:line1,:line2,:city,:province,:postal,:country,:phone,:default)';
            $pdo->prepare($sql)->execute($params);$id=(int)$pdo->lastInsertId();
        }
        if(!$makeDefault){
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM customer_addresses WHERE customer_id=:customer AND is_default=1');$stmt->execute(['customer'=>$customerId]);
            if((int)$stmt->fetchColumn()===0)$pdo->prepare('UPDATE customer_addresses SET is_default=1 WHERE id=:id AND customer_id=:customer')->execute(['id'=>$id,'customer'=>$customerId]);
        }
        $pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function customer_delete_address(PDO $pdo, int $customerId, int $addressId): void
{
    $stmt=$pdo->prepare('SELECT is_default FROM customer_addresses WHERE id=:id AND customer_id=:customer');$stmt->execute(['id'=>$addressId,'customer'=>$customerId]);$wasDefault=(bool)$stmt->fetchColumn();
    $pdo->prepare('DELETE FROM customer_addresses WHERE id=:id AND customer_id=:customer')->execute(['id'=>$addressId,'customer'=>$customerId]);
    if($wasDefault){$stmt=$pdo->prepare('SELECT id FROM customer_addresses WHERE customer_id=:customer ORDER BY id LIMIT 1');$stmt->execute(['customer'=>$customerId]);$next=(int)$stmt->fetchColumn();if($next)$pdo->prepare('UPDATE customer_addresses SET is_default=1 WHERE id=:id')->execute(['id'=>$next]);}
}

function customer_save_checkout_address(PDO $pdo, int $customerId, array $checkout): int
{
    return customer_save_address($pdo,$customerId,[
        'label'=>'Default','recipient_name'=>trim((string)$checkout['first_name'].' '.(string)$checkout['last_name']),'company'=>$checkout['company']??null,
        'address_line1'=>$checkout['address_line1']??'','address_line2'=>$checkout['address_line2']??'','city'=>$checkout['city']??'','province'=>$checkout['province']??'',
        'postal_code'=>$checkout['postal_code']??'','country_code'=>$checkout['country_code']??commerce_config('country_code','ZA'),'phone'=>$checkout['phone']??null,'is_default'=>1,
    ]);
}
