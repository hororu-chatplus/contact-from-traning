<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/mail.php';

bootstrap_session();
require_login();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>メール送信ログ - ChatPlus</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="admin-header">
  <div class="admin-header-left">
    <div class="brand-logo">
      <svg width="24" height="20" viewBox="0 0 30 26" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect x="0" y="0" width="30" height="20" rx="10" fill="#1fbbe8"/>
        <path d="M8 19 L4 26 L12 20 Z" fill="#1fbbe8"/>
      </svg>
      <span class="brand-name">Chat<span class="accent">Plus+</span></span>
    </div>
    <h1>メール送信ログ</h1>
  </div>
  <nav>
    <a href="/admin/list.php">一覧へ戻る</a>
    <details class="menu">
      <summary class="menu-toggle" aria-label="メニュー">☰</summary>
      <div class="menu-dropdown">
        <a href="/admin/accounts.php">管理者アカウント管理</a>
        <a href="/admin/mail_log.php">メール送信ログ</a>
        <a href="/admin/manual.php">運用マニュアル</a>
        <form method="post" action="/logout.php" class="logout-form">
          <?= csrf_field() ?>
          <button type="submit" class="link-button">ログアウト</button>
        </form>
      </div>
    </details>
  </nav>
</header>
<main class="admin-main">
<?php if (MAIL_SEND_REAL): ?>
<p>自動返信・管理者通知・返信メールの送信内容をここに記録しています。</p>
<?php else: ?>
<p>実際にはメールを送信せず、送信されるはずの内容をここに記録しています（自動返信・管理者通知・返信メールの全て）。</p>
<?php endif; ?>
<pre class="mail-log"><?= h(last_mail_entries(20)) ?></pre>
</main>
</body>
</html>
