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

function outreach_mail_ready(): bool
{
    $cfg=mail_config();return !empty($cfg['enabled']) && strtolower((string)($cfg['transport']??''))==='mail';
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

function outreach_queue_campaign(PDO $pdo,int $campaignId): int
{
    $stmt=$pdo->prepare('SELECT c.*,t.subject_template,t.body_html FROM supplier_outreach_campaigns c JOIN supplier_outreach_templates t ON t.id=c.template_id WHERE c.id=:id');
    $stmt->execute(['id'=>$campaignId]);$campaign=$stmt->fetch();if(!$campaign)throw new RuntimeException('Campaign not found.');
    $where=['s.email IS NOT NULL','s.email<>\'\'','s.outreach_email_enabled=1'];$params=[];
    if(trim((string)$campaign['target_supplier_status'])!==''){$where[]='s.status=:status';$params['status']=$campaign['target_supplier_status'];}
    if(trim((string)$campaign['target_region'])!==''){$where[]='s.region=:region';$params['region']=$campaign['target_region'];}
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
