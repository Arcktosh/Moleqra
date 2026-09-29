<?php

declare(strict_types=1);

require_once __DIR__.'/communications.php';
require_once __DIR__.'/mailer.php';

function mailbox_config(): array
{
    $mail=mail_config();$cfg=is_array($mail['inbound']??null)?$mail['inbound']:[];
    $enc=strtolower(trim((string)($cfg['encryption']??'ssl')));
    if(!in_array($enc,['ssl','tls','none'],true))$enc='ssl';
    return [
        'enabled'=>(bool)($cfg['enabled']??false),
        'host'=>trim((string)($cfg['host']??'')),
        'port'=>(int)($cfg['port']??($enc==='ssl'?993:143)),
        'encryption'=>$enc,
        'username'=>(string)($cfg['username']??''),
        'password'=>(string)($cfg['password']??''),
        'folder'=>trim((string)($cfg['folder']??'INBOX'))?:'INBOX',
        'validate_cert'=>(bool)($cfg['validate_cert']??true),
        'max_messages'=>max(1,min(200,(int)($cfg['max_messages']??50))),
    ];
}

function mailbox_ready(): bool
{
    $cfg=mailbox_config();
    return $cfg['enabled'] && function_exists('imap_open') && $cfg['host']!=='' && $cfg['username']!=='' && $cfg['password']!=='';
}

function mailbox_imap_string(array $cfg): string
{
    $flags='/imap';
    if($cfg['encryption']==='ssl')$flags.='/ssl';
    elseif($cfg['encryption']==='tls')$flags.='/tls';
    else $flags.='/notls';
    if(!$cfg['validate_cert'])$flags.='/novalidate-cert';
    return '{'.$cfg['host'].':'.$cfg['port'].$flags.'}'.$cfg['folder'];
}

function mailbox_decode_header(string $value): string
{
    if(function_exists('imap_utf8'))$value=(string)imap_utf8($value);
    return trim($value);
}

function mailbox_address($address): string
{
    if(!$address)return '';
    $mailbox=(string)($address->mailbox??'');$host=(string)($address->host??'');
    return ($mailbox!==''&&$host!=='')?strtolower($mailbox.'@'.$host):'';
}

function mailbox_part_text($imap,int $msgNo,$structure,string $partNo=''): string
{
    $encoding=(int)($structure->encoding??0);
    $type=(int)($structure->type??0);
    $subtype=strtoupper((string)($structure->subtype??''));
    if($type===0 && $subtype==='PLAIN'){
        $raw=$partNo===''?imap_body($imap,$msgNo,FT_PEEK):imap_fetchbody($imap,$msgNo,$partNo,FT_PEEK);
        if($encoding===3)$raw=base64_decode($raw,true)?:'';
        elseif($encoding===4)$raw=quoted_printable_decode($raw);
        return trim((string)$raw);
    }
    if(!empty($structure->parts)){
        foreach($structure->parts as $i=>$part){
            $text=mailbox_part_text($imap,$msgNo,$part,$partNo===''?(string)($i+1):$partNo.'.'.($i+1));
            if($text!=='')return $text;
        }
    }
    return '';
}

function mailbox_thread_for_inbound(PDO $pdo,string $email,string $subject): int
{
    $party=communication_party_from_email($pdo,$email);
    $thread=communication_find_thread($pdo,$email,$subject,$party['customer_id']??null,$party['supplier_id']??null,null);
    if($thread)return $thread;
    return communication_create_thread($pdo,[
        'email'=>$email,'contact_name'=>$party['contact_name']??null,'customer_id'=>$party['customer_id']??null,'supplier_id'=>$party['supplier_id']??null,
        'party_type'=>$party['party_type']??'contact','subject'=>$subject!==''?$subject:'Inbound email','source'=>'Mailbox'
    ]);
}

function mailbox_probe(): array
{
    $started=microtime(true);
    if(!function_exists('imap_open'))return ['ok'=>false,'detail'=>'PHP IMAP extension is not available on this host.','elapsed_ms'=>0];
    $cfg=mailbox_config();
    if(!$cfg['enabled'])return ['ok'=>false,'detail'=>'Inbound mailbox sync is disabled in config/mail.php.','elapsed_ms'=>0];
    if($cfg['host']===''||$cfg['username']===''||$cfg['password']==='')return ['ok'=>false,'detail'=>'Inbound mailbox credentials are incomplete.','elapsed_ms'=>0];
    $imap=@imap_open(mailbox_imap_string($cfg),$cfg['username'],$cfg['password'],OP_READONLY);
    if(!$imap)return ['ok'=>false,'detail'=>'IMAP connection failed: '.(imap_last_error()?:'unknown error'),'elapsed_ms'=>(int)round((microtime(true)-$started)*1000)];
    $count=(int)imap_num_msg($imap);@imap_close($imap);
    return ['ok'=>true,'detail'=>'Connected to '.$cfg['host'].':'.$cfg['port'].' · '.$cfg['folder'].' · '.$count.' message'.($count===1?'':'s').' currently in folder.','elapsed_ms'=>(int)round((microtime(true)-$started)*1000)];
}

function mailbox_sync(PDO $pdo): array
{
    if(!communications_schema_ready($pdo))throw new RuntimeException('Communications database upgrade is not installed.');
    if(!function_exists('imap_open'))throw new RuntimeException('PHP IMAP extension is not available on this host.');
    $cfg=mailbox_config();if(!$cfg['enabled'])throw new RuntimeException('Inbound mailbox sync is disabled.');
    $mailbox=mailbox_imap_string($cfg);
    $imap=@imap_open($mailbox,$cfg['username'],$cfg['password'],OP_READONLY);
    if(!$imap)throw new RuntimeException('IMAP connection failed: '.(imap_last_error()?:'unknown error'));
    $imported=0;$skipped=0;$failed=0;
    try{
        $ids=imap_search($imap,'ALL',SE_UID)?:[];
        rsort($ids);$ids=array_slice($ids,0,$cfg['max_messages']);
        foreach(array_reverse($ids) as $uid){
            try{
                $msgNo=imap_msgno($imap,$uid);if(!$msgNo){$skipped++;continue;}
                $header=imap_headerinfo($imap,$msgNo);if(!$header){$failed++;continue;}
                $messageId=trim((string)($header->message_id??''));if($messageId!==''){
                    $s=$pdo->prepare('SELECT COUNT(*) FROM communication_messages WHERE external_message_id=:id');$s->execute(['id'=>$messageId]);
                    if((int)$s->fetchColumn()>0){$skipped++;continue;}
                }
                $from=mailbox_address($header->from[0]??null);if(!filter_var($from,FILTER_VALIDATE_EMAIL)){$skipped++;continue;}
                $subject=mailbox_decode_header((string)($header->subject??'Inbound email'));
                $structure=imap_fetchstructure($imap,$msgNo);$body=$structure?mailbox_part_text($imap,$msgNo,$structure):trim((string)imap_body($imap,$msgNo,FT_PEEK));
                if($body==='')$body='[No plain-text body available]';
                $thread=mailbox_thread_for_inbound($pdo,$from,$subject);
                communication_add_message($pdo,$thread,[
                    'direction'=>'Inbound','channel'=>'Email','sender_email'=>$from,'recipient_email'=>$cfg['username'],'subject'=>$subject,
                    'body_text'=>$body,'transport'=>'imap','status'=>'Received','external_message_id'=>$messageId?:null,
                    'in_reply_to'=>trim((string)($header->in_reply_to??''))?:null,
                    'received_at'=>!empty($header->udate)?date('Y-m-d H:i:s',(int)$header->udate):date('Y-m-d H:i:s')
                ]);
                $imported++;
            }catch(Throwable $e){$failed++;error_log('Moleqra IMAP import failed: '.$e->getMessage());}
        }
    }finally{@imap_close($imap);}
    return ['imported'=>$imported,'skipped'=>$skipped,'failed'=>$failed];
}
