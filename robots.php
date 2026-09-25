<?php
require_once __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/seo.php';header('Content-Type: text/plain; charset=UTF-8');
echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /config/\nDisallow: /storage/\nDisallow: /account/\nDisallow: /payment/\n";$sitemap=seo_url('sitemap.xml');if($sitemap!=='')echo "Sitemap: {$sitemap}\n";
