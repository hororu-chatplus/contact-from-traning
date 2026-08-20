<?php
declare(strict_types=1);

/**
 * .env（このファイルと同じ階層、Git管理外）があれば読み込み、getenv()で参照できるようにする。
 * DB_PASSのような秘密情報をconfig.php本体に直接書かないための最小限のローダー。
 */
function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (getenv($key) === false) {
            putenv($key . '=' . trim($value));
        }
    }
}
load_env_file(__DIR__ . '/.env');

/** 必須の環境変数を取得する。未設定ならエラー内容をブラウザに出さずに停止する */
function env_or_fail(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        error_log("環境変数 {$key} が未設定です。.env.example を参考に .env を作成してください。");
        http_response_code(500);
        die('現在システムに接続できません。しばらくしてから再度お試しください。');
    }
    return $value;
}

// データベース接続設定
// 最小権限の専用ユーザー（contact_formデータベースへのSELECT/INSERT/UPDATE/DELETEのみ）を使用する。
// パスワードは秘密情報のためこのファイルに書かず、.env（Git管理外）から読み込む。
// ホスト名・DB名・ユーザー名は秘密情報ではないため、.envが無い場合は既定値を使う。
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'contact_form');
define('DB_USER', getenv('DB_USER') ?: 'contact_form_app');
define('DB_PASS', env_or_fail('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

// アプリ共通設定
// 本番想定の宛先はsupport@chatplus.jpだが、.envのADMIN_NOTIFY_EMAILで上書きできるようにする
// （ローカルでの実メール送信確認時など、実在しない本番アドレス宛では届かないため）
define('ADMIN_NOTIFY_EMAIL', getenv('ADMIN_NOTIFY_EMAIL') ?: 'support@chatplus.jp');
define('MAIL_FROM_ADDRESS', 'no-reply@chatplus.jp');
define('MAIL_FROM_NAME', 'ChatPlus');

// 管理者からの個別返信メール（要件 F-17）の送信元。自動返信・管理者通知とは異なり、
// 返信可能なアドレスとする（2026-08-13のヒアリングで確定）
define('REPLY_FROM_ADDRESS', 'support@chatplus.jp');
define('REPLY_FROM_NAME', 'ChatPlus サポート');

// パスワード再設定（要件 F-24）用トークンの有効期限（分）。
// 要件で指定はないため、ログインロック時間（8.9）と揃えて実装者判断で30分とする
define('PASSWORD_RESET_EXPIRES_MINUTES', 30);

// プライバシーポリシーのURL（要件定義書 4.6 F-22参照）
define('PRIVACY_POLICY_URL', 'https://chatplus.jp/policy/privacy/');

// ログイン試行回数制限（要件定義書 8.9で確定: 5回連続失敗で30分ロック）
define('LOGIN_MAX_FAIL_COUNT', 5);
define('LOGIN_LOCK_MINUTES', 30);

// 管理者セッションのアイドルタイムアウト（要件 8.3「適切な有効期限」対応）
define('SESSION_IDLE_TIMEOUT_MINUTES', 30);

// reCAPTCHA v2（チェックボックス）設定（要件 F-09）。
// 既定値はGoogleが公開しているテスト用キー（常に検証成功する。ローカル動作確認用）。
// 本番運用前に、Google reCAPTCHA管理コンソールで取得した実際のキーを.envのRECAPTCHA_SITE_KEY / RECAPTCHA_SECRET_KEYに設定して差し替えること。
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe');

// フリーメールドメイン禁止リスト（データ設計書 2.1章の内容で確定。要件定義書 6章参照）。
// ローカルで実際のメール送信を自分のフリーメールアドレス宛に確認したい場合等のため、
// .envのALLOW_FREE_MAIL_FOR_TESTINGをtrueにすると一時的にこのチェックを無効化できる
// （本番の要件そのものを変更するものではない。本番投入前に必ずfalse/未設定に戻すこと）
define('ALLOW_FREE_MAIL_FOR_TESTING', in_array(strtolower((string)getenv('ALLOW_FREE_MAIL_FOR_TESTING')), ['1', 'true'], true));
define('FREE_MAIL_DOMAINS', ALLOW_FREE_MAIL_FOR_TESTING ? [] : [
    'gmail.com', 'yahoo.co.jp', 'yahoo.com', 'hotmail.com', 'hotmail.co.jp',
    'outlook.com', 'outlook.jp', 'icloud.com', 'live.jp',
]);

// メール送信方式
// true  = SMTP（lib/smtp_mailer.php、下記MAIL_SMTP_*設定）経由で実際に送信する
// false = 送信せず、内容を storage/mail_log.txt に記録するだけ（研修用モック、デフォルト）
define('MAIL_SEND_REAL', in_array(strtolower((string)getenv('MAIL_SEND_REAL')), ['1', 'true'], true));
define('MAIL_LOG_FILE', __DIR__ . '/storage/mail_log.txt');

// SMTP送信設定（MAIL_SEND_REAL=trueの場合のみ使用）。Gmailを既定の想定先とする。
// USERNAME/PASSWORDは秘密情報のため.envでのみ設定する（Gmailの場合、通常のログインパスワードではなく
// 「アプリパスワード」を発行して使用すること。GmailのSMTPは認証に使ったアカウント自身をFromアドレスとする
// ことを要求するため、実際の送信元アドレスはno-reply@chatplus.jp等ではなくこのUSERNAMEになる）
define('MAIL_SMTP_HOST', getenv('MAIL_SMTP_HOST') ?: 'smtp.gmail.com');
define('MAIL_SMTP_PORT', (int)(getenv('MAIL_SMTP_PORT') ?: 587));
define('MAIL_SMTP_USERNAME', getenv('MAIL_SMTP_USERNAME') ?: '');
define('MAIL_SMTP_PASSWORD', getenv('MAIL_SMTP_PASSWORD') ?: '');

// エラーログの出力先（php.ini依存の既定値ではなく、専用ファイルに明示的に出力する）
define('ERROR_LOG_FILE', __DIR__ . '/storage/error.log');
if (!is_dir(__DIR__ . '/storage')) {
    mkdir(__DIR__ . '/storage', 0777, true);
}
ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOG_FILE);
