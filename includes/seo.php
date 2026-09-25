<?php

declare(strict_types=1);

function seo_base_url(): string
{
    return rtrim((string)config('base_url',''),'/');
}

function seo_url(string $path=''): string
{
    $base=seo_base_url();
    if($base==='')return '';
    return $base.'/'.ltrim($path,'/');
}

function seo_product_url(array $product): string
{
    return seo_url(storefront_product_path($product));
}

function seo_json(array $data): string
{
    return (string)json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
}

function seo_product_schema(array $product,array $offers): array
{
    $url=seo_product_url($product);
    $schema=[
        '@context'=>'https://schema.org','@type'=>'Product','name'=>(string)$product['name'],'sku'=>(string)$product['sku'],
        'description'=>storefront_summary($product),
    ];
    if($url!=='')$schema['url']=$url;
    if($product['category']??null)$schema['category']=(string)$product['category'];
    if($offers)$schema['offers']=count($offers)===1?$offers[0]:$offers;
    return $schema;
}

function seo_offer_schema(array $offer,float $available): array
{
    return [
        '@type'=>'Offer','price'=>(float)$offer['price'],'priceCurrency'=>(string)($offer['currency']??commerce_currency()),
        'availability'=>$available>0?'https://schema.org/InStock':'https://schema.org/OutOfStock',
        'itemCondition'=>'https://schema.org/NewCondition',
    ];
}
