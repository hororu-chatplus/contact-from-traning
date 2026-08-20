<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/validation.php';

bootstrap_session();
require_login();

$id = (int)($_GET['id'] ?? 0);
$pdo = get_pdo();

$stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = :id');
$stmt->execute(['id' => $id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    header('Location: /admin/list.php');
    exit;
}

$errors = [];
$memo_value = $inquiry['memo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $summary = "会社名: {$inquiry['company_name']} / 氏名: {$inquiry['name']}";

    if ($action === 'update_status') {
        $new_status = (string)($_POST['status'] ?? '');
        if (!validate_status($new_status)) {
            $errors['status'] = '不正なステータスが指定されています';
        } else {
            $pdo->prepare('UPDATE inquiries SET status = :status WHERE id = :id')
                ->execute(['status' => $new_status, 'id' => $id]);
            record_operation_log($pdo, (int)$_SESSION['admin_id'], $id, 'edit', $summary);
            header('Location: /admin/detail.php?id=' . $id);
            exit;
        }
    } elseif ($action === 'update_memo') {
        $memo = trim((string)($_POST['memo'] ?? ''));
        $memo_error = validate_memo($memo);
        if ($memo_error) {
            $errors['memo'] = $memo_error;
            $memo_value = $memo; // エラー時も入力し直した内容を保持する
        } else {
            $pdo->prepare('UPDATE inquiries SET memo = :memo WHERE id = :id')
                ->execute(['memo' => $memo !== '' ? $memo : null, 'id' => $id]);
            record_operation_log($pdo, (int)$_SESSION['admin_id'], $id, 'edit', $summary);
            header('Location: /admin/detail.php?id=' . $id);
            exit;
        }
    }

    // エラー時は最新の値を再取得して表示する
    $stmt->execute(['id' => $id]);
    $inquiry = $stmt->fetch();
}

// 返信履歴（要件 F-17・F-19。本文もreply_bodyカラムに保存している）
$reply_stmt = $pdo->prepare(
    'SELECT ol.created_at, ol.target_summary, ol.reply_body, au.login_id
     FROM operation_logs ol
     JOIN admin_users au ON au.id = ol.admin_id
     WHERE ol.inquiry_id = :id AND ol.action_type = \'reply\'
     ORDER BY ol.created_at DESC'
);
$reply_stmt->execute(['id' => $id]);
$reply_history = $reply_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>お問い合わせ詳細 - ChatPlus</title>
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
    <h1>お問い合わせ詳細</h1>
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

<dl class="confirm-list">
  <dt>会社名</dt><dd><?= h($inquiry['company_name']) ?></dd>
  <dt>部署名</dt><dd><?= h($inquiry['department'] ?? '-') ?></dd>
  <dt>役職</dt><dd><?= h($inquiry['position'] ?? '-') ?></dd>
  <dt>氏名</dt><dd><?= h($inquiry['name']) ?></dd>
  <dt>メールアドレス</dt><dd><?= h($inquiry['email']) ?></dd>
  <dt>電話番号</dt><dd><?= h($inquiry['phone']) ?></dd>
  <dt>ご担当</dt><dd><?= h($inquiry['contact_role']) ?></dd>
  <dt>ChatPlusについて</dt><dd><?= h($inquiry['chatplus_status']) ?></dd>
  <dt>サービス項目</dt><dd><?= h($inquiry['service_type'] ?? '-') ?></dd>
  <dt>問い合わせ内容</dt><dd class="pre-wrap"><?= h($inquiry['content']) ?></dd>
  <dt>登録日時</dt><dd><?= h($inquiry['created_at']) ?></dd>
</dl>

<h2>返信履歴</h2>
<?php if (empty($reply_history)): ?>
  <p>このお問い合わせへの返信はまだありません。</p>
<?php else: ?>
  <table class="list-table">
  <thead><tr><th>送信日時</th><th>対応者</th><th>宛先・件名</th><th>本文</th></tr></thead>
  <tbody>
  <?php foreach ($reply_history as $r): ?>
    <tr>
      <td><?= h($r['created_at']) ?></td>
      <td><?= h($r['login_id']) ?></td>
      <td><?= h($r['target_summary']) ?></td>
      <td><?= h(mb_substr((string)$r['reply_body'], 0, 30)) ?><?= mb_strlen((string)$r['reply_body']) > 30 ? '…' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  </table>
<?php endif; ?>

<form method="post" action="/admin/detail.php?id=<?= $id ?>" class="inline-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="update_status">
  <label for="status">ステータス</label>
  <select id="status" name="status">
    <option value="未対応" <?= $inquiry['status'] === '未対応' ? 'selected' : '' ?>>未対応</option>
    <option value="対応済" <?= $inquiry['status'] === '対応済' ? 'selected' : '' ?>>対応済</option>
  </select>
  <button type="submit" class="btn btn-secondary">保存</button>
  <?php if (isset($errors['status'])): ?><p class="error-message"><?= h($errors['status']) ?></p><?php endif; ?>
</form>

<form method="post" action="/admin/detail.php?id=<?= $id ?>" class="inline-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="update_memo">
  <label for="memo">メモ（社内向け）</label>
  <textarea id="memo" name="memo" rows="4"><?= h($memo_value) ?></textarea>
  <?php if (isset($errors['memo'])): ?><p class="error-message"><?= h($errors['memo']) ?></p><?php endif; ?>
  <button type="submit" class="btn btn-secondary">保存</button>
</form>

<div class="button-row">
  <a class="btn btn-primary" href="/admin/reply.php?id=<?= $id ?>">返信する</a>
  <a class="btn btn-danger" href="/admin/delete.php?id=<?= $id ?>">削除する</a>
  <a class="btn btn-secondary" href="/admin/list.php">一覧に戻る</a>
</div>
</main>
</body>
</html>
