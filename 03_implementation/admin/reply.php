<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/validation.php';
require_once __DIR__ . '/../lib/mail.php';

bootstrap_session();
require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = :id');
$stmt->execute(['id' => $id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    header('Location: /admin/list.php');
    exit;
}

$errors = [];
$values = ['subject' => '', 'body' => ''];
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $result = validate_reply($_POST);
    $errors = $result['errors'];
    $values = $result['values'];

    if (empty($errors)) {
        // 件名には他の自動送信メール（自動返信・管理者通知・ロック警告・パスワード再設定）と
        // 同じ「【ChatPlus】」の接頭辞を付ける（ブランド表記の統一）
        $email_subject = '【ChatPlus】' . $values['subject'];
        // 管理者が入力した本文をそのまま送るのではなく、宛名・元のお問い合わせ内容の引用・署名を
        // 付けた形式に整えて送信する（自動返信メールと同様の構成にする）
        $email_body = <<<TEXT
{$inquiry['name']} 様

お問い合わせいただき、誠にありがとうございます。
ChatPlus サポート窓口でございます。

{$values['body']}

-----------------------------------
【お問い合わせ内容】
{$inquiry['content']}
-----------------------------------

ご不明な点がございましたら、本メールに直接ご返信いただくか、お気軽にお問い合わせください。

ChatPlus サポート窓口
support@chatplus.jp
TEXT;
        $mail_sent = send_mail($inquiry['email'], $email_subject, $email_body, REPLY_FROM_NAME, REPLY_FROM_ADDRESS);
        if ($mail_sent) {
            record_operation_log(
                $pdo,
                (int)$_SESSION['admin_id'],
                $id,
                'reply',
                "宛先: {$inquiry['email']} / 件名: {$email_subject}",
                $values['body']
            );
            // 返信送信によりステータスを自動的に「対応済」に更新する（要件 F-16・F-17）
            $pdo->prepare('UPDATE inquiries SET status = :status WHERE id = :id')
                ->execute(['status' => '対応済', 'id' => $id]);
            $sent = true;
        } else {
            // 送信に失敗した場合は対応済への更新も操作ログへの記録も行わず、再送を促す
            $errors['send'] = 'メールの送信に失敗しました。しばらくしてから再度お試しください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>返信メール作成 - ChatPlus</title>
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
    <h1>返信メール作成</h1>
  </div>
  <nav>
    <a href="/admin/detail.php?id=<?= $id ?>">詳細へ戻る</a>
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

<?php if ($sent): ?>
  <p class="success-message">返信メールを送信しました<?= MAIL_SEND_REAL ? '' : '（研修用モックのため、実際には送信せず内容をメール送信ログに記録しています）' ?>。</p>
  <a class="btn btn-primary" href="/admin/detail.php?id=<?= $id ?>">詳細へ戻る</a>
<?php else: ?>
  <?php if (isset($errors['send'])): ?><p class="error-message"><?= h($errors['send']) ?></p><?php endif; ?>
  <form method="post" action="/admin/reply.php?id=<?= $id ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="field">
      <label>宛先</label>
      <p><?= h($inquiry['email']) ?>（変更不可）</p>
    </div>
    <div class="field">
      <label for="subject">件名 <span class="required">必須</span></label>
      <input type="text" id="subject" name="subject" value="<?= h($values['subject']) ?>" data-max="200">
      <?php if (isset($errors['subject'])): ?><p class="error-message"><?= h($errors['subject']) ?></p><?php endif; ?>
    </div>
    <div class="field">
      <label for="body">本文 <span class="required">必須</span></label>
      <textarea id="body" name="body" rows="8" data-max="4000"><?= h($values['body']) ?></textarea>
      <?php if (isset($errors['body'])): ?><p class="error-message"><?= h($errors['body']) ?></p><?php endif; ?>
    </div>
    <div class="button-row">
      <button type="submit" class="btn btn-primary">送信する</button>
      <a class="btn btn-secondary" href="/admin/detail.php?id=<?= $id ?>">キャンセル</a>
    </div>
  </form>
<?php endif; ?>
</main>
<script src="/assets/script.js"></script>
</body>
</html>
