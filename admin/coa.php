<?php
$adminTitle='COAs'; require __DIR__.'/_header.php'; $pdo=db(); $error='';
if($pdo && $_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_valid($_POST['csrf_token']??null))$error='Your session expired.'; else{
  $productId=(int)($_POST['product_id']??0);$batch=trim((string)($_POST['batch_number']??''));$lab=trim((string)($_POST['lab_name']??''));$purity=trim((string)($_POST['purity_label']??''));$tested=trim((string)($_POST['tested_at']??''));$public=isset($_POST['is_public'])?1:0;$file=$_FILES['coa_file']??null;
  if($productId<1||text_length($batch)<1||text_length($batch)>100)$error='Product and batch number are required.'; elseif(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)$error='Choose a PDF file.'; elseif(($file['size']??0)>10*1024*1024)$error='PDF must be 10 MB or smaller.'; else{
   $tmp=(string)$file['tmp_name'];$head=file_get_contents($tmp,false,null,0,5);$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($tmp);
   if($head!=='%PDF-'||$mime!=='application/pdf')$error='Uploaded file must be a valid PDF.'; else{
    $safe=preg_replace('/[^A-Za-z0-9._-]/','-', $batch);$name=date('YmdHis').'-'.bin2hex(random_bytes(4)).'-'.$safe.'.pdf';$rel='storage/coa/'.$name;$dest=__DIR__.'/../'.$rel;
    if(!move_uploaded_file($tmp,$dest))$error='Could not store the uploaded PDF.'; else{
     $hash=hash_file('sha256',$dest);$stmt=$pdo->prepare('INSERT INTO coa_documents(product_id,batch_number,lab_name,purity_label,tested_at,file_path,sha256,is_public) VALUES(:product,:batch,:lab,:purity,:tested,:path,:hash,:public)');$stmt->execute(['product'=>$productId,'batch'=>$batch,'lab'=>$lab?:null,'purity'=>$purity?:null,'tested'=>$tested?:null,'path'=>$rel,'hash'=>$hash,'public'=>$public]);header('Location: coa.php?saved=1');exit;
    }
   }
  }
 }
}
$products=$pdo?$pdo->query('SELECT id,sku,name FROM products ORDER BY name')->fetchAll():[];$rows=$pdo?$pdo->query('SELECT c.*,p.name product_name,p.sku FROM coa_documents c JOIN products p ON p.id=c.product_id ORDER BY c.created_at DESC')->fetchAll():[];
?>
<div class="admin-heading"><div><div class="eyebrow">Documentation</div><h1>COA records</h1></div></div><?php if(isset($_GET['saved'])):?><div class="alert success">COA uploaded and SHA-256 recorded.</div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="admin-two-col"><section class="card"><h2>Upload batch document</h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="field"><label>Product</label><select name="product_id" required><option value="">Select product</option><?php foreach($products as $p):?><option value="<?=(int)$p['id']?>"><?=e($p['sku'].' — '.$p['name'])?></option><?php endforeach;?></select></div><div class="field"><label>Batch number</label><input name="batch_number" maxlength="100" required></div><div class="field"><label>Testing laboratory</label><input name="lab_name" maxlength="160"></div><div class="form-grid"><div class="field"><label>Reported purity</label><input name="purity_label" maxlength="40"></div><div class="field"><label>Test date</label><input name="tested_at" type="date"></div></div><div class="field"><label>COA PDF</label><input name="coa_file" type="file" accept="application/pdf,.pdf" required></div><div class="field admin-checks"><label><input type="checkbox" name="is_public"> Publish in public COA library</label></div><button class="btn primary">Upload COA</button></form></section><section class="card"><h2>Documents</h2><div class="table-wrap"><table class="data-table"><thead><tr><th>Batch</th><th>Product</th><th>State</th><th>Digest</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><a href="../coa-download.php?id=<?=(int)$r['id']?>" target="_blank"><?=e($r['batch_number'])?></a></td><td><?=e($r['sku'])?></td><td><?=$r['is_public']?'Public':'Private'?></td><td><code title="<?=e($r['sha256'])?>"><?=e(substr($r['sha256'],0,12))?>…</code></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="4">No COAs uploaded.</td></tr><?php endif;?></tbody></table></div></section></div>
<?php require __DIR__.'/_footer.php';?>
