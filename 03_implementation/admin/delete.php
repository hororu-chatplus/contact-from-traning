<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';

bootstrap_session();
require_login();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 実際の削除はPOSTでのみ実行する（誤操作防止のための確認は、GET時に表示する確認画面がサーバー側の担保となる）
    verify_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT company_name, name FROM inquiries WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $inquiry = $stmt->fetch();

    if ($inquiry) {
        // 物理削除（要件 F-18）。削除後も追跡できるよう、削除前にスナップショットをログへ記録する（要件 F-19）
        $pdo->prepare('DELETE FROM inquiries WHERE id = :id')->execute(['id' => $id]);
        record_operation_log(
            $pdo,
            (int)$_SESSION['admin_id'],
            null,
            'delete',
            "会社名: {$inquiry['company_name']} / 氏名: {$inquiry['name']}"
        );
    }

    header('Location: /admin/list.php');
    exit;
}

// GET: 確認画面を表示する（JavaScriptに依存しない、サーバー側での確認ステップ）
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, company_name, name FROM inquiries WHERE id = :id');
$stmt->execute(['id' => $id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    header('Location: /admin/list.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>お問い合わせ削除の確認 - ChatPlus</title>
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
    <h1>お問い合わせ削除の確認</h1>
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
<p>以下のお問い合わせを削除します。よろしいですか？この操作は取り消せません。</p>
<dl class="confirm-list">
  <dt>会社名</dt><dd><?= h($inquiry['company_name']) ?></dd>
  <dt>氏名</dt><dd><?= h($inquiry['name']) ?></dd>
</dl>
<div class="button-row">
  <form method="post" action="/admin/delete.php">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-danger">削除する</button>
  </form>
  <a class="btn btn-secondary" href="/admin/detail.php?id=<?= $id ?>">キャンセル</a>
</div>
</main>
</body>
</html>

