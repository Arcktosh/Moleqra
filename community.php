<?php
$pageTitle='Research Community | Moleqra';$pageDescription='Moleqra customer research community for laboratory and analytical discussion.';$pageRobots='noindex,follow';
require_once __DIR__.'/includes/bootstrap.php';require_once __DIR__.'/includes/community.php';
$pdo=db();$ready=$pdo&&community_schema_ready($pdo);$error='';$user=customer_user();
if($ready&&$_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_valid($_POST['csrf_token']??null))$error='Your session expired.';
    elseif(!$user)$error='Sign in to create a discussion topic.';
    else{try{$id=community_create_topic($pdo,(int)$user['id'],(string)($_POST['title']??''),(string)($_POST['body']??''));header('Location: community-topic.php?id='.$id);exit;}catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'The topic could not be created.';}}
}
$topics=[];
if($ready){$topics=$pdo->query("SELECT t.*,p.name product_name,(SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id=t.id AND fp.status='Published') post_count FROM forum_topics t LEFT JOIN products p ON p.id=t.product_id WHERE t.status<>'Hidden' ORDER BY t.is_product_topic DESC,COALESCE(t.last_post_at,t.created_at) DESC LIMIT 100")->fetchAll();}
require __DIR__.'/includes/header.php';
?>
<section class="page-hero"><div class="container"><div class="eyebrow">Community</div><h1>Research discussions.</h1><p>A moderated customer forum for laboratory observations, documentation questions and product-related analytical discussion. Human-use, dosing and off-platform contact sharing are not permitted.</p></div></section>
<section class="section"><div class="container"><?php if(!$ready):?><div class="alert error">The community database upgrade has not been installed yet.</div><?php else:?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="community-layout"><section><div class="section-head"><div><h2>Topics</h2><p>Product topics are created automatically; customers can also start general research discussions.</p></div></div><div class="topic-list"><?php foreach($topics as $t):?><a class="topic-row" href="community-topic.php?id=<?=(int)$t['id']?>"><div><strong><?=e($t['title'])?></strong><span><?= $t['is_product_topic']?'Product discussion'.($t['product_name']?' · '.e($t['product_name']):''):'Community topic' ?></span></div><span><?= (int)$t['post_count'] ?> post<?= (int)$t['post_count']===1?'':'s' ?></span></a><?php endforeach;?><?php if(!$topics):?><div class="card"><p>No community topics yet.</p></div><?php endif;?></div></section>
<aside class="card community-compose"><h2>Start a topic</h2><?php if($user):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="field"><label>Topic title</label><input name="title" maxlength="180" required></div><div class="field"><label>Opening post</label><textarea name="body" maxlength="8000" required></textarea></div><p class="small muted">Do not post links, email addresses, phone/contact details, personal-use claims, dosing or administration instructions.</p><button class="btn primary">Create topic</button></form><?php else:?><p>Sign in to create or reply to community topics.</p><a class="btn primary" href="account/login.php">Sign in</a><?php endif;?></aside></div>
<?php endif;?></div></section>
<?php require __DIR__.'/includes/footer.php';?>
