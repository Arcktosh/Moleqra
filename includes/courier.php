<?php

declare(strict_types=1);

function courier_config(): array
{
    static $cfg=null;if($cfg!==null)return $cfg;$file=__DIR__.'/../config/courier.php';$cfg=is_file($file)?(require $file):[];return is_array($cfg)?$cfg:[];
}

function courier_provider(): string
{
    return strtolower((string)(courier_config()['provider']??'manual'));
}

function courier_integration_status(): array
{
    $provider=courier_provider();
    if($provider==='shiplogic'){$cfg=(array)(courier_config()['shiplogic']??[]);return ['provider'=>'ShipLogic','configured'=>!empty($cfg['enabled'])&&!empty($cfg['api_key']),'mode'=>'API adapter reserved'];}
    return ['provider'=>'Manual','configured'=>true,'mode'=>'Manual dispatch/tracking'];
}
