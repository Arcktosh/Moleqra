<?php

declare(strict_types=1);

function site_seo_settings(?PDO $pdo=null): array
{
    static $cache=null;if($cache!==null)return $cache;
    $defaults=[
        'title_suffix'=>(string)($GLOBALS['config']['site_name']??'Moleqra'),
        'default_description'=>'Moleqra provides research-use-only materials with batch documentation and transparent quality controls.',
        'organization_name'=>(string)($GLOBALS['config']['company_name']??'Moleqra Biosciences'),
        'allow_indexing'=>0,'sitemap_enabled'=>1,'extra_robots_disallow'=>'','og_image_path'=>null,
    ];
    $pdo ??= db();
    if($pdo && db_table_exists('site_seo_settings',$pdo)){
        try{$row=$pdo->query('SELECT * FROM site_seo_settings WHERE id=1')->fetch();if($row)$defaults=array_merge($defaults,$row);}catch(Throwable $e){error_log('Moleqra SEO settings load failed: '.$e->getMessage());}
    }
    $cache=$defaults;return $cache;
}

function site_seo_title(string $title): string
{
    $suffix=trim((string)(site_seo_settings()['title_suffix']??''));
    if($suffix===''||stripos($title,$suffix)!==false)return $title;
    return trim($title).' | '.$suffix;
}

function site_seo_default_description(): string
{
    return trim((string)(site_seo_settings()['default_description']??''));
}

function site_seo_global_robots(): ?string
{
    return !empty(site_seo_settings()['allow_indexing'])?null:'noindex,nofollow';
}

function site_seo_og_image_url(): string
{
    $path=trim((string)(site_seo_settings()['og_image_path']??''));
    if($path==='')$path=branding_logo_path();
    if($path===''||seo_base_url()==='')return '';
    return seo_url($path);
}

function site_seo_extra_disallow(): array
{
    $raw=(string)(site_seo_settings()['extra_robots_disallow']??'');$out=[];
    foreach(preg_split('/\r?\n/',$raw)?:[] as $line){$line=trim($line);if($line!==''&&str_starts_with($line,'/')&&!str_contains($line,"\n"))$out[]=$line;}
    return array_values(array_unique($out));
}
