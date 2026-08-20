<?php
declare(strict_types=1);

// 初期管理者アカウントの作成・パスワード更新用CLIスクリプト
// 使い方: php bin/create_admin.php <ログインID> <パスワード>

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/validation.php';

if (PHP_SAPI !== 'cli') {
    die("このスクリプトはコマンドラインから実行してください。\n");
}

[, $login_id, $email, $password] = array_pad($argv, 4, null);

if ($login_id === null || $email === null || $password === null) {
    fwrite(STDERR, "使い方: php bin/create_admin.php <ログインID> <メールアドレス> <パスワード>\n");
    exit(1);
}

if (!is_valid_email_format($email)) {
    fwrite(STDERR, "メールアドレスの形式が正しくありません。\n");
    exit(1);
}

if (!is_valid_admin_password($password)) {
    fwrite(STDERR, "パスワードは8文字以上72文字以内で、半角英数字に加えて記号を1文字以上含めてください。\n");
    exit(1);
}

$pdo = get_pdo();

// ログインID・メールアドレスはどちらもログイン識別子を兼ねる（要件 F-10）ため、一方が他の
// アカウントの値と一致するとログイン時にどちらのアカウントか一意に定まらなくなる。DBエラーに
// なる前にここで検出する（login_id自体は既存アカウントの更新のためON DUPLICATE KEYで許容する）
$stmt = $pdo->prepare('SELECT login_id FROM admin_users WHERE email = :email AND login_id != :login_id');
$stmt->execute(['email' => $email, 'login_id' => $login_id]);
$conflict = $stmt->fetchColumn();
if ($conflict !== false) {
    fwrite(STDERR, "このメールアドレスは既に別の管理者アカウント「{$conflict}」で使用されています。\n");
    exit(1);
}

// PDO(ATTR_EMULATE_PREPARES=false)のネイティブプリペアドステートメントは同名プレースホルダの
// 再利用を確実にサポートしないため、値が同じでも別名のプレースホルダとして渡す
$stmt = $pdo->prepare('SELECT email FROM admin_users WHERE login_id != :login_id AND email = :login_id_as_email');
$stmt->execute(['login_id' => $login_id, 'login_id_as_email' => $login_id]);
$conflict = $stmt->fetchColumn();
if ($conflict !== false) {
    fwrite(STDERR, "このログインIDは既に別の管理者アカウントのメールアドレスとして使用されています。\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO admin_users (login_id, email, password_hash, is_active, login_fail_count, locked_until)
     VALUES (:login_id, :email, :password_hash, 1, 0, NULL)
     ON DUPLICATE KEY UPDATE email = VALUES(email), password_hash = VALUES(password_hash), is_active = 1, login_fail_count = 0, locked_until = NULL'
);
$stmt->execute(['login_id' => $login_id, 'email' => $email, 'password_hash' => $hash]);

echo "管理者アカウント「{$login_id}」を作成（またはパスワードを更新）しました。\n";
