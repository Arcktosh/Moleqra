<?php
require_once __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/seo.php';require_once __DIR__.'/includes/site_seo.php';header('Content-Type: text/plain; charset=UTF-8');
$seo=site_seo_settings();echo "User-agent: *\n";
if(empty($seo['allow_indexing'])){echo "Disallow: /\n";exit;}
echo "Allow: /\n";foreach(['/admin/','/config/','/storage/','/account/','/payment/','/automation/'] as $path)echo "Disallow: {$path}\n";foreach(site_seo_extra_disallow() as $path)echo "Disallow: {$path}\n";
if(!empty($seo['sitemap_enabled'])){$sitemap=seo_url('sitemap.xml');if($sitemap!=='')echo "Sitemap: {$sitemap}\n";}
