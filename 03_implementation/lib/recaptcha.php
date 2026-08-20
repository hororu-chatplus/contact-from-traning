<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * reCAPTCHA v2のトークンをGoogleのAPIで検証する（要件 F-09）。
 * ネットワークエラー・タイムアウト時は安全側に倒して失敗（false）を返す。
 *
 * HTTP通信にはfile_get_contents（allow_url_fopen依存）ではなくcURLを使用する。
 * 本番ホスティング環境がWordPress向け共用ホスティング相当であることが2026-08-14の
 * ヒアリングで確認され、そうした環境ではセキュリティ設定としてallow_url_fopenが
 * 無効化されていることが多いため（無効な場合、file_get_contentsによる外部URLへの
 * アクセスは常に失敗し、reCAPTCHA検証・お問い合わせ送信自体が機能しなくなる）。
 * cURL拡張は多くのPHP標準構成で有効になっており、依存を回避できる。
 */
function verify_recaptcha(?string $token): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    $params = http_build_query([
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('reCAPTCHA検証のリクエストに失敗しました: ' . $curl_error);
        return false;
    }

    $result = json_decode($response, true);
    return !empty($result['success']);
}
