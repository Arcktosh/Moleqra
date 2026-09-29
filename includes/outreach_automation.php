<?php

declare(strict_types=1);

require_once __DIR__.'/sourcing.php';
require_once __DIR__.'/mailer.php';

function outreach_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();if(!$pdo)return false;
    foreach(['supplier_outreach_templates','supplier_outreach_campaigns','supplier_outreach_queue'] as $table)if(!db_table_exists($table,$pdo))return false;
    return true;
}

function outreach_column_exists(PDO $pdo,string $table,string $column): bool
{
    if(!preg_match('/^[a-zA-Z0-9_]+$/',$table.$column))return false;
    try{
        $stmt=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column LIMIT 1');
        $stmt->execute(['table'=>$table,'column'=>$column]);
        return (bool)$stmt->fetchColumn();
    }catch(Throwable $e){
        error_log('Moleqra outreach column check failed: '.$e->getMessage());
        return false;
    }
}

function outreach_campaign_edit_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();
    return $pdo && outreach_schema_ready($pdo)
        && outreach_column_exists($pdo,'supplier_outreach_campaigns','subject_template')
        && outreach_column_exists($pdo,'supplier_outreach_campaigns','body_html');
}

function outreach_mail_ready(): bool
{
    return mailer_delivery_ready();
}

function outreach_render(string $template,array $supplier): string
{
    $greeting=trim((string)($supplier['contact_name']??''));$greeting=$greeting!==''?$greeting:(string)($supplier['name']??'Sales Team');
    $base=rtrim((string)config('base_url',''),'/');
    $vars=[
        '{{supplier_name}}'=>(string)($supplier['name']??''),
        '{{contact_name}}'=>(string)($supplier['contact_name']??''),
        '{{contact_greeting}}'=>$greeting,
        '{{site_name}}'=>(string)config('site_name','Moleqra'),
        '{{contact_email}}'=>(string)config('contact_email',''),
        '{{website}}'=>$base,
    ];
    return strtr($template,$vars);
}

function outreach_campaign_record(PDO $pdo,int $campaignId): ?array
{
    $content=outreach_campaign_edit_schema_ready($pdo)
        ? "COALESCE(NULLIF(c.subject_template,''),t.subject_template) subject_template,COALESCE(NULLIF(c.body_html,''),t.body_html) body_html"
        : 't.subject_template,t.body_html';
    $stmt=$pdo->prepare("SELECT c.*,t.name template_name,$content FROM supplier_outreach_campaigns c JOIN supplier_outreach_templates t ON t.id=c.template_id WHERE c.id=:id LIMIT 1");
    $stmt->execute(['id'=>$campaignId]);
    return $stmt->fetch()?:null;
}

function outreach_campaign_send_started(PDO $pdo,int $campaignId): bool
{
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM supplier_outreach_queue WHERE campaign_id=:id AND (attempts>0 OR status IN ('Sending','Sent'))");
    $stmt->execute(['id'=>$campaignId]);
    return (int)$stmt->fetchColumn()>0;
}

function outreach_campaign_editable(PDO $pdo,int $campaignId): bool
{
    $campaign=outreach_campaign_record($pdo,$campaignId);
    if(!$campaign || $campaign['status']==='Completed')return false;
    return !outreach_campaign_send_started($pdo,$campaignId);
}

function outreach_campaign_queue_stats(PDO $pdo,int $campaignId): array
{
    $stmt=$pdo->prepare("SELECT COUNT(*) total,
        SUM(status='Pending') pending,
        SUM(status='Sending') sending,
        SUM(status='Sent') sent,
        SUM(status='Failed') failed,
        SUM(status='Skipped') skipped,
        SUM(attempts>0) attempted
        FROM supplier_outreach_queue WHERE campaign_id=:id");
    $stmt->execute(['id'=>$campaignId]);$row=$stmt->fetch()?:[];
    foreach(['total','pending','sending','sent','failed','skipped','attempted'] as $key)$row[$key]=(int)($row[$key]??0);
    return $row;
}

function outreach_campaign_filter(array $campaign,array &$params): array
{
    $where=['s.email IS NOT NULL','s.email<>\'\'','s.outreach_email_enabled=1'];$params=[];
    if(trim((string)($campaign['target_supplier_status']??''))!==''){$where[]='s.status=:status';$params['status']=$campaign['target_supplier_status'];}
    if(trim((string)($campaign['target_region']??''))!==''){$where[]='s.region=:region';$params['region']=$campaign['target_region'];}
    return $where;
}

function outreach_preview_supplier(PDO $pdo,?int $campaignId=null): array
{
    if($campaignId){
        $stmt=$pdo->prepare('SELECT s.* FROM supplier_outreach_queue q JOIN suppliers s ON s.id=q.supplier_id WHERE q.campaign_id=:id ORDER BY q.id LIMIT 1');
        $stmt->execute(['id'=>$campaignId]);$supplier=$stmt->fetch();if($supplier)return $supplier;
        $campaign=outreach_campaign_record($pdo,$campaignId);
        if($campaign){$params=[];$where=outreach_campaign_filter($campaign,$params);$stmt=$pdo->prepare('SELECT s.* FROM suppliers s WHERE '.implode(' AND ',$where).' ORDER BY s.name LIMIT 1');$stmt->execute($params);$supplier=$stmt->fetch();if($supplier)return $supplier;}
    }
    try{$supplier=$pdo->query("SELECT * FROM suppliers WHERE email IS NOT NULL AND email<>'' ORDER BY name LIMIT 1")->fetch();if($supplier)return $supplier;}catch(Throwable $e){}
    return ['id'=>0,'name'=>'Example Supplier','contact_name'=>'Sales Team','email'=>'supplier@example.com','status'=>'Prospect','region'=>'South Africa'];
}

function outreach_preview_email(string $subjectTemplate,string $bodyTemplate,array $supplier): array
{
    $subject=outreach_render($subjectTemplate,$supplier);$body=outreach_render($bodyTemplate,$supplier);
    return ['subject'=>$subject,'body_html'=>$body,'html'=>mailer_layout('Partnership enquiry',$body)];
}

function outreach_queue_campaign(PDO $pdo,int $campaignId): int
{
    $campaign=outreach_campaign_record($pdo,$campaignId);if(!$campaign)throw new RuntimeException('Campaign not found.');
    $params=[];$where=outreach_campaign_filter($campaign,$params);
    $sql='SELECT s.* FROM suppliers s WHERE '.implode(' AND ',$where).' ORDER BY s.name';$s=$pdo->prepare($sql);$s->execute($params);$added=0;
    $insert=$pdo->prepare("INSERT IGNORE INTO supplier_outreach_queue(campaign_id,supplier_id,recipient,subject,body_html,status,scheduled_at) VALUES(:campaign,:supplier,:recipient,:subject,:body,'Pending',COALESCE(:start,NOW()))");
    foreach($s->fetchAll() as $supplier){
        if(!filter_var($supplier['email'],FILTER_VALIDATE_EMAIL))continue;
        $minDays=max(0,(int)$campaign['min_days_between_contacts']);
        if($minDays>0 && !empty($supplier['outreach_last_emailed_at'])){
            $last=strtotime((string)$supplier['outreach_last_emailed_at']);if($last && $last>strtotime('-'.$minDays.' days'))continue;
        }
        $insert->execute(['campaign'=>$campaignId,'supplier'=>$supplier['id'],'recipient'=>$supplier['email'],'subject'=>outreach_render((string)$campaign['subject_template'],$supplier),'body'=>outreach_render((string)$campaign['body_html'],$supplier),'start'=>$campaign['scheduled_start']?:null]);
        if($insert->rowCount()>0)$added++;
    }
    return $added;
}

function outreach_update_campaign_and_rebuild(PDO $pdo,int $campaignId,array $data): int
{
    if(!outreach_campaign_edit_schema_ready($pdo))throw new RuntimeException('Campaign editing upgrade is not installed.');
    $pdo->beginTransaction();
    try{
        $campaignStmt=$pdo->prepare('SELECT id,status FROM supplier_outreach_campaigns WHERE id=:id FOR UPDATE');$campaignStmt->execute(['id'=>$campaignId]);$campaign=$campaignStmt->fetch();
        if(!$campaign)throw new RuntimeException('Campaign not found.');
        if($campaign['status']==='Completed')throw new RuntimeException('Completed campaigns cannot be edited. Create a new campaign for revised content.');
        $queueStmt=$pdo->prepare('SELECT id,attempts,status FROM supplier_outreach_queue WHERE campaign_id=:id FOR UPDATE');$queueStmt->execute(['id'=>$campaignId]);
        foreach($queueStmt->fetchAll() as $row){
            if((int)$row['attempts']>0 || in_array((string)$row['status'],['Sending','Sent'],true))throw new RuntimeException('This campaign is locked because sending has already started. Pause it and create a new campaign for revised content.');
        }
        $data['id']=$campaignId;
        $pdo->prepare('UPDATE supplier_outreach_campaigns SET name=:name,template_id=:template_id,subject_template=:subject_template,body_html=:body_html,target_supplier_status=:target_supplier_status,target_region=:target_region,daily_limit=:daily_limit,min_days_between_contacts=:min_days_between_contacts,follow_up_days=:follow_up_days,scheduled_start=:scheduled_start WHERE id=:id')->execute($data);
        $pdo->prepare('DELETE FROM supplier_outreach_queue WHERE campaign_id=:id')->execute(['id'=>$campaignId]);
        $added=outreach_queue_campaign($pdo,$campaignId);
        $pdo->commit();return $added;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function outreach_rebuild_campaign_queue(PDO $pdo,int $campaignId): int
{
    $campaign=outreach_campaign_record($pdo,$campaignId);if(!$campaign)throw new RuntimeException('Campaign not found.');
    $data=[
        'name'=>$campaign['name'],'template_id'=>$campaign['template_id'],'subject_template'=>$campaign['subject_template'],'body_html'=>$campaign['body_html'],
        'target_supplier_status'=>$campaign['target_supplier_status'],'target_region'=>$campaign['target_region'],'daily_limit'=>$campaign['daily_limit'],
        'min_days_between_contacts'=>$campaign['min_days_between_contacts'],'follow_up_days'=>$campaign['follow_up_days'],'scheduled_start'=>$campaign['scheduled_start'],
    ];
    return outreach_update_campaign_and_rebuild($pdo,$campaignId,$data);
}

function outreach_process_campaign(PDO $pdo,int $campaignId,?int $requestedLimit=null): array
{
    if(!outreach_mail_ready())throw new RuntimeException('Outbound email is not enabled with the mail transport. Configure and test email first.');
    $stmt=$pdo->prepare('SELECT * FROM supplier_outreach_campaigns WHERE id=:id');$stmt->execute(['id'=>$campaignId]);$campaign=$stmt->fetch();if(!$campaign)throw new RuntimeException('Campaign not found.');
    if($campaign['status']!=='Running')throw new RuntimeException('Only running campaigns can send outreach.');
    if($campaign['scheduled_start'] && strtotime((string)$campaign['scheduled_start'])>time())return ['sent'=>0,'failed'=>0,'remaining'=>0];
    $daily=max(1,min(50,(int)$campaign['daily_limit']));$sentToday=$pdo->prepare("SELECT COUNT(*) FROM supplier_outreach_queue WHERE campaign_id=:id AND status='Sent' AND sent_at>=CURDATE()");$sentToday->execute(['id'=>$campaignId]);$remainingToday=max(0,$daily-(int)$sentToday->fetchColumn());
    $limit=min($remainingToday,max(1,min(20,$requestedLimit??5)));if($limit<1)return ['sent'=>0,'failed'=>0,'remaining'=>0];
    $pdo->prepare("UPDATE supplier_outreach_queue SET status='Failed',last_error='Previous send was interrupted; review before retrying' WHERE campaign_id=:id AND status='Sending' AND updated_at<DATE_SUB(NOW(),INTERVAL 1 HOUR)")->execute(['id'=>$campaignId]);
    $pdo->prepare("UPDATE supplier_outreach_queue q JOIN suppliers s ON s.id=q.supplier_id SET q.status='Skipped',q.last_error='Supplier outreach disabled' WHERE q.campaign_id=:id AND q.status='Pending' AND s.outreach_email_enabled=0")->execute(['id'=>$campaignId]);
    $q=$pdo->prepare("SELECT q.*,s.name,s.contact_name,s.status supplier_status,s.outreach_last_emailed_at,c.follow_up_days,c.min_days_between_contacts,c.name campaign_name FROM supplier_outreach_queue q JOIN suppliers s ON s.id=q.supplier_id JOIN supplier_outreach_campaigns c ON c.id=q.campaign_id WHERE q.campaign_id=:id AND q.status='Pending' AND q.scheduled_at<=NOW() AND s.outreach_email_enabled=1 ORDER BY q.scheduled_at,q.id LIMIT $limit");$q->execute(['id'=>$campaignId]);$rows=$q->fetchAll();$sent=0;$failed=0;
    foreach($rows as $row){
        try{
            $minDays=max(0,(int)$row['min_days_between_contacts']);
            if($minDays>0 && !empty($row['outreach_last_emailed_at'])){
                $last=strtotime((string)$row['outreach_last_emailed_at']);
                if($last && $last>strtotime('-'.$minDays.' days')){$pdo->prepare("UPDATE supplier_outreach_queue SET status='Skipped',last_error='Minimum contact spacing not reached' WHERE id=:id")->execute(['id'=>$row['id']]);continue;}
            }
            $claim=$pdo->prepare("UPDATE supplier_outreach_queue SET status='Sending',attempts=attempts+1,last_error=NULL WHERE id=:id AND status='Pending'");$claim->execute(['id'=>$row['id']]);if($claim->rowCount()!==1)continue;
            $html=mailer_layout('Partnership enquiry',(string)$row['body_html']);
            $ok=mailer_send($pdo,'supplier_outreach_'.$campaignId.'_'.(int)$row['supplier_id'],(string)$row['recipient'],(string)$row['subject'],$html,null,null);
            if(!$ok)throw new RuntimeException('Mail transport returned failure.');
            $pdo->prepare("UPDATE supplier_outreach_queue SET status='Sent',last_error=NULL,sent_at=NOW() WHERE id=:id")->execute(['id'=>$row['id']]);
            try{
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE suppliers SET outreach_last_emailed_at=NOW(),status=CASE WHEN status='Prospect' THEN 'Contacted' ELSE status END WHERE id=:id")->execute(['id'=>$row['supplier_id']]);
                $follow=(new DateTimeImmutable('today'))->modify('+'.max(1,(int)$row['follow_up_days']).' days')->format('Y-m-d');
                $pdo->prepare("INSERT INTO supplier_outreach(supplier_id,contacted_at,channel,subject,notes,outcome,next_follow_up,created_by) VALUES(:supplier,NOW(),'Automated email',:subject,:notes,'Awaiting response',:follow,NULL)")
                    ->execute(['supplier'=>$row['supplier_id'],'subject'=>$row['subject'],'notes'=>'Automated supplier outreach campaign: '.$row['campaign_name'],'follow'=>$follow]);
                $pdo->commit();
            }catch(Throwable $logError){if($pdo->inTransaction())$pdo->rollBack();error_log('Moleqra supplier outreach was sent but CRM logging failed: '.$logError->getMessage());}
            $sent++;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$failed++;$attempts=(int)$row['attempts']+1;$pdo->prepare("UPDATE supplier_outreach_queue SET status=:status,last_error=:error,scheduled_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE) WHERE id=:id")->execute(['status'=>$attempts>=3?'Failed':'Pending','error'=>substr($e->getMessage(),0,1000),'id'=>$row['id']]);}
    }
    $pdo->prepare('UPDATE supplier_outreach_campaigns SET last_processed_at=NOW() WHERE id=:id')->execute(['id'=>$campaignId]);
    $remaining=$pdo->prepare("SELECT COUNT(*) FROM supplier_outreach_queue WHERE campaign_id=:id AND status='Pending'");$remaining->execute(['id'=>$campaignId]);$left=(int)$remaining->fetchColumn();if($left===0)$pdo->prepare("UPDATE supplier_outreach_campaigns SET status='Completed' WHERE id=:id AND status='Running'")->execute(['id'=>$campaignId]);
    return ['sent'=>$sent,'failed'=>$failed,'remaining'=>$left];
}

function outreach_process_running(PDO $pdo,int $batchLimit=5): array
{
    $rows=$pdo->query("SELECT id FROM supplier_outreach_campaigns WHERE status='Running' AND (scheduled_start IS NULL OR scheduled_start<=NOW()) ORDER BY id LIMIT 10")->fetchAll();$result=['sent'=>0,'failed'=>0,'campaigns'=>0];
    foreach($rows as $row){$r=outreach_process_campaign($pdo,(int)$row['id'],$batchLimit);$result['sent']+=$r['sent'];$result['failed']+=$r['failed'];$result['campaigns']++;}
    return $result;
}
