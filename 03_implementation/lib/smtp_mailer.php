<?php
declare(strict_types=1);

/**
 * 最小限のSMTPクライアント（STARTTLS + AUTH LOGIN対応）。
 * 本プロジェクトは依存パッケージを持たない研修用構成のため、Composer等の外部ライブラリは
 * 追加せず、PHP標準のstream関数のみで実装する。Gmail（smtp.gmail.com:587）を主な
 * 利用対象とするが、STARTTLS + AUTH LOGINに対応した一般的なSMTPサーバーであれば動作する。
 */

/** 1つのSMTP応答を読み取る（複数行応答は最終行まで読み進める） */
function smtp_read_response($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        // 応答コードの直後が"-"なら続きがある（例: "250-STARTTLS"）。半角スペースなら最終行
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    return $response;
}

/** コマンドを送信し、応答が期待する応答コードで始まるか確認する */
function smtp_command($socket, string $command, string $expected_code): bool
{
    if ($command !== '') {
        fwrite($socket, $command . "\r\n");
    }
    $response = smtp_read_response($socket);
    if (!str_starts_with($response, $expected_code)) {
        error_log("SMTPエラー: コマンド「{$command}」への応答が不正です: " . trim($response));
        return false;
    }
    return true;
}

/** ヘッダの表示名等にASCII以外の文字が含まれる場合、RFC 2047のencoded-word形式に変換する */
function smtp_encode_header_word(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/**
 * SMTP（STARTTLS + AUTH LOGIN）でメールを送信する。成功時true、失敗時falseを返す。
 * 件名・本文・表示名はUTF-8を前提とする。
 */
function smtp_send_mail(
    string $host,
    int $port,
    string $username,
    string $password,
    string $from_name,
    string $from_address,
    string $to,
    string $subject,
    string $body
): bool {
    $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10);
    if ($socket === false) {
        error_log("SMTP接続に失敗しました: {$errstr} ({$errno})");
        return false;
    }
    stream_set_timeout($socket, 10);

    $ok = str_starts_with(smtp_read_response($socket), '220')
        && smtp_command($socket, 'EHLO localhost', '250')
        && smtp_command($socket, 'STARTTLS', '220');

    if ($ok) {
        $ok = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$ok) {
            error_log('SMTPエラー: STARTTLSのTLSネゴシエーションに失敗しました。');
        }
    }

    if ($ok) {
        // STARTTLS後はEHLOをやり直す（RFC 3207）
        $ok = smtp_command($socket, 'EHLO localhost', '250')
            && smtp_command($socket, 'AUTH LOGIN', '334')
            && smtp_command($socket, base64_encode($username), '334')
            && smtp_command($socket, base64_encode($password), '235')
            && smtp_command($socket, 'MAIL FROM:<' . $from_address . '>', '250')
            && smtp_command($socket, 'RCPT TO:<' . $to . '>', '250')
            && smtp_command($socket, 'DATA', '354');
    }

    if ($ok) {
        $encoded_from_name = smtp_encode_header_word($from_name);
        $encoded_subject = smtp_encode_header_word($subject);

        // SMTPのドットスタッフィング対策（行頭が"."単独だと終端記号と誤認されるため二重化する）
        $normalized_body = str_replace(["\r\n", "\r"], "\n", $body);
        $escaped_body = preg_replace('/^\./m', '..', $normalized_body);
        $crlf_body = str_replace("\n", "\r\n", $escaped_body);

        $message = "From: {$encoded_from_name} <{$from_address}>\r\n"
            . "To: <{$to}>\r\n"
            . "Subject: {$encoded_subject}\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n"
            . "\r\n"
            . $crlf_body . "\r\n";

        $ok = smtp_command($socket, $message . '.', '250');
    }

    smtp_command($socket, 'QUIT', '221');
    fclose($socket);
    return $ok;
}
