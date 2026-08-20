<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/mail.php';

bootstrap_session();

if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin/list.php');
    exit;
}

$error = null;
$identifier_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $identifier_value = $identifier;

    $pdo = get_pdo();
    // ログインIDまたはメールアドレスのどちらでも認証できるようにする（emailはUNIQUEのため一意に定まる）。
    // PDO(ATTR_EMULATE_PREPARES=false)のネイティブプリペアドステートメントは同名プレースホルダの
    // 再利用を確実にサポートしないため、値が同じでも別名のプレースホルダとして渡す
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE login_id = :login_id OR email = :email');
    $stmt->execute(['login_id' => $identifier, 'email' => $identifier]);
    $admin = $stmt->fetch();

    if ($identifier === '' || $password === '' || !$admin) {
        // アカウントが存在しない場合も、実在する場合とほぼ同じ処理時間になるようダミーの
        // パスワード検証を行う（bcryptの検証にかかる時間差からアカウントの有無を推測されないようにするため）
        password_verify($password, '$2y$10$hSH3dJ5zzVT0rj1b4hYOjuJ8h2XvJseZtv4SgSZmWVkh6PZHaE3EW');
        $error = 'IDまたはパスワードが正しくありません';
    } elseif (!$admin['is_active']) {
        // 無効化アカウントはパスワードが正しくてもログイン拒否する。ただし本人が正しいパスワードを
        // 入力している可能性があるため、失敗回数への加算・ロック・警告メール送信の対象にはしない
        // （表示メッセージのみパスワード誤りと同じにし、アカウントの有無を推測させない。
        // ロック中かどうかに関わらず、無効化されている場合は必ずこのメッセージを優先する）
        // ここでもダミーのパスワード検証を行い、本物のpassword_verify()を呼ぶ他の分岐との処理時間差から
        // 「実在するが無効化されている」ことが推測されないようにする（要件 8.9・S-04の匿名化方針と同様）
        password_verify($password, '$2y$10$hSH3dJ5zzVT0rj1b4hYOjuJ8h2XvJseZtv4SgSZmWVkh6PZHaE3EW');
        $error = 'IDまたはパスワードが正しくありません';
    } elseif ($admin['locked_until'] !== null && strtotime($admin['locked_until']) > time()) {
        $error = '試行回数の上限に達しました。しばらく経ってから再度お試しください';
    } elseif (!password_verify($password, $admin['password_hash'])) {
        // 失敗回数の加算とロック設定を、原子的な2段階のUPDATEで行う（本番は共用ホスティング環境であることが
        // 2026-08-14のヒアリングで確認されたため対応。従来のSELECT→PHPで+1→UPDATEという読み取り後書き込みでは、
        // 同時に失敗した複数リクエストの一方の加算が失われたり、ロック警告メールが重複送信され得る）
        $pdo->prepare('UPDATE admin_users SET login_fail_count = login_fail_count + 1 WHERE id = :id')
            ->execute(['id' => $admin['id']]);

        $locked_until = date('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES * 60);
        $now = date('Y-m-d H:i:s');
        // login_fail_countが上限に達しており、かつまだロック中でない（or 期限切れの）行にのみ適用される。
        // 該当行が0件なら他のリクエストが既にロックを確定させた後なので、ここでは何もしない
        // （要件 F-23「ロックのたびに1回だけ送信する」を、同時アクセス下でも保証するため）
        $lock_stmt = $pdo->prepare(
            'UPDATE admin_users
             SET locked_until = :locked_until, login_fail_count = 0
             WHERE id = :id AND login_fail_count >= :max_fail AND (locked_until IS NULL OR locked_until <= :now)'
        );
        $lock_stmt->execute([
            'locked_until' => $locked_until,
            'id' => $admin['id'],
            'max_fail' => LOGIN_MAX_FAIL_COUNT,
            'now' => $now,
        ]);
        $newly_locked = $lock_stmt->rowCount() > 0;

        // ロックが今回新たに発生した場合のみ警告通知メールを送信する（要件 F-23。失敗のたびには送信しない）
        if ($newly_locked) {
            // 文面は2026-08-07に確定
            $lockout_body = <<<TEXT
管理者アカウント「{$admin['login_id']}」は、ログイン試行の失敗が続いたため一時的にロックされました。

ロック解除予定時刻: {$locked_until}

ご本人による操作に心当たりがない場合、第三者による不正なログイン試行の可能性があります。
お手数ですが、他の管理者にご確認いただくか、ログイン可能になり次第パスワードの変更をご検討ください。

なお、ロック解除後もパスワード自体は変更されておらず、そのままご利用いただけます。
TEXT;
            send_mail($admin['email'], '【ChatPlus】管理者アカウントがロックされました', $lockout_body);
        }
        $error = 'IDまたはパスワードが正しくありません';
    } else {
        $pdo->prepare('UPDATE admin_users SET login_fail_count = 0, locked_until = NULL WHERE id = :id')
            ->execute(['id' => $admin['id']]);

        session_regenerate_id(true); // セッション固定攻撃対策（要件 8.3）
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_login_id'] = $admin['login_id'];
        header('Location: /admin/list.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>管理者ログイン - ChatPlus</title>
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
<h1>管理者ログイン</h1>
<form method="post" action="/login.php">
<?= csrf_field() ?>
<div class="field">
  <label for="identifier">ログインID または メールアドレス <span class="required">必須</span></label>
  <input type="text" id="identifier" name="identifier" value="<?= h($identifier_value) ?>">
</div>
<div class="field">
  <label for="password">パスワード <span class="required">必須</span></label>
  <input type="password" id="password" name="password">
</div>
<?php if ($error): ?><p class="error-message"><?= h($error) ?></p><?php endif; ?>
<button type="submit" class="btn btn-primary btn-block">ログインする</button>
</form>
<p class="forgot-password-link"><a href="/forgot_password.php">パスワードをお忘れの方はこちら</a></p>
</main>
</body>
</html>
