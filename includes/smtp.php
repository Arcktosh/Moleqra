<?php

declare(strict_types=1);

/**
 * Minimal native SMTP client for shared-hosting PHP.
 * No Composer/runtime dependency. Supports plain SMTP, STARTTLS and implicit TLS,
 * plus AUTH LOGIN / AUTH PLAIN.
 */

function smtp_config(array $mailConfig): array
{
    $smtp=is_array($mailConfig['smtp']??null)?$mailConfig['smtp']:[];
    $host=trim((string)($smtp['host']??''));
    $encryption=strtolower(trim((string)($smtp['encryption']??'tls')));
    if(!in_array($encryption,['tls','ssl','none'],true))$encryption='tls';
    $port=(int)($smtp['port']??($encryption==='ssl'?465:587));
    return [
        'host'=>$host,
        'port'=>$port>0?$port:($encryption==='ssl'?465:587),
        'encryption'=>$encryption,
        'auth'=>(bool)($smtp['auth']??true),
        'auth_mode'=>strtolower(trim((string)($smtp['auth_mode']??'auto'))),
        'username'=>(string)($smtp['username']??''),
        'password'=>(string)($smtp['password']??''),
        'timeout'=>max(3,min(60,(int)($smtp['timeout']??15))),
        'verify_peer'=>(bool)($smtp['verify_peer']??true),
        'verify_peer_name'=>(bool)($smtp['verify_peer_name']??true),
        'allow_self_signed'=>(bool)($smtp['allow_self_signed']??false),
        'helo_name'=>trim((string)($smtp['helo_name']??'')),
    ];
}

function smtp_sanitize_header(string $value): string
{
    return trim(str_replace(["\r","\n"],' ',$value));
}

function smtp_header_text(string $value): string
{
    $value=smtp_sanitize_header($value);
    return function_exists('mb_encode_mimeheader')?mb_encode_mimeheader($value,'UTF-8','B',"\r\n"):$value;
}

function smtp_read_response($stream): array
{
    $lines=[];$code=0;
    while(!feof($stream)){
        $line=fgets($stream,8192);
        if($line===false)break;
        $line=rtrim($line,"\r\n");$lines[]=$line;
        if(preg_match('/^(\d{3})([ -])(.*)$/',$line,$m)){
            $code=(int)$m[1];
            if($m[2]===' ')break;
        }
    }
    return ['code'=>$code,'lines'=>$lines,'message'=>implode("\n",$lines)];
}

function smtp_expect($stream,array $codes,string $context): array
{
    $response=smtp_read_response($stream);
    if(!in_array($response['code'],$codes,true))throw new RuntimeException($context.' failed: '.$response['message']);
    return $response;
}

function smtp_write($stream,string $line): void
{
    $payload=$line."\r\n";$length=strlen($payload);$offset=0;
    while($offset<$length){
        $written=fwrite($stream,substr($payload,$offset));
        if($written===false||$written===0)throw new RuntimeException('SMTP connection write failed.');
        $offset+=$written;
    }
}

function smtp_command($stream,string $command,array $codes,string $context): array
{
    smtp_write($stream,$command);
    return smtp_expect($stream,$codes,$context);
}

function smtp_capabilities(array $response): array
{
    $caps=[];
    foreach($response['lines'] as $line){
        if(!preg_match('/^250[ -](.*)$/i',$line,$m))continue;
        $part=trim($m[1]);
        if($part==='')continue;
        [$name,$rest]=array_pad(preg_split('/\s+/',$part,2),2,'');
        $caps[strtoupper($name)]=trim($rest);
    }
    return $caps;
}

function smtp_open(array $mailConfig): array
{
    if(!function_exists('stream_socket_client'))throw new RuntimeException('PHP stream sockets are unavailable.');
    $cfg=smtp_config($mailConfig);
    if($cfg['host']==='')throw new RuntimeException('SMTP host is not configured.');
    if($cfg['auth'] && (trim($cfg['username'])===''||$cfg['password']===''))throw new RuntimeException('SMTP username/password are not configured.');

    $scheme=$cfg['encryption']==='ssl'?'ssl':'tcp';
    $target=$scheme.'://'.$cfg['host'].':'.$cfg['port'];
    $contextOptions=['ssl'=>[
        'verify_peer'=>$cfg['verify_peer'],
        'verify_peer_name'=>$cfg['verify_peer_name'],
        'allow_self_signed'=>$cfg['allow_self_signed'],
        'peer_name'=>$cfg['host'],
        'SNI_enabled'=>true,
    ]];
    $context=stream_context_create($contextOptions);
    $errno=0;$errstr='';
    $stream=@stream_socket_client($target,$errno,$errstr,$cfg['timeout'],STREAM_CLIENT_CONNECT,$context);
    if(!is_resource($stream))throw new RuntimeException('SMTP connection failed'.($errstr!==''?': '.$errstr:' (error '.$errno.')'));
    stream_set_timeout($stream,$cfg['timeout']);

    try{
        smtp_expect($stream,[220],'SMTP greeting');
        $helo=$cfg['helo_name']!==''?$cfg['helo_name']:(gethostname()?:'localhost');
        $helo=preg_replace('/[^A-Za-z0-9.\-]/','',$helo)?:'localhost';
        $ehlo=smtp_command($stream,'EHLO '.$helo,[250],'EHLO');
        $caps=smtp_capabilities($ehlo);

        if($cfg['encryption']==='tls'){
            if(!isset($caps['STARTTLS']))throw new RuntimeException('SMTP server does not advertise STARTTLS.');
            smtp_command($stream,'STARTTLS',[220],'STARTTLS');
            $crypto=@stream_socket_enable_crypto($stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if($crypto!==true)throw new RuntimeException('SMTP TLS negotiation failed.');
            $ehlo=smtp_command($stream,'EHLO '.$helo,[250],'EHLO after STARTTLS');
            $caps=smtp_capabilities($ehlo);
        }

        if($cfg['auth']){
            $advertised=strtoupper((string)($caps['AUTH']??''));
            $mode=$cfg['auth_mode'];
            if(!in_array($mode,['auto','login','plain'],true))$mode='auto';
            if($mode==='auto'){
                if(str_contains($advertised,'PLAIN'))$mode='plain';
                elseif(str_contains($advertised,'LOGIN'))$mode='login';
                else $mode='login';
            }
            if($mode==='plain'){
                $token=base64_encode("\0".$cfg['username']."\0".$cfg['password']);
                smtp_command($stream,'AUTH PLAIN '.$token,[235],'SMTP authentication');
            }else{
                smtp_command($stream,'AUTH LOGIN',[334],'SMTP AUTH LOGIN');
                smtp_command($stream,base64_encode($cfg['username']),[334],'SMTP username');
                smtp_command($stream,base64_encode($cfg['password']),[235],'SMTP password');
            }
        }
        return ['stream'=>$stream,'config'=>$cfg,'capabilities'=>$caps];
    }catch(Throwable $e){
        @fclose($stream);
        throw $e;
    }
}

function smtp_close($stream): void
{
    if(!is_resource($stream))return;
    try{smtp_command($stream,'QUIT',[221,250],'QUIT');}catch(Throwable $e){}
    @fclose($stream);
}

function smtp_probe(array $mailConfig): array
{
    $started=microtime(true);
    try{
        $conn=smtp_open($mailConfig);$cfg=$conn['config'];$caps=$conn['capabilities'];smtp_close($conn['stream']);
        return [
            'ok'=>true,
            'detail'=>'Connected and authenticated to '.$cfg['host'].':'.$cfg['port'].' using '.$cfg['encryption'].'.',
            'capabilities'=>array_keys($caps),
            'elapsed_ms'=>(int)round((microtime(true)-$started)*1000),
        ];
    }catch(Throwable $e){
        return ['ok'=>false,'detail'=>$e->getMessage(),'capabilities'=>[],'elapsed_ms'=>(int)round((microtime(true)-$started)*1000)];
    }
}

function smtp_send(array $mailConfig,string $recipient,string $subject,string $html,string $fromEmail,string $fromName='',string $replyTo=''): void
{
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Recipient email address is invalid.');
    if(!filter_var($fromEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('From email address is invalid.');

    $conn=smtp_open($mailConfig);$stream=$conn['stream'];
    try{
        smtp_command($stream,'MAIL FROM:<'.$fromEmail.'>',[250],'MAIL FROM');
        smtp_command($stream,'RCPT TO:<'.$recipient.'>',[250,251],'RCPT TO');
        smtp_command($stream,'DATA',[354],'DATA');

        $safeFromName=smtp_header_text($fromName!==''?$fromName:$fromEmail);
        $safeSubject=smtp_header_text($subject);
        $domain=substr(strrchr($fromEmail,'@')?:'@localhost',1)?:'localhost';
        $messageId='<'.bin2hex(random_bytes(12)).'.'.time().'@'.preg_replace('/[^A-Za-z0-9.\-]/','',$domain).'>';
        $headers=[
            'Date: '.date(DATE_RFC2822),
            'Message-ID: '.$messageId,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: '.$safeFromName.' <'.$fromEmail.'>',
            'To: <'.$recipient.'>',
            'Subject: '.$safeSubject,
        ];
        if(filter_var($replyTo,FILTER_VALIDATE_EMAIL))$headers[]='Reply-To: '.$replyTo;

        $body=str_replace(["\r\n","\r"],"\n",$html);
        $body=str_replace("\n","\r\n",$body);
        $body=preg_replace('/(?m)^\./','..',$body)??$body;
        $message=implode("\r\n",$headers)."\r\n\r\n".$body."\r\n.";
        smtp_write($stream,$message);
        smtp_expect($stream,[250],'Message delivery');
    }finally{
        smtp_close($stream);
    }
}
