<?php
$adminTitle='Communications';
require __DIR__.'/_header.php';
require_once __DIR__.'/../includes/communications.php';
require_once __DIR__.'/../includes/mailbox.php';
require_once __DIR__.'/../includes/audit.php';
$pdo=db();$ready=$pdo&&communications_schema_ready($pdo);$error='';$message='';
$view=(int)($_GET['view']??$_POST['thread_id']??0);
$customerId=(int)($_GET['customer']??0);$supplierId=(int)($_GET['supplier']??0);$enquiryId=(int)($_GET['enquiry']??0);
$q=trim((string)($_GET['q']??''));$statusFilter=trim((string)($_GET['status']??''));
$prefill=['email'=>'','name'=>'','subject'=>'','party_type'=>'contact'];
if($ready&&$customerId){$s=$pdo->prepare('SELECT id,email,first_name,last_name FROM customer_accounts WHERE id=:id');$s->execute(['id'=>$customerId]);if($row=$s->fetch())$prefill=['email'=>$row['email'],'name'=>trim($row['first_name'].' '.$row['last_name']),'subject'=>'Customer communication','party_type'=>'customer'];}
if($ready&&$supplierId){$s=$pdo->prepare('SELECT id,email,name,contact_name FROM suppliers WHERE id=:id');$s->execute(['id'=>$supplierId]);if($row=$s->fetch())$prefill=['email'=>$row['email'],'name'=>$row['contact_name']?:$row['name'],'subject'=>'Supplier communication · '.$row['name'],'party_type'=>'supplier'];}
if($ready&&$enquiryId){$s=$pdo->prepare('SELECT * FROM enquiries WHERE id=:id');$s->execute(['id'=>$enquiryId]);if($row=$s->fetch()){$view=communication_capture_enquiry($pdo,$enquiryId,$row);}}
if($ready&&$_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null))$error='Your session expired.';
    else{
        try{
            $action=(string)($_POST['action']??'');$adminId=(int)(admin_user()['id']??0);
            if($action==='reply'){
                $threadId=(int)($_POST['thread_id']??0);$subject=trim((string)($_POST['subject']??''));$body=trim((string)($_POST['body']??''));
                if(!communication_send_admin_reply($pdo,$threadId,$subject,$body,$adminId))throw new RuntimeException('The reply was recorded but the mail transport reported a failure.');
                admin_audit($pdo,'communication.reply','communication_thread',(string)$threadId,'Sent back-office email reply',['subject'=>$subject]);
                header('Location: communications.php?view='.$threadId.'&sent=1');exit;
            }
            if($action==='compose'){
                $email=communication_normalize_email((string)($_POST['email']??''));$name=trim((string)($_POST['contact_name']??''));$subject=trim((string)($_POST['subject']??''));$body=trim((string)($_POST['body']??''));
                if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid recipient email.');
                $cid=(int)($_POST['customer_id']??0)?:null;$sid=(int)($_POST['supplier_id']??0)?:null;
                $thread=communication_ensure_thread($pdo,['email'=>$email,'contact_name'=>$name,'customer_id'=>$cid,'supplier_id'=>$sid,'subject'=>$subject?:'Back-office communication','source'=>'Back office']);
                if(!communication_send_admin_reply($pdo,$thread,$subject,$body,$adminId))throw new RuntimeException('The message was recorded but the mail transport reported a failure.');
                admin_audit($pdo,'communication.compose','communication_thread',(string)$thread,'Sent back-office email',['recipient'=>$email,'subject'=>$subject]);
                header('Location: communications.php?view='.$thread.'&sent=1');exit;
            }
            if($action==='capture_inbound'){
                $threadId=(int)($_POST['thread_id']??0);$thread=communication_thread($pdo,$threadId);if(!$thread)throw new RuntimeException('Communication thread not found.');
                $subject=trim((string)($_POST['subject']??$thread['subject']));$body=trim((string)($_POST['body']??''));if($body==='')throw new RuntimeException('Inbound message/note is required.');
                communication_add_message($pdo,$threadId,['direction'=>'Inbound','channel'=>(string)($_POST['channel']??'Manual'),'sender_email'=>$thread['email'],'recipient_email'=>(string)config('contact_email',''),'subject'=>$subject,'body_text'=>$body,'status'=>'Received','received_at'=>date('Y-m-d H:i:s')]);
                admin_audit($pdo,'communication.capture_inbound','communication_thread',(string)$threadId,'Captured inbound communication',['channel'=>(string)($_POST['channel']??'Manual')]);
                header('Location: communications.php?view='.$threadId.'&captured=1');exit;
            }
            if($action==='status'){
                $threadId=(int)($_POST['thread_id']??0);$status=(string)($_POST['status']??'Open');if(!in_array($status,['Open','Waiting','Closed'],true))throw new RuntimeException('Invalid thread status.');
                $pdo->prepare('UPDATE communication_threads SET status=:status WHERE id=:id')->execute(['status'=>$status,'id'=>$threadId]);
                admin_audit($pdo,'communication.status','communication_thread',(string)$threadId,'Changed communication status',['status'=>$status]);
                header('Location: communications.php?view='.$threadId.'&saved=1');exit;
            }
            if($action==='sync_mailbox'){
                $result=mailbox_sync($pdo);$message='Mailbox sync complete: '.$result['imported'].' imported, '.$result['skipped'].' skipped, '.$result['failed'].' failed.';
                admin_audit($pdo,'communication.mailbox_sync','communications',null,'Synced inbound mailbox',$result);
            }
        }catch(Throwable $e){$error=$e->getMessage();}
    }
}
$params=[];$where=[];
if($q!==''){$where[]='(t.email LIKE :q OR t.contact_name LIKE :q OR t.subject LIKE :q)';$params['q']='%'.$q.'%';}
if(in_array($statusFilter,['Open','Waiting','Closed'],true)){$where[]='t.status=:status';$params['status']=$statusFilter;}
$sql="SELECT t.*,(SELECT COUNT(*) FROM communication_messages m WHERE m.thread_id=t.id) message_count,(SELECT direction FROM communication_messages m WHERE m.thread_id=t.id ORDER BY m.created_at DESC,m.id DESC LIMIT 1) last_direction FROM communication_threads t".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY t.last_message_at DESC LIMIT 300";
$stmt=$ready?$pdo->prepare($sql):null;if($stmt){$stmt->execute($params);$threads=$stmt->fetchAll();}else$threads=[];
$thread=$ready&&$view?communication_thread($pdo,$view):null;$messages=$thread?communication_messages($pdo,$view):[];
?>
<div class="admin-heading"><div><div class="eyebrow">CRM inbox</div><h1>Communications</h1><p class="muted">Website enquiries, customer/supplier email history, replies and inbound mailbox capture.</p></div><div class="admin-actions"><?php if($ready):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="sync_mailbox"><button class="btn"<?=mailbox_ready()?'':' disabled'?>>Sync mailbox</button></form><?php endif;?></div></div>
<?php if(!$ready):?><div class="alert error">Communications migration 011 is not installed.</div><?php endif;?>
<?php if(isset($_GET['sent'])):?><div class="alert success">Email sent and added to communication history.</div><?php endif;?>
<?php if(isset($_GET['captured'])):?><div class="alert success">Inbound communication captured.</div><?php endif;?>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<?php if($ready):?>
<div class="communications-grid">
<section>
<form method="get" class="card communication-filters"><div class="form-grid"><div class="field"><label>Search</label><input name="q" value="<?=e($q)?>" placeholder="Email, name or subject"></div><div class="field"><label>Status</label><select name="status"><option value="">All</option><?php foreach(['Open','Waiting','Closed'] as $s):?><option<?= $statusFilter===$s?' selected':'' ?>><?=e($s)?></option><?php endforeach;?></select></div><div class="field full"><button class="btn">Filter</button></div></div></form>
<div class="communication-thread-list"><?php foreach($threads as $t):?><a class="communication-thread-row<?= $view===(int)$t['id']?' active':'' ?>" href="communications.php?view=<?=(int)$t['id']?>"><div><strong><?=e($t['contact_name']?:$t['email'])?></strong><span><?=e($t['subject'])?></span><small><?=e($t['email'])?> · <?=e($t['party_type'])?></small></div><div><span class="status-pill"><?=e($t['status'])?></span><small><?=(int)$t['message_count']?> msg · <?=e($t['last_message_at'])?></small></div></a><?php endforeach;?><?php if(!$threads):?><div class="card">No communication threads found.</div><?php endif;?></div>
</section>
<section>
<?php if($thread):?>
<article class="card communication-thread-panel">
<div class="section-heading"><div><h2><?=e($thread['contact_name']?:$thread['email'])?></h2><p class="muted"><?=e($thread['email'])?> · <?=e($thread['subject'])?></p><p><?php if($thread['customer_id']):?><a href="customers.php?view=<?=(int)$thread['customer_id']?>">Customer record</a><?php endif;?><?= $thread['customer_id']&&$thread['supplier_id']?' · ':'' ?><?php if($thread['supplier_id']):?><a href="suppliers.php?edit=<?=(int)$thread['supplier_id']?>">Supplier record</a><?php endif;?><?php if($thread['enquiry_id']):?> · <a href="enquiries.php?view=<?=(int)$thread['enquiry_id']?>">Website enquiry</a><?php endif;?></p></div><form method="post" class="inline-select"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="thread_id" value="<?=(int)$thread['id']?>"><select name="status"><?php foreach(['Open','Waiting','Closed'] as $s):?><option<?= $thread['status']===$s?' selected':'' ?>><?=e($s)?></option><?php endforeach;?></select><button class="btn">Save</button></form></div>
<div class="communication-messages"><?php foreach($messages as $m):?><article class="communication-message <?=strtolower(e($m['direction']))?>"><header><strong><?=e($m['direction'])?> · <?=e($m['channel'])?></strong><span><?=e($m['sent_at']?:($m['received_at']?:$m['created_at']))?></span></header><p class="communication-subject"><?=e($m['subject']?:$thread['subject'])?></p><div class="communication-body"><?=nl2br(e($m['body_text']?:strip_tags((string)$m['body_html'])))?></div><footer><?=e($m['status'])?><?= $m['admin_name']?' · by '.e($m['admin_name']):'' ?><?= $m['error_message']?' · '.e($m['error_message']):'' ?></footer></article><?php endforeach;?></div>
<div class="admin-two-col admin-section-gap">
<form method="post" class="card compact-card"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="thread_id" value="<?=(int)$thread['id']?>"><h3>Reply by email</h3><div class="field"><label>Subject</label><input name="subject" maxlength="220" required value="<?=e(str_starts_with(strtolower($thread['subject']),'re:')?$thread['subject']:'Re: '.$thread['subject'])?>"></div><div class="field"><label>Message</label><textarea name="body" maxlength="12000" required></textarea></div><button class="btn primary"<?=mailer_delivery_ready()?'':' disabled'?>>Send reply</button><?php if(!mailer_delivery_ready()):?><p class="muted">Outbound mail is not configured.</p><?php endif;?></form>
<form method="post" class="card compact-card"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="capture_inbound"><input type="hidden" name="thread_id" value="<?=(int)$thread['id']?>"><h3>Capture incoming communication</h3><div class="field"><label>Channel</label><select name="channel"><option>Email</option><option>Phone</option><option>WhatsApp</option><option>Manual</option></select></div><div class="field"><label>Subject</label><input name="subject" maxlength="220" value="<?=e($thread['subject'])?>"></div><div class="field"><label>Message / note</label><textarea name="body" maxlength="12000" required></textarea></div><button class="btn">Add to history</button></form>
</div>
</article>
<?php else:?>
<section class="card"><h2>New email</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="compose"><input type="hidden" name="customer_id" value="<?=$customerId?>"><input type="hidden" name="supplier_id" value="<?=$supplierId?>"><div class="form-grid"><div class="field"><label>Recipient email</label><input type="email" name="email" required value="<?=e($prefill['email'])?>"></div><div class="field"><label>Name</label><input name="contact_name" maxlength="160" value="<?=e($prefill['name'])?>"></div><div class="field full"><label>Subject</label><input name="subject" maxlength="220" required value="<?=e($prefill['subject'])?>"></div><div class="field full"><label>Message</label><textarea name="body" maxlength="12000" required></textarea></div><div class="field full"><button class="btn primary"<?=mailer_delivery_ready()?'':' disabled'?>>Send email</button></div></div></form></section>
<?php endif;?>
</section>
</div>
<?php endif;?>
<?php require __DIR__.'/_footer.php';?>
