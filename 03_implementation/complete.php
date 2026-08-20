<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/security.php';

bootstrap_session();

if (empty($_SESSION['inquiry_completed'])) {
    header('Location: /index.php');
    exit;
}
unset($_SESSION['inquiry_completed']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>送信完了 - ChatPlus</title>
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
<main class="contact-page complete-card">
<ol class="step-indicator">
  <li>① 入力</li>
  <li>② 確認</li>
  <li class="active">③ 完了</li>
</ol>
<h1>お問い合わせを受け付けました</h1>
<p>確認のメールをお送りしましたので、ご確認ください。</p>
<?php if (!MAIL_SEND_REAL): ?>
<p class="note">（研修用モックのため、実際のメールは送信されません。管理画面の「メール送信ログ」から内容をご確認いただけます）</p>
<?php endif; ?>
<a class="btn btn-primary" href="/index.php">TOPページへ戻る</a>
</main>
</body>
</html>
