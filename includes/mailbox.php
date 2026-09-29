<?php

declare(strict_types=1);

require_once __DIR__.'/communications.php';
require_once __DIR__.'/mailer.php';

function mailbox_config(): array
{
    $mail=mail_config();
    $cfg=is_array($mail['inbound']??null)?$mail['inbound']:[];
    $protocol=strtolower(trim((string)($cfg['protocol']??'pop3')));
    if($protocol!=='pop3')$protocol='pop3';
    $enc=strtolower(trim((string)($cfg['encryption']??'none')));
    if(!in_array($enc,['none','tls','ssl'],true))$enc='none';
    return [
        'enabled'=>(bool)($cfg['enabled']??false),
        'protocol'=>$protocol,
        'host'=>trim((string)($cfg['host']??'')),
        'port'=>(int)($cfg['port']??($enc==='ssl'?995:110)),
        'encryption'=>$enc,
        'username'=>(string)($cfg['username']??''),
        'password'=>(string)($cfg['password']??''),
        'timeout'=>max(3,min(60,(int)($cfg['timeout']??15))),
        'verify_peer'=>(bool)($cfg['verify_peer']??true),
        'verify_peer_name'=>(bool)($cfg['verify_peer_name']??true),
        'allow_self_signed'=>(bool)($cfg['allow_self_signed']??false),
        'max_messages'=>max(1,min(200,(int)($cfg['max_messages']??50))),
    ];
}

function mailbox_ready(): bool
{
    $cfg=mailbox_config();
    return $cfg['enabled']
        && function_exists('stream_socket_client')
        && $cfg['host']!==''
        && $cfg['username']!==''
        && $cfg['password']!=='';
}

function pop3_read_line($stream): string
{
    $line=fgets($stream,8192);
    if($line===false)throw new RuntimeException('POP3 connection closed unexpectedly.');
    return rtrim($line,"\r\n");
}

function pop3_expect_ok($stream,string $context): string
{
    $line=pop3_read_line($stream);
    if(!str_starts_with($line,'+OK'))throw new RuntimeException($context.' failed: '.$line);
    return $line;
}

function pop3_write($stream,string $line): void
{
    $payload=$line."\r\n";$length=strlen($payload);$offset=0;
    while($offset<$length){
        $written=fwrite($stream,substr($payload,$offset));
        if($written===false||$written===0)throw new RuntimeException('POP3 connection write failed.');
        $offset+=$written;
    }
}

function pop3_command($stream,string $command,string $context): string
{
    pop3_write($stream,$command);
    return pop3_expect_ok($stream,$context);
}

function pop3_multiline($stream): array
{
    $lines=[];
    while(true){
        $line=pop3_read_line($stream);
        if($line==='.')break;
        if(str_starts_with($line,'..'))$line=substr($line,1);
        $lines[]=$line;
    }
    return $lines;
}

function pop3_command_multiline($stream,string $command,string $context): array
{
    pop3_write($stream,$command);
    pop3_expect_ok($stream,$context);
    return pop3_multiline($stream);
}

function pop3_open(array $cfg): array
{
    if(!function_exists('stream_socket_client'))throw new RuntimeException('PHP stream sockets are unavailable.');
    if($cfg['host']==='')throw new RuntimeException('POP3 host is not configured.');
    if($cfg['username']===''||$cfg['password']==='')throw new RuntimeException('POP3 username/password are not configured.');

    $scheme=$cfg['encryption']==='ssl'?'ssl':'tcp';
    $target=$scheme.'://'.$cfg['host'].':'.$cfg['port'];
    $context=stream_context_create(['ssl'=>[
        'verify_peer'=>$cfg['verify_peer'],
        'verify_peer_name'=>$cfg['verify_peer_name'],
        'allow_self_signed'=>$cfg['allow_self_signed'],
        'peer_name'=>$cfg['host'],
        'SNI_enabled'=>true,
    ]]);
    $errno=0;$errstr='';
    $stream=@stream_socket_client($target,$errno,$errstr,$cfg['timeout'],STREAM_CLIENT_CONNECT,$context);
    if(!is_resource($stream))throw new RuntimeException('POP3 connection failed'.($errstr!==''?': '.$errstr:' (error '.$errno.')'));
    stream_set_timeout($stream,$cfg['timeout']);

    try{
        pop3_expect_ok($stream,'POP3 greeting');
        if($cfg['encryption']==='tls'){
            pop3_command($stream,'STLS','POP3 STLS');
            $crypto=@stream_socket_enable_crypto($stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if($crypto!==true)throw new RuntimeException('POP3 TLS negotiation failed.');
        }
        pop3_command($stream,'USER '.$cfg['username'],'POP3 username');
        pop3_command($stream,'PASS '.$cfg['password'],'POP3 password');
        return ['stream'=>$stream,'config'=>$cfg];
    }catch(Throwable $e){
        @fclose($stream);
        throw $e;
    }
}

function pop3_close($stream): void
{
    if(!is_resource($stream))return;
    try{pop3_command($stream,'QUIT','POP3 quit');}catch(Throwable $e){}
    @fclose($stream);
}

function pop3_stat($stream): array
{
    $line=pop3_command($stream,'STAT','POP3 STAT');
    if(!preg_match('/^\+OK\s+(\d+)\s+(\d+)/',$line,$m))return ['count'=>0,'bytes'=>0];
    return ['count'=>(int)$m[1],'bytes'=>(int)$m[2]];
}

function pop3_uidl($stream): array
{
    $lines=pop3_command_multiline($stream,'UIDL','POP3 UIDL');
    $out=[];
    foreach($lines as $line){
        if(preg_match('/^(\d+)\s+(.+)$/',$line,$m))$out[(int)$m[1]]=trim($m[2]);
    }
    return $out;
}

function mailbox_decode_header(string $value): string
{
    $value=trim($value);
    if($value==='')return '';
    if(function_exists('iconv_mime_decode')){
        $decoded=@iconv_mime_decode($value,ICONV_MIME_DECODE_CONTINUE_ON_ERROR,'UTF-8');
        if(is_string($decoded)&&$decoded!=='')return trim($decoded);
    }
    return $value;
}

function mailbox_parse_headers(string $raw): array
{
    $headers=[];$current=null;
    foreach(preg_split('/\r?\n/',$raw)?:[] as $line){
        if($line==='' )continue;
        if(($line[0]===' '||$line[0]==="\t")&&$current!==null){
            $headers[$current].=' '.trim($line);
            continue;
        }
        $pos=strpos($line,':');
        if($pos===false)continue;
        $name=strtolower(trim(substr($line,0,$pos)));
        $value=trim(substr($line,$pos+1));
        if(isset($headers[$name]))$headers[$name].=', '.$value;else $headers[$name]=$value;
        $current=$name;
    }
    return $headers;
}

function mailbox_extract_email(string $value): string
{
    if(preg_match('/<([^>]+)>/',$value,$m))$value=$m[1];
    if(preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',$value,$m))return strtolower($m[0]);
    return '';
}

function mailbox_decode_transfer(string $body,string $encoding): string
{
    $encoding=strtolower(trim($encoding));
    if($encoding==='base64'){
        $decoded=base64_decode(preg_replace('/\s+/','',$body)??$body,true);
        return $decoded===false?'':$decoded;
    }
    if($encoding==='quoted-printable')return quoted_printable_decode($body);
    return $body;
}

function mailbox_extract_plain_text(string $headersRaw,string $body): string
{
    $headers=mailbox_parse_headers($headersRaw);
    $contentType=strtolower((string)($headers['content-type']??'text/plain'));
    $encoding=(string)($headers['content-transfer-encoding']??'');

    if(str_starts_with($contentType,'multipart/')){
        if(preg_match('/boundary\s*=\s*(?:"([^"]+)"|([^;\s]+))/i',(string)($headers['content-type']??''),$m)){
            $boundary=$m[1]!==''?$m[1]:$m[2];
            $parts=preg_split('/--'.preg_quote($boundary,'/').'(?:--)?\r?\n?/',$body)?:[];
            $htmlFallback='';
            foreach($parts as $part){
                $part=ltrim($part,"\r\n");
                if($part===''||$part==='--')continue;
                [$ph,$pb]=array_pad(preg_split('/\r?\n\r?\n/',$part,2),2,'');
                $pHeaders=mailbox_parse_headers($ph);
                $pType=strtolower((string)($pHeaders['content-type']??'text/plain'));
                if(str_starts_with($pType,'multipart/')){
                    $nested=mailbox_extract_plain_text($ph,$pb);
                    if($nested!=='')return $nested;
                    continue;
                }
                $decoded=mailbox_decode_transfer($pb,(string)($pHeaders['content-transfer-encoding']??''));
                if(str_starts_with($pType,'text/plain'))return trim($decoded);
                if($htmlFallback===''&&str_starts_with($pType,'text/html'))$htmlFallback=trim(html_entity_decode(strip_tags($decoded),ENT_QUOTES|ENT_HTML5,'UTF-8'));
            }
            return $htmlFallback;
        }
    }

    $decoded=mailbox_decode_transfer($body,$encoding);
    if(str_starts_with($contentType,'text/html'))$decoded=html_entity_decode(strip_tags($decoded),ENT_QUOTES|ENT_HTML5,'UTF-8');
    return trim($decoded);
}

function mailbox_parse_message(string $raw): array
{
    [$headersRaw,$body]=array_pad(preg_split('/\r?\n\r?\n/',$raw,2),2,'');
    $headers=mailbox_parse_headers($headersRaw);
    $dateRaw=(string)($headers['date']??'');
    $timestamp=$dateRaw!==''?strtotime($dateRaw):false;
    return [
        'from'=>mailbox_extract_email((string)($headers['from']??'')),
        'to'=>mailbox_extract_email((string)($headers['to']??'')),
        'subject'=>mailbox_decode_header((string)($headers['subject']??'Inbound email')),
        'message_id'=>trim((string)($headers['message-id']??'')),
        'in_reply_to'=>trim((string)($headers['in-reply-to']??'')),
        'body'=>mailbox_extract_plain_text($headersRaw,$body),
        'received_at'=>$timestamp?date('Y-m-d H:i:s',$timestamp):date('Y-m-d H:i:s'),
    ];
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
    $started=microtime(true);$cfg=mailbox_config();
    if(!$cfg['enabled'])return ['ok'=>false,'detail'=>'Inbound POP3 sync is disabled in config/mail.php.','elapsed_ms'=>0];
    try{
        $conn=pop3_open($cfg);$stat=pop3_stat($conn['stream']);pop3_close($conn['stream']);
        return ['ok'=>true,'detail'=>'Connected and authenticated to POP3 '.$cfg['host'].':'.$cfg['port'].' using '.$cfg['encryption'].' · '.$stat['count'].' message'.($stat['count']===1?'':'s').' in mailbox.','elapsed_ms'=>(int)round((microtime(true)-$started)*1000)];
    }catch(Throwable $e){
        return ['ok'=>false,'detail'=>$e->getMessage(),'elapsed_ms'=>(int)round((microtime(true)-$started)*1000)];
    }
}

function mailbox_sync(PDO $pdo): array
{
    if(!communications_schema_ready($pdo))throw new RuntimeException('Communications database upgrade is not installed.');
    $cfg=mailbox_config();if(!$cfg['enabled'])throw new RuntimeException('Inbound POP3 sync is disabled.');
    $conn=pop3_open($cfg);$stream=$conn['stream'];$imported=0;$skipped=0;$failed=0;
    try{
        $uidls=pop3_uidl($stream);
        if(!$uidls)return ['imported'=>0,'skipped'=>0,'failed'=>0];
        krsort($uidls);
        $selected=array_slice($uidls,0,$cfg['max_messages'],true);
        ksort($selected);
        foreach($selected as $msgNo=>$uid){
            try{
                $externalId='pop3:'.hash('sha256',$cfg['host'].'|'.$cfg['username'].'|'.$uid);
                $s=$pdo->prepare('SELECT COUNT(*) FROM communication_messages WHERE external_message_id=:id');$s->execute(['id'=>$externalId]);
                if((int)$s->fetchColumn()>0){$skipped++;continue;}

                $lines=pop3_command_multiline($stream,'RETR '.(int)$msgNo,'POP3 RETR');
                $raw=implode("\r\n",$lines);
                $parsed=mailbox_parse_message($raw);
                $from=$parsed['from'];if(!filter_var($from,FILTER_VALIDATE_EMAIL)){$skipped++;continue;}
                $subject=$parsed['subject']!==''?$parsed['subject']:'Inbound email';
                $body=$parsed['body']!==''?$parsed['body']:'[No readable text body available]';
                $thread=mailbox_thread_for_inbound($pdo,$from,$subject);
                communication_add_message($pdo,$thread,[
                    'direction'=>'Inbound','channel'=>'Email','sender_email'=>$from,'recipient_email'=>$parsed['to']?:$cfg['username'],'subject'=>$subject,
                    'body_text'=>$body,'transport'=>'pop3','status'=>'Received','external_message_id'=>$externalId,
                    'in_reply_to'=>$parsed['in_reply_to']?:null,'received_at'=>$parsed['received_at']
                ]);
                $imported++;
            }catch(Throwable $e){$failed++;error_log('Moleqra POP3 import failed: '.$e->getMessage());}
        }
    }finally{pop3_close($stream);}
    return ['imported'=>$imported,'skipped'=>$skipped,'failed'=>$failed];
}
