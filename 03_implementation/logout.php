<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/security.php';

bootstrap_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/list.php');
    exit;
}
verify_csrf();

$_SESSION = [];

// セッションデータの破棄に加え、ブラウザ側のセッションCookie自体も失効させる
// （要件 8.3 セッション管理。session_destroy()だけではサーバー側のデータは消えるが、
// 同じCookie値がブラウザに残り続けるため、明示的に過去日時で上書きする）
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: /login.php');
exit;
