<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/security.php';

bootstrap_session();

if (empty($_SESSION['inquiry_input'])) {
    header('Location: /index.php');
    exit;
}
$v = $_SESSION['inquiry_input'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>入力内容の確認 - ChatPlus</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
  <div class="brand-logo">
    <svg width="26" height="22" viewBox="0 0 30 26" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect x="0" y="0" width="30" height="20" rx="10" fill="#1fbbe8"/>
      <path d="M8 19 L4 26 L12 20 Z" fill="#1fbbe8"/>
    </svg>
    <span class="brand-name">Chat<span class="accent">Plus+</span></span>
  </div>
</header>
<main class="contact-page">
<ol class="step-indicator">
  <li>① 入力</li>
  <li class="active">② 確認</li>
  <li>③ 完了</li>
</ol>
<h1>入力内容の確認</h1>
<dl class="confirm-list">
  <dt>会社名</dt><dd><?= h($v['company_name']) ?></dd>
  <dt>部署名</dt><dd><?= $v['department'] !== '' ? h($v['department']) : '-' ?></dd>
  <dt>役職</dt><dd><?= $v['position'] !== '' ? h($v['position']) : '-' ?></dd>
  <dt>氏名</dt><dd><?= h($v['name']) ?></dd>
  <dt>メールアドレス</dt><dd><?= h($v['email']) ?></dd>
  <dt>電話番号</dt><dd><?= h($v['phone']) ?></dd>
  <dt>ご担当</dt><dd><?= h($v['contact_role']) ?></dd>
  <dt>ChatPlusについて</dt><dd><?= h($v['chatplus_status']) ?></dd>
  <dt>サービス項目</dt><dd><?= $v['service_type'] !== '' ? h($v['service_type']) : '-' ?></dd>
  <dt>問い合わせ内容</dt><dd class="pre-wrap"><?= h($v['content']) ?></dd>
</dl>

<div class="button-row">
  <form method="get" action="/index.php" style="display:inline;">
    <button type="submit" class="btn btn-secondary">戻る</button>
  </form>
  <form method="post" action="/submit.php" style="display:inline;">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary">送信する</button>
  </form>
</div>
</main>
<script src="/assets/script.js"></script>
</body>
</html>
