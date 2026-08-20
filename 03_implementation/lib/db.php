<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * PDO接続を取得する（プレースホルダを使用したSQL発行を前提とする。要件 8.1）
 */
function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // DBのエラー内容をそのままブラウザに表示しない（要件 8.1）
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('現在システムに接続できません。しばらくしてから再度お試しください。');
        }
    }

    return $pdo;
}

/** 操作ログを記録する（要件 F-19）。全ての値をプレースホルダ経由で渡す（要件 8.1） */
function record_operation_log(PDO $pdo, int $adminId, ?int $inquiryId, string $actionType, string $summary, ?string $replyBody = null): void
{
    $pdo->prepare(
        'INSERT INTO operation_logs (admin_id, inquiry_id, action_type, target_summary, reply_body)
         VALUES (:admin_id, :inquiry_id, :action_type, :summary, :reply_body)'
    )->execute([
        'admin_id' => $adminId,
        'inquiry_id' => $inquiryId,
        'action_type' => $actionType,
        'summary' => $summary,
        'reply_body' => $replyBody,
    ]);
}
