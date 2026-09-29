<?php

declare(strict_types=1);

require_once __DIR__.'/account_auth.php';

function community_schema_ready(?PDO $pdo=null): bool
{
    $pdo ??= db();if(!$pdo)return false;
    foreach(['forum_customer_moderation','forum_topics','forum_posts'] as $t)if(!db_table_exists($t,$pdo))return false;
    return true;
}

function community_member_label(int $customerId): string
{
    return 'Member '.strtoupper(substr(hash('sha256','moleqra-forum-'.$customerId),0,8));
}

function community_forum_status(PDO $pdo,int $customerId): array
{
    $stmt=$pdo->prepare("SELECT * FROM forum_customer_moderation WHERE customer_id=:id");$stmt->execute(['id'=>$customerId]);$row=$stmt->fetch();
    return $row?:['customer_id'=>$customerId,'forum_status'=>'Active','spam_score'=>0,'block_reason'=>null,'blocked_at'=>null];
}

function community_content_violation(string $text): ?string
{
    $text=trim($text);
    if($text==='')return 'Post content is required.';
    if(strlen($text)>8000)return 'Posts are limited to 8,000 characters.';
    $patterns=[
      '/\bhttps?:\/\/\S+/i'=>'External links are not allowed.',
      '/\bwww\.\S+/i'=>'External links are not allowed.',
      '/\b[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}\b/i'=>'Email addresses are not allowed.',
      '/(?:\+?\d[\d\s().-]{7,}\d)/'=>'Phone or contact numbers are not allowed.',
      '/\b(?:whatsapp|telegram|signal|discord|instagram|facebook|tiktok|wechat|email me|contact me|dm me|message me)\b/i'=>'Off-platform contact sharing is not allowed.',
      '/\b(?:dose|dosage|inject|injection|subcutaneous|intramuscular|take\s+\d|mg\s*(?:daily|weekly|per)|iu\s*(?:daily|weekly|per))\b/i'=>'Dosing, administration, or human-use instructions are not allowed in the research forum.',
    ];
    foreach($patterns as $pattern=>$message)if(preg_match($pattern,$text))return $message;
    return null;
}

function community_record_violation(PDO $pdo,int $customerId,string $reason): void
{
    $pdo->prepare("INSERT INTO forum_customer_moderation(customer_id,spam_score) VALUES(:id,1) ON DUPLICATE KEY UPDATE spam_score=spam_score+1")->execute(['id'=>$customerId]);
    $state=community_forum_status($pdo,$customerId);
    if((int)$state['spam_score']>=4 && $state['forum_status']==='Active'){
        $pdo->prepare("UPDATE forum_customer_moderation SET forum_status='Suspended',block_reason=:reason,blocked_at=NOW() WHERE customer_id=:id")->execute(['reason'=>'Automatic spam protection after repeated rejected submissions: '.$reason,'id'=>$customerId]);
    }
}

function community_assert_can_post(PDO $pdo,int $customerId): void
{
    $state=community_forum_status($pdo,$customerId);
    if(in_array($state['forum_status'],['Suspended','Blocked'],true))throw new RuntimeException('Your community posting access is currently restricted.');
    $rate=$pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE customer_id=:id AND created_at>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)");$rate->execute(['id'=>$customerId]);
    if((int)$rate->fetchColumn()>=8)throw new RuntimeException('You are posting too quickly. Please try again later.');
}

function community_slug(string $title): string
{
    $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$title)??'','-'));if($slug==='')$slug='topic';
    return substr($slug,0,150).'-'.substr(bin2hex(random_bytes(4)),0,8);
}

function community_product_topic(PDO $pdo,int $productId,string $productName): int
{
    $s=$pdo->prepare('SELECT id FROM forum_topics WHERE product_id=:id LIMIT 1');$s->execute(['id'=>$productId]);$id=(int)$s->fetchColumn();if($id)return $id;
    $pdo->prepare("INSERT INTO forum_topics(product_id,title,slug,status,is_product_topic,last_post_at) VALUES(:product,:title,:slug,'Open',1,NOW())")->execute(['product'=>$productId,'title'=>$productName.' research discussion','slug'=>community_slug($productName.' research discussion')]);
    return (int)$pdo->lastInsertId();
}

function community_create_topic(PDO $pdo,int $customerId,string $title,string $body): int
{
    community_assert_can_post($pdo,$customerId);$title=trim($title);if(strlen($title)<5||strlen($title)>180)throw new RuntimeException('Topic title must be between 5 and 180 characters.');
    if($v=community_content_violation($title)){community_record_violation($pdo,$customerId,$v);throw new RuntimeException($v);}
    if($v=community_content_violation($body)){community_record_violation($pdo,$customerId,$v);throw new RuntimeException($v);}
    $pdo->beginTransaction();try{
      $pdo->prepare("INSERT INTO forum_topics(created_by_customer_id,title,slug,status,is_product_topic,last_post_at) VALUES(:customer,:title,:slug,'Open',0,NOW())")->execute(['customer'=>$customerId,'title'=>$title,'slug'=>community_slug($title)]);$topic=(int)$pdo->lastInsertId();
      $pdo->prepare("INSERT INTO forum_posts(topic_id,customer_id,body,status) VALUES(:topic,:customer,:body,'Published')")->execute(['topic'=>$topic,'customer'=>$customerId,'body'=>trim($body)]);
      $pdo->commit();return $topic;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function community_add_post(PDO $pdo,int $customerId,int $topicId,string $body): void
{
    community_assert_can_post($pdo,$customerId);
    $s=$pdo->prepare("SELECT status FROM forum_topics WHERE id=:id");$s->execute(['id'=>$topicId]);$status=$s->fetchColumn();if($status!=='Open')throw new RuntimeException('This topic is not open for new replies.');
    if($v=community_content_violation($body)){community_record_violation($pdo,$customerId,$v);throw new RuntimeException($v);}
    $pdo->prepare("INSERT INTO forum_posts(topic_id,customer_id,body,status) VALUES(:topic,:customer,:body,'Published')")->execute(['topic'=>$topicId,'customer'=>$customerId,'body'=>trim($body)]);
    $pdo->prepare('UPDATE forum_topics SET last_post_at=NOW() WHERE id=:id')->execute(['id'=>$topicId]);
}
