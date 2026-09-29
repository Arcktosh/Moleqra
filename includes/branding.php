<?php

declare(strict_types=1);

function branding_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();
    return $pdo && db_table_exists('site_branding',$pdo) && db_table_exists('site_seo_settings',$pdo);
}

function branding_hex(string $value,string $fallback): string
{
    $value=trim($value);
    return preg_match('/^#[0-9a-fA-F]{6}$/',$value)?strtolower($value):$fallback;
}

function branding_settings(?PDO $pdo=null): array
{
    static $cache=null;
    if($cache!==null)return $cache;
    $defaults=[
        'site_name'=>(string)($GLOBALS['config']['site_name']??'Moleqra'),
        'logo_path'=>null,
        'primary_color'=>'#63e6be','secondary_color'=>'#8be9fd','background_color'=>'#070b14',
        'surface_color'=>'#0e1526','surface_alt_color'=>'#141d31','text_color'=>'#f4f7fb','muted_color'=>'#9aa8bd',
    ];
    $pdo ??= db();
    if($pdo && db_table_exists('site_branding',$pdo)){
        try{$row=$pdo->query('SELECT * FROM site_branding WHERE id=1')->fetch();if($row)$defaults=array_merge($defaults,$row);}catch(Throwable $e){error_log('Moleqra branding load failed: '.$e->getMessage());}
    }
    foreach(['primary_color','secondary_color','background_color','surface_color','surface_alt_color','text_color','muted_color'] as $field)$defaults[$field]=branding_hex((string)$defaults[$field],match($field){'primary_color'=>'#63e6be','secondary_color'=>'#8be9fd','background_color'=>'#070b14','surface_color'=>'#0e1526','surface_alt_color'=>'#141d31','text_color'=>'#f4f7fb',default=>'#9aa8bd'});
    if($defaults['logo_path']===null){
        $latest='assets/1cd54664-3f0a-4fc2-85d2-946ad7341006.png';
        $packaged='assets/branding/moleqra-logo.png';
        if(is_file(__DIR__.'/../'.$latest))$defaults['logo_path']=$latest;
        elseif(is_file(__DIR__.'/../'.$packaged))$defaults['logo_path']=$packaged;
        else $defaults['logo_path']='';
    }
    $cache=$defaults;
    return $cache;
}

function branding_apply_config_overrides(): void
{
    global $config;
    $b=branding_settings();
    if(trim((string)$b['site_name'])!=='')$config['site_name']=trim((string)$b['site_name']);
}

function branding_logo_path(): string
{
    $path=trim((string)(branding_settings()['logo_path']??''));
    if($path===''||str_contains($path,'..')||preg_match('/^[a-z]+:\/\//i',$path))return '';
    return ltrim($path,'/');
}

function branding_logo_url(string $rootPrefix=''): string
{
    $path=branding_logo_path();return $path!==''?$rootPrefix.$path:'';
}

function branding_css_variables(): string
{
    $b=branding_settings();
    return ':root{--bg:'.$b['background_color'].';--surface:'.$b['surface_color'].';--surface-2:'.$b['surface_alt_color'].';--text:'.$b['text_color'].';--muted:'.$b['muted_color'].';--accent:'.$b['primary_color'].';--accent-2:'.$b['secondary_color'].';}';
}

function branding_reset_cache(): void
{
    // Request-scoped settings are reloaded on the next request after admin save.
}
