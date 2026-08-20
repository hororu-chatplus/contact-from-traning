<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/validation.php';

bootstrap_session();
require_login();

$pdo = get_pdo();
$errors = [];
$values = ['login_id' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $result = validate_new_admin($_POST, $pdo);
        $errors = $result['errors'];
        $values = $result['values'];

        if (empty($errors)) {
            $hash = password_hash((string)$_POST['password'], PASSWORD_DEFAULT); // 要件 8.9
            $pdo->prepare('INSERT INTO admin_users (login_id, email, password_hash, is_active) VALUES (:login_id, :email, :hash, 1)')
                ->execute(['login_id' => $values['login_id'], 'email' => $values['email'], 'hash' => $hash]);
            header('Location: /admin/accounts.php');
            exit;
        }
    } elseif ($action === 'toggle_active') {
        $target_id = (int)($_POST['id'] ?? 0);
        if ($target_id === (int)$_SESSION['admin_id']) {
            // 自己ロックアウト防止: 自分自身は無効化できない
            $errors['toggle'] = '自分自身のアカウントは無効化できません';
        } else {
            $pdo->prepare('UPDATE admin_users SET is_active = NOT is_active WHERE id = :id')
                ->execute(['id' => $target_id]);
            header('Location: /admin/accounts.php');
            exit;
        }
    }
}

$admins = $pdo->query('SELECT id, login_id, email, is_active, created_at FROM admin_users ORDER BY login_id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>管理者アカウント管理 - ChatPlus</title>
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
    <h1>管理者アカウント管理</h1>
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

<?php if (isset($errors['toggle'])): ?><p class="error-message"><?= h($errors['toggle']) ?></p><?php endif; ?>

<table class="list-table">
<thead><tr><th>ログインID</th><th>メールアドレス</th><th>状態</th><th>作成日時</th><th>操作</th></tr></thead>
<tbody>
<?php foreach ($admins as $a): ?>
  <tr>
    <td><?= h($a['login_id']) ?></td>
    <td><?= h($a['email']) ?></td>
    <td><?= $a['is_active'] ? '有効' : '無効' ?></td>
    <td><?= h($a['created_at']) ?></td>
    <td>
      <form method="post" action="/admin/accounts.php" style="display:inline;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_active">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button type="submit" class="btn btn-secondary"><?= $a['is_active'] ? '無効化する' : '有効化する' ?></button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
</tbody>
</table>

<h2>新規登録</h2>
<form method="post" action="/admin/accounts.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <div class="field">
    <label for="login_id">ログインID <span class="required">必須</span></label>
    <input type="text" id="login_id" name="login_id" value="<?= h($values['login_id']) ?>">
    <?php if (isset($errors['login_id'])): ?><p class="error-message"><?= h($errors['login_id']) ?></p><?php endif; ?>
  </div>
  <div class="field">
    <label for="email">メールアドレス <span class="required">必須</span></label>
    <input type="email" id="email" name="email" value="<?= h($values['email']) ?>">
    <p class="note">アカウントがロックされた際の警告通知先として使用します（要件 F-23）</p>
    <?php if (isset($errors['email'])): ?><p class="error-message"><?= h($errors['email']) ?></p><?php endif; ?>
  </div>
  <div class="field">
    <label for="password">パスワード <span class="required">必須</span></label>
    <input type="password" id="password" name="password">
    <?php if (isset($errors['password'])): ?><p class="error-message"><?= h($errors['password']) ?></p><?php endif; ?>
  </div>
  <div class="field">
    <label for="password_confirm">パスワード（確認用） <span class="required">必須</span></label>
    <input type="password" id="password_confirm" name="password_confirm">
    <?php if (isset($errors['password_confirm'])): ?><p class="error-message"><?= h($errors['password_confirm']) ?></p><?php endif; ?>
  </div>
  <button type="submit" class="btn btn-primary">登録する</button>
</form>
</main>
</body>
</html>
