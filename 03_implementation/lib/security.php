<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

/**
 * 未処理の例外がSQL文やファイルパスを含むエラー画面としてそのまま
 * ブラウザに表示されるのを防ぐ（要件 8.1）。詳細はサーバー側のログにのみ残す。
 * security.phpは全ページでDB処理より前に読み込まれるため、ここで一括して登録する。
 */
set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '現在システムに問題が発生しています。しばらくしてから再度お試しください。';
});

/**
 * セッション開始と共通セキュリティヘッダの出力。全ページの先頭で呼び出す。
 */
function bootstrap_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,           // 要件 8.3
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']), // HTTPS時のみSecure属性を付与（要件 8.3）
        ]);
        session_name('CFSESSID');
        session_start();
    }

    header('Content-Type: text/html; charset=UTF-8'); // 要件 8.4
    header('X-Frame-Options: SAMEORIGIN');             // 要件 8.7
}

/** HTML出力時のエスケープ処理（要件 8.4） */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** CSRFトークンを取得（未発行なら発行する） */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** フォームに埋め込むhiddenフィールドを出力する（要件 8.5） */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/** POST時にCSRFトークンを検証する。不一致なら処理を中断する（要件 8.5） */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('不正なリクエストです。画面を更新してもう一度お試しください。');
    }
}

/**
 * 管理者向けページの先頭で呼び出す。未ログインならログイン画面へ（要件 F-13）。
 * 一定時間操作がない場合もセッションを破棄してログイン画面へ戻す（要件 8.3）。
 * ログイン中に他の管理者によってアカウントが無効化された場合も、次のアクセスで
 * 即座にセッションを無効化する（要件 8.8。無効化の効果が既存セッションに反映されない
 * 抜け穴を防ぐため）。
 */
function require_login(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: /login.php');
        exit;
    }

    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT_MINUTES * 60) {
        $_SESSION = [];
        session_destroy();
        header('Location: /login.php');
        exit;
    }

    $stmt = get_pdo()->prepare('SELECT is_active FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin || !$admin['is_active']) {
        $_SESSION = [];
        session_destroy();
        header('Location: /login.php');
        exit;
    }

    $_SESSION['last_activity'] = time();
}
