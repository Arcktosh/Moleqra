<?php
$adminTitle='Brand & SEO';
require_once __DIR__.'/../includes/auth.php';require_admin();
require_once __DIR__.'/../includes/branding.php';require_once __DIR__.'/../includes/site_seo.php';require_once __DIR__.'/../includes/audit.php';
$pdo=db();$error='';$message='';
function branding_upload_logo(array $file): string
{
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('Logo upload failed.');
    if((int)($file['size']??0)>3*1024*1024)throw new RuntimeException('Logo must be 3 MB or smaller.');
    $tmp=(string)($file['tmp_name']??'');$info=@getimagesize($tmp);if(!$info)throw new RuntimeException('Upload a valid PNG, JPEG, GIF or WebP image.');
    $allowed=['image/png'=>'png','image/jpeg'=>'jpg','image/gif'=>'gif','image/webp'=>'webp'];$mime=(string)($info['mime']??'');if(!isset($allowed[$mime]))throw new RuntimeException('Unsupported logo image type.');
    $dir=__DIR__.'/../assets/branding';if(!is_dir($dir)&&!mkdir($dir,0775,true))throw new RuntimeException('Branding upload directory is unavailable.');
    $name='logo-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];$dest=$dir.'/'.$name;if(!move_uploaded_file($tmp,$dest))throw new RuntimeException('Could not save the uploaded logo.');
    return 'assets/branding/'.$name;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null))$error='Your session expired.';
    elseif(!$pdo||!branding_schema_ready($pdo))$error='V9 branding/SEO tables are not ready. Run the System upgrade first.';
    else{try{
        $action=(string)($_POST['action']??'save');$adminId=(int)(admin_user()['id']??0)?:null;
        if($action==='save_branding'){
            $current=branding_settings($pdo);$logo=branding_upload_logo($_FILES['logo']??[]);if($logo==='')$logo=(string)($current['logo_path']??'');
            if(!empty($_POST['remove_logo']))$logo='';
            $siteName=trim((string)($_POST['site_name']??'Moleqra'));if($siteName===''||text_length($siteName)>120)throw new RuntimeException('Enter a valid site name.');
            $colors=[];foreach(['primary_color','secondary_color','background_color','surface_color','surface_alt_color','text_color','muted_color'] as $field){$colors[$field]=branding_hex((string)($_POST[$field]??''),(string)$current[$field]);}
            $sql='INSERT INTO site_branding(id,site_name,logo_path,primary_color,secondary_color,background_color,surface_color,surface_alt_color,text_color,muted_color,updated_by) VALUES(1,:site,:logo,:primary,:secondary,:bg,:surface,:surface_alt,:text,:muted,:admin) ON DUPLICATE KEY UPDATE site_name=VALUES(site_name),logo_path=VALUES(logo_path),primary_color=VALUES(primary_color),secondary_color=VALUES(secondary_color),background_color=VALUES(background_color),surface_color=VALUES(surface_color),surface_alt_color=VALUES(surface_alt_color),text_color=VALUES(text_color),muted_color=VALUES(muted_color),updated_by=VALUES(updated_by)';
            $pdo->prepare($sql)->execute(['site'=>$siteName,'logo'=>$logo,'primary'=>$colors['primary_color'],'secondary'=>$colors['secondary_color'],'bg'=>$colors['background_color'],'surface'=>$colors['surface_color'],'surface_alt'=>$colors['surface_alt_color'],'text'=>$colors['text_color'],'muted'=>$colors['muted_color'],'admin'=>$adminId]);
            admin_audit($pdo,'branding.update','site','1','Updated brand settings',['site_name'=>$siteName]);header('Location: branding.php?saved=brand');exit;
        }elseif($action==='save_seo'){
            $suffix=trim((string)($_POST['title_suffix']??''));$description=trim((string)($_POST['default_description']??''));$org=trim((string)($_POST['organization_name']??''));if(text_length($description)>300)throw new RuntimeException('Default description must be 300 characters or fewer.');
            $extra=trim((string)($_POST['extra_robots_disallow']??''));
            $sql='INSERT INTO site_seo_settings(id,title_suffix,default_description,organization_name,allow_indexing,sitemap_enabled,extra_robots_disallow,og_image_path,updated_by) VALUES(1,:suffix,:description,:org,:indexing,:sitemap,:extra,:og,:admin) ON DUPLICATE KEY UPDATE title_suffix=VALUES(title_suffix),default_description=VALUES(default_description),organization_name=VALUES(organization_name),allow_indexing=VALUES(allow_indexing),sitemap_enabled=VALUES(sitemap_enabled),extra_robots_disallow=VALUES(extra_robots_disallow),og_image_path=VALUES(og_image_path),updated_by=VALUES(updated_by)';
            $pdo->prepare($sql)->execute(['suffix'=>$suffix,'description'=>$description,'org'=>$org,'indexing'=>isset($_POST['allow_indexing'])?1:0,'sitemap'=>isset($_POST['sitemap_enabled'])?1:0,'extra'=>$extra?:null,'og'=>trim((string)($_POST['og_image_path']??''))?:null,'admin'=>$adminId]);
            admin_audit($pdo,'seo.update','site','1','Updated site SEO/robots settings');header('Location: branding.php?saved=seo');exit;
        }
    }catch(Throwable $e){error_log('Moleqra branding settings failed: '.$e->getMessage());$error=$e instanceof RuntimeException?$e->getMessage():'Settings could not be saved.';}}
}
require __DIR__.'/_header.php';
$brand=branding_settings($pdo);$seo=site_seo_settings($pdo);
?>
<div class="admin-heading"><div><div class="eyebrow">Presentation</div><h1>Brand &amp; SEO</h1><p class="muted">Change the site logo, visual palette, default search metadata and crawler policy without editing source files.</p></div></div>
<?php if(isset($_GET['saved'])):?><div class="alert success"><?= $_GET['saved']==='seo'?'SEO and robots settings saved.':'Brand settings saved.' ?></div><?php endif;?><?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<?php if(!$pdo||!branding_schema_ready($pdo)):?><div class="alert error">V9 settings are not installed. Run the upgrade from System.</div><?php else:?>
<div class="admin-two-col">
<section class="card"><h2>Branding</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_branding"><div class="form-grid">
<div class="field full"><label>Site name</label><input name="site_name" maxlength="120" value="<?=e($brand['site_name'])?>"></div>
<div class="field full"><label>Logo</label><?php if(branding_logo_path()):?><img class="branding-preview" src="../<?=e(branding_logo_path())?>" alt="Current logo"><?php endif;?><input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp"><label class="check-row"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label></div>
<?php foreach(['primary_color'=>'Primary / accent','secondary_color'=>'Secondary accent','background_color'=>'Background','surface_color'=>'Surface','surface_alt_color'=>'Alternate surface','text_color'=>'Text','muted_color'=>'Muted text'] as $field=>$label):?><div class="field"><label><?=e($label)?></label><input type="color" name="<?=e($field)?>" value="<?=e($brand[$field])?>"></div><?php endforeach;?>
<div class="field full"><button class="btn primary">Save branding</button></div></div></form></section>
<section class="card brand-live-preview" style="<?=e(branding_css_variables())?>"><h2>Live palette preview</h2><div class="brand-preview-surface"><div class="brand-preview-logo"><?php if(branding_logo_path()):?><img src="../<?=e(branding_logo_path())?>" alt=""><?php else:?><span class="brand-mark">M</span><?php endif;?><strong><?=e(config('site_name','Moleqra'))?></strong></div><p>This preview uses the current saved palette.</p><a class="btn primary" href="#">Primary action</a></div></section>
</div>
<section class="card admin-section-gap"><h2>SEO &amp; robots</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save_seo"><div class="form-grid">
<div class="field"><label>Title suffix</label><input name="title_suffix" maxlength="120" value="<?=e($seo['title_suffix'])?>"></div><div class="field"><label>Organisation name</label><input name="organization_name" maxlength="160" value="<?=e($seo['organization_name'])?>"></div>
<div class="field full"><label>Default meta description</label><textarea name="default_description" maxlength="300"><?=e($seo['default_description'])?></textarea></div>
<div class="field full"><label>Open Graph image path</label><input name="og_image_path" maxlength="255" placeholder="assets/branding/share-card.png" value="<?=e((string)$seo['og_image_path'])?>"><small class="muted">Leave empty to use the configured logo.</small></div>
<div class="field full"><label>Extra robots.txt Disallow paths</label><textarea name="extra_robots_disallow" placeholder="/private-preview/&#10;/internal-tools/"><?=e((string)$seo['extra_robots_disallow'])?></textarea></div>
<div class="field full"><label class="check-row"><input type="checkbox" name="allow_indexing" value="1"<?=!empty($seo['allow_indexing'])?' checked':''?>> Allow public search-engine indexing</label><label class="check-row"><input type="checkbox" name="sitemap_enabled" value="1"<?=!empty($seo['sitemap_enabled'])?' checked':''?>> Publish sitemap.xml when base URL is configured</label><div class="notice">Keep indexing disabled during development/staging. Enable it only when the public catalogue, legal pages, canonical base URL and product metadata are launch-ready.</div></div>
<div class="field full"><button class="btn primary">Save SEO settings</button></div></div></form></section>
<?php endif;?>
<?php require __DIR__.'/_footer.php';?>
