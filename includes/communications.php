<?php

declare(strict_types=1);

require_once __DIR__.'/bootstrap.php';

function communications_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();
    return $pdo && db_table_exists('communication_threads',$pdo) && db_table_exists('communication_messages',$pdo);
}

function communication_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function communication_party_from_email(PDO $pdo,string $email): array
{
    $email=communication_normalize_email($email);
    if($email==='')return ['party_type'=>'contact','customer_id'=>null,'supplier_id'=>null,'contact_name'=>null];
    if(db_table_exists('customer_accounts',$pdo)){
        $s=$pdo->prepare('SELECT id,first_name,last_name FROM customer_accounts WHERE LOWER(email)=:email LIMIT 1');
        $s->execute(['email'=>$email]);$row=$s->fetch();
        if($row)return ['party_type'=>'customer','customer_id'=>(int)$row['id'],'supplier_id'=>null,'contact_name'=>trim($row['first_name'].' '.$row['last_name'])];
    }
    if(db_table_exists('suppliers',$pdo)){
        $s=$pdo->prepare('SELECT id,name,contact_name FROM suppliers WHERE LOWER(email)=:email LIMIT 1');
        $s->execute(['email'=>$email]);$row=$s->fetch();
        if($row)return ['party_type'=>'supplier','customer_id'=>null,'supplier_id'=>(int)$row['id'],'contact_name'=>trim((string)($row['contact_name']?:$row['name']))];
    }
    return ['party_type'=>'contact','customer_id'=>null,'supplier_id'=>null,'contact_name'=>null];
}

function communication_thread(PDO $pdo,int $threadId): ?array
{
    $s=$pdo->prepare('SELECT * FROM communication_threads WHERE id=:id');$s->execute(['id'=>$threadId]);
    return $s->fetch()?:null;
}

function communication_messages(PDO $pdo,int $threadId): array
{
    $s=$pdo->prepare('SELECT m.*,a.display_name admin_name FROM communication_messages m LEFT JOIN admin_users a ON a.id=m.sent_by_admin WHERE m.thread_id=:id ORDER BY m.created_at ASC,m.id ASC');
    $s->execute(['id'=>$threadId]);return $s->fetchAll();
}

function communication_find_thread(PDO $pdo,string $email,string $subject='',?int $customerId=null,?int $supplierId=null,?int $enquiryId=null): ?int
{
    if($enquiryId){
        $s=$pdo->prepare('SELECT id FROM communication_threads WHERE enquiry_id=:id LIMIT 1');$s->execute(['id'=>$enquiryId]);$id=(int)$s->fetchColumn();if($id)return $id;
    }
    if($customerId){$s=$pdo->prepare("SELECT id FROM communication_threads WHERE customer_id=:id AND status<>'Closed' ORDER BY last_message_at DESC LIMIT 1");$s->execute(['id'=>$customerId]);$id=(int)$s->fetchColumn();if($id)return $id;}
    if($supplierId){$s=$pdo->prepare("SELECT id FROM communication_threads WHERE supplier_id=:id AND status<>'Closed' ORDER BY last_message_at DESC LIMIT 1");$s->execute(['id'=>$supplierId]);$id=(int)$s->fetchColumn();if($id)return $id;}
    $email=communication_normalize_email($email);
    if($email!==''){
        $s=$pdo->prepare("SELECT id FROM communication_threads WHERE LOWER(email)=:email AND status<>'Closed' ORDER BY last_message_at DESC LIMIT 1");$s->execute(['email'=>$email]);$id=(int)$s->fetchColumn();if($id)return $id;
    }
    return null;
}

function communication_create_thread(PDO $pdo,array $data): int
{
    $email=communication_normalize_email((string)($data['email']??''));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('A valid communication email address is required.');
    $party=$data['party']??communication_party_from_email($pdo,$email);
    $stmt=$pdo->prepare("INSERT INTO communication_threads(party_type,customer_id,supplier_id,enquiry_id,contact_name,email,subject,source,status,assigned_to,last_message_at)
      VALUES(:party_type,:customer,:supplier,:enquiry,:name,:email,:subject,:source,:status,:assigned,NOW())");
    $stmt->execute([
        'party_type'=>$data['party_type']??$party['party_type']??'contact',
        'customer'=>$data['customer_id']??$party['customer_id']??null,
        'supplier'=>$data['supplier_id']??$party['supplier_id']??null,
        'enquiry'=>$data['enquiry_id']??null,
        'name'=>trim((string)($data['contact_name']??$party['contact_name']??''))?:null,
        'email'=>$email,
        'subject'=>trim((string)($data['subject']??'Conversation'))?:'Conversation',
        'source'=>substr((string)($data['source']??'Manual'),0,40),
        'status'=>$data['status']??'Open',
        'assigned'=>$data['assigned_to']??null,
    ]);
    return (int)$pdo->lastInsertId();
}

function communication_ensure_thread(PDO $pdo,array $data): int
{
    $id=communication_find_thread($pdo,(string)($data['email']??''),(string)($data['subject']??''),$data['customer_id']??null,$data['supplier_id']??null,$data['enquiry_id']??null);
    return $id?:communication_create_thread($pdo,$data);
}

function communication_add_message(PDO $pdo,int $threadId,array $data): int
{
    $stmt=$pdo->prepare("INSERT INTO communication_messages(thread_id,direction,channel,sender_email,recipient_email,subject,body_text,body_html,transport,status,external_message_id,in_reply_to,sent_by_admin,error_message,sent_at,received_at)
      VALUES(:thread,:direction,:channel,:sender,:recipient,:subject,:body_text,:body_html,:transport,:status,:external_id,:reply_to,:admin,:error,:sent_at,:received_at)");
    $stmt->execute([
        'thread'=>$threadId,
        'direction'=>$data['direction']??'Inbound',
        'channel'=>$data['channel']??'Email',
        'sender'=>isset($data['sender_email'])?communication_normalize_email((string)$data['sender_email']):null,
        'recipient'=>isset($data['recipient_email'])?communication_normalize_email((string)$data['recipient_email']):null,
        'subject'=>$data['subject']??null,
        'body_text'=>$data['body_text']??null,
        'body_html'=>$data['body_html']??null,
        'transport'=>$data['transport']??null,
        'status'=>$data['status']??'Received',
        'external_id'=>$data['external_message_id']??null,
        'reply_to'=>$data['in_reply_to']??null,
        'admin'=>$data['sent_by_admin']??null,
        'error'=>$data['error_message']??null,
        'sent_at'=>$data['sent_at']??null,
        'received_at'=>$data['received_at']??null,
    ]);
    $id=(int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE communication_threads SET last_message_at=NOW(),status=CASE WHEN :direction='Inbound' THEN 'Open' ELSE status END WHERE id=:id")->execute(['direction'=>$data['direction']??'Inbound','id'=>$threadId]);
    return $id;
}

function communication_capture_enquiry(PDO $pdo,int $enquiryId,array $enquiry): int
{
    if(!communications_schema_ready($pdo))return 0;
    $thread=communication_ensure_thread($pdo,[
        'enquiry_id'=>$enquiryId,'email'=>$enquiry['email'],'contact_name'=>$enquiry['name']??null,
        'subject'=>'Website enquiry · '.ucfirst((string)($enquiry['topic']??'general')),'source'=>'Website enquiry'
    ]);
    $check=$pdo->prepare("SELECT COUNT(*) FROM communication_messages WHERE thread_id=:thread AND direction='Inbound' AND channel='Website'");$check->execute(['thread'=>$thread]);
    if((int)$check->fetchColumn()===0){
        communication_add_message($pdo,$thread,[
            'direction'=>'Inbound','channel'=>'Website','sender_email'=>$enquiry['email'],
            'recipient_email'=>(string)config('contact_email',''),'subject'=>'Website enquiry · '.ucfirst((string)($enquiry['topic']??'general')),
            'body_text'=>$enquiry['message']??'','status'=>'Received','received_at'=>date('Y-m-d H:i:s')
        ]);
    }
    return $thread;
}

function communication_thread_for_customer(PDO $pdo,int $customerId,string $email,string $name=''): int
{
    return communication_ensure_thread($pdo,['customer_id'=>$customerId,'party_type'=>'customer','email'=>$email,'contact_name'=>$name,'subject'=>'Customer communication','source'=>'Back office']);
}

function communication_thread_for_supplier(PDO $pdo,int $supplierId,string $email,string $name=''): int
{
    return communication_ensure_thread($pdo,['supplier_id'=>$supplierId,'party_type'=>'supplier','email'=>$email,'contact_name'=>$name,'subject'=>'Supplier communication','source'=>'Back office']);
}

function communication_record_outbound(PDO $pdo,string $recipient,string $subject,string $html,array $context=[]): void
{
    if(!communications_schema_ready($pdo)||!filter_var($recipient,FILTER_VALIDATE_EMAIL))return;
    $thread=communication_ensure_thread($pdo,[
        'email'=>$recipient,'customer_id'=>$context['customer_id']??null,'supplier_id'=>$context['supplier_id']??null,'enquiry_id'=>$context['enquiry_id']??null,
        'contact_name'=>$context['contact_name']??null,'subject'=>$context['thread_subject']??$subject,'source'=>$context['source']??'System email'
    ]);
    $cfg=function_exists('mail_config')?mail_config():[];
    communication_add_message($pdo,$thread,[
        'direction'=>'Outbound','channel'=>'Email','sender_email'=>$cfg['from_email']??config('contact_email',''),
        'recipient_email'=>$recipient,'subject'=>$subject,'body_html'=>$html,'body_text'=>trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$html))),
        'transport'=>$cfg['transport']??null,'status'=>$context['status']??'Sent','sent_by_admin'=>$context['admin_id']??null,
        'error_message'=>$context['error']??null,'sent_at'=>date('Y-m-d H:i:s')
    ]);
}

function communication_send_admin_reply(PDO $pdo,int $threadId,string $subject,string $body,int $adminId): bool
{
    $thread=communication_thread($pdo,$threadId);if(!$thread)throw new RuntimeException('Communication thread not found.');
    $recipient=(string)$thread['email'];if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Thread recipient email is invalid.');
    $subject=trim($subject);$body=trim($body);if($subject===''||$body==='')throw new RuntimeException('Subject and message are required.');
    if(!function_exists('mailer_send'))require_once __DIR__.'/mailer.php';
    $html=mailer_layout($subject,'<div style="white-space:pre-wrap">'.nl2br(e($body)).'</div>');
    $ok=mailer_send($pdo,'backoffice_reply_'.$threadId.'_'.bin2hex(random_bytes(5)),$recipient,$subject,$html,null,$thread['customer_id']?(int)$thread['customer_id']:null,false);
    communication_add_message($pdo,$threadId,[
        'direction'=>'Outbound','channel'=>'Email','sender_email'=>(string)(mail_config()['from_email']??config('contact_email','')),
        'recipient_email'=>$recipient,'subject'=>$subject,'body_text'=>$body,'body_html'=>$html,'transport'=>mail_config()['transport']??null,
        'status'=>$ok?'Sent':'Failed','sent_by_admin'=>$adminId,'error_message'=>$ok?null:'Mail transport reported failure.','sent_at'=>date('Y-m-d H:i:s')
    ]);
    if($ok)$pdo->prepare("UPDATE communication_threads SET status='Waiting',last_message_at=NOW() WHERE id=:id")->execute(['id'=>$threadId]);
    return $ok;
}
