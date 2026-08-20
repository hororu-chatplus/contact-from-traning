<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/validation.php';
require_once __DIR__ . '/lib/mail.php';

bootstrap_session();

$errors = [];
$values = ['identifier' => ''];
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $result = validate_password_reset_request($_POST);
    $errors = $result['errors'];
    $values = $result['values'];

    if (empty($errors)) {
        $pdo = get_pdo();
        // ログインIDまたはメールアドレスのどちらでも依頼できるようにする（要件 F-10と同様の考え方）
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE login_id = :login_id OR email = :email');
        $stmt->execute(['login_id' => $values['identifier'], 'email' => $values['identifier']]);
        $admin = $stmt->fetch();

        // 実在有無に関わらず同一の完了画面を表示する（アカウントの有無を外部から推測させないため）
        if ($admin) {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRES_MINUTES * 60);
            $pdo->prepare('UPDATE admin_users SET password_reset_token = :token, password_reset_expires_at = :expires_at WHERE id = :id')
                ->execute(['token' => $token, 'expires_at' => $expires_at, 'id' => $admin['id']]);

            // リバースプロキシ配下（GitHub Codespaces等）でも正しい外部URLを組み立てる
            $scheme = is_secure_request() ? 'https' : 'http';
            $reset_link = $scheme . '://' . current_host() . '/reset_password.php?token=' . $token;

            $body = <<<TEXT
{$admin['login_id']} 様

パスワード再設定のご依頼を受け付けました。
以下のリンクから、新しいパスワードを設定してください。

{$reset_link}

このリンクの有効期限は発行から30分です。有効期限が切れた場合は、お手数ですが再度パスワード再設定の手続きを行ってください。

このメールに心当たりがない場合は、破棄していただいて構いません（パスワードは変更されません）。
TEXT;
            send_mail($admin['email'], '【ChatPlus】パスワード再設定のご案内', $body);
        } else {
            // login_idが存在しない場合も、存在する場合とある程度近い処理時間になるよう
            // 重い処理（bcryptハッシュ計算）を行っておく。メール送信のネットワーク待ち時間までは
            // 完全には揃えられないが、応答時間の差からアカウントの有無を推測されにくくするための対策
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        }
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>パスワード再設定 - ChatPlus</title>
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
<h1>パスワード再設定</h1>
<?php if ($sent): ?>
  <p>入力されたログインIDまたはメールアドレスが登録されている場合、パスワード再設定用のメールを送信しました。メール内のリンクからお進みください。</p>
  <a class="btn btn-primary" href="/login.php">ログイン画面へ戻る</a>
<?php else: ?>
  <p class="note">ログインIDまたはメールアドレスを入力してください。再設定用のリンクを記載したメールを、登録済みのメールアドレス宛に送信します。</p>
  <form method="post" action="/forgot_password.php">
    <?= csrf_field() ?>
    <div class="field">
      <label for="identifier">ログインID または メールアドレス <span class="required">必須</span></label>
      <input type="text" id="identifier" name="identifier" value="<?= h($values['identifier']) ?>">
      <?php if (isset($errors['identifier'])): ?><p class="error-message"><?= h($errors['identifier']) ?></p><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary btn-block">送信する</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
