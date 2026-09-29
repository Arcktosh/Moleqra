<?php
$pageTitle='Community topic | Moleqra';$pageRobots='noindex,follow';
require_once __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/community.php';
$pdo=db();$ready=$pdo&&community_schema_ready($pdo);$id=(int)($_GET['id']??0);$user=customer_user();$error='';
$topic=null;$posts=[];
if($ready){$s=$pdo->prepare("SELECT t.*,p.name product_name,p.id product_id FROM forum_topics t LEFT JOIN products p ON p.id=t.product_id WHERE t.id=:id AND t.status<>'Hidden'");$s->execute(['id'=>$id]);$topic=$s->fetch()?:null;}
if($topic&&$_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null))$error='Your session expired.';
    elseif(!$user)$error='Sign in to reply.';
    else{try{community_add_post($pdo,(int)$user['id'],$id,(string)($_POST['body']??''));header('Location: community-topic.php?id='.$id.'#latest');exit;}catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Your reply could not be posted.';}}
}
if($topic){$s=$pdo->prepare("SELECT fp.*,ca.id member_id FROM forum_posts fp JOIN customer_accounts ca ON ca.id=fp.customer_id WHERE fp.topic_id=:id AND fp.status='Published' ORDER BY fp.created_at ASC");$s->execute(['id'=>$id]);$posts=$s->fetchAll();$pageTitle=$topic['title'].' | Moleqra';}
else http_response_code(404);
require __DIR__.'/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Community topic</div><h1><?=e($topic['title']??'Topic unavailable')?></h1><?php if($topic&&$topic['product_name']):?><p>Linked to <?=e($topic['product_name'])?>. Discussion is limited to legitimate laboratory/analytical research observations.</p><?php endif;?></div></section>
<section class="section"><div class="container narrow"><?php if(!$topic):?><div class="card">This topic is not available.</div><?php else:?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><div class="forum-rules notice"><strong>Community rules:</strong> no external links, email addresses, phone/contact details, off-platform contact requests, human-use claims, dosing or administration guidance.</div><div class="forum-posts"><?php foreach($posts as $p):?><article class="forum-post"><header><strong><?=e(community_member_label((int)$p['member_id']))?></strong><time datetime="<?=e($p['created_at'])?>"><?=e($p['created_at'])?></time></header><p><?=nl2br(e($p['body']))?></p></article><?php endforeach;?></div><div id="latest"></div><?php if($topic['status']==='Open'):?><section class="card forum-reply"><h2>Reply</h2><?php if($user):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><textarea name="body" maxlength="8000" required placeholder="Share laboratory observations, analytical results, documentation questions or research methodology context."></textarea><p class="small muted">Posts are screened server-side for links and personal contact information.</p><button class="btn primary">Post reply</button></form><?php else:?><p>Sign in to participate.</p><a class="btn" href="account/login.php">Sign in</a><?php endif;?></section><?php endif;?><?php endif;?></div></section>
<?php require __DIR__.'/includes/footer.php';?>
