<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validation.php';

bootstrap_session();

$pdo = get_pdo();

/**
 * トークンが有効な（存在し、期限切れでない）管理者アカウントを取得する。無効ならnull
 * 期限判定はPHP側で行う（login.phpのlocked_until判定と同様）。
 * DBサーバーとアプリサーバーでタイムゾーン設定が異なる環境でも、
 * MySQLのNOW()に依存せず一貫した基準（PHPのtime()）で比較するため
 */
function find_admin_by_valid_token(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE password_reset_token = :token');
    $stmt->execute(['token' => $token]);
    $admin = $stmt->fetch();
    if (!$admin || $admin['password_reset_expires_at'] === null) {
        return null;
    }
    if (strtotime($admin['password_reset_expires_at']) <= time()) {
        return null;
    }
    return $admin;
}

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $admin = find_admin_by_valid_token($pdo, $token);

    if ($admin) {
        $result = validate_new_password($_POST);
        $errors = $result['errors'];

        if (empty($errors)) {
            $hash = password_hash((string)$_POST['password'], PASSWORD_DEFAULT); // 要件 8.9
            $pdo->prepare(
                'UPDATE admin_users
                 SET password_hash = :hash, password_reset_token = NULL, password_reset_expires_at = NULL,
                     login_fail_count = 0, locked_until = NULL
                 WHERE id = :id'
            )->execute(['hash' => $hash, 'id' => $admin['id']]);
            $done = true;
        }
    }
} else {
    $admin = find_admin_by_valid_token($pdo, $token);
}

$token_valid = !empty($admin);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>新しいパスワードの設定 - ChatPlus</title>
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
<main class="form-card">
<h1>新しいパスワードの設定</h1>
<?php if ($done): ?>
  <p class="success-message">パスワードを再設定しました。新しいパスワードでログインしてください。</p>
  <a class="btn btn-primary" href="/login.php">ログイン画面へ</a>
<?php elseif (!$token_valid): ?>
  <p>このリンクは無効か、有効期限が切れています。お手数ですが、パスワード再設定をもう一度お試しください。</p>
  <a class="btn btn-primary" href="/forgot_password.php">パスワード再設定画面へ</a>
<?php else: ?>
  <form method="post" action="/reset_password.php">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= h($token) ?>">
    <div class="field">
      <label for="password">新しいパスワード <span class="required">必須</span></label>
      <input type="password" id="password" name="password">
      <?php if (isset($errors['password'])): ?><p class="error-message"><?= h($errors['password']) ?></p><?php endif; ?>
    </div>
    <div class="field">
      <label for="password_confirm">新しいパスワード（確認用） <span class="required">必須</span></label>
      <input type="password" id="password_confirm" name="password_confirm">
      <?php if (isset($errors['password_confirm'])): ?><p class="error-message"><?= h($errors['password_confirm']) ?></p><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary btn-block">設定する</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
