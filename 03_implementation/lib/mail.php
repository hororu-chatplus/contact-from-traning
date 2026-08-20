<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/smtp_mailer.php';

/**
 * メール送信（研修用モック対応版）。
 * MAIL_SEND_REALがtrueの場合のみSMTP（lib/smtp_mailer.php）経由で実際に送信し、
 * それ以外は送信内容をstorage/mail_log.txtに記録するだけとする。
 *
 * 送信元は既定でMAIL_FROM_NAME/MAIL_FROM_ADDRESS（自動返信・管理者通知用）を使用するが、
 * 管理者の個別返信（要件 F-17）等、用途によって異なる送信元を使う場合は$from_name/$from_addressを指定する。
 * ただし実際にSMTP送信する場合、GmailのSMTPは認証に使ったアカウント自身をFromアドレスとすることを
 * 要求する（chatplus.jpの独自メールボックスがまだ無いため）ので、送信元アドレスのみ
 * MAIL_SMTP_USERNAMEに置き換える（表示名は指定した$from_nameのまま）。
 *
 * 件名の改行は除去し、外部入力（本文）はヘッダには含めない（メールヘッダ・インジェクション対策、要件 8.6）。
 *
 * 戻り値: 実際に送信を試みて失敗した場合のみfalse。研修用モック時（MAIL_SEND_REAL=false）は
 * 送信自体は行わない仕様のため、ログへの記録が正常に完了すれば常にtrueを返す。
 * 送信成功可否によって呼び出し側の挙動を変えたい場合（例: 送信に失敗したら完了扱いにしない）は
 * この戻り値を確認すること。
 */
function send_mail(string $to, string $subject, string $body, ?string $from_name = null, ?string $from_address = null): bool
{
    $safe_subject = str_replace(["\r", "\n"], '', $subject);
    $from_name = $from_name ?? MAIL_FROM_NAME;
    $from_address = $from_address ?? MAIL_FROM_ADDRESS;
    $sent_from_address = $from_address;
    $success = true;

    if (MAIL_SEND_REAL) {
        if (MAIL_SMTP_USERNAME === '' || MAIL_SMTP_PASSWORD === '') {
            error_log('MAIL_SEND_REALが有効ですが、.envにMAIL_SMTP_USERNAME / MAIL_SMTP_PASSWORDが未設定のため、実際の送信をスキップしました。');
            $success = false;
        } else {
            $sent_from_address = MAIL_SMTP_USERNAME;
            $success = smtp_send_mail(
                MAIL_SMTP_HOST,
                MAIL_SMTP_PORT,
                MAIL_SMTP_USERNAME,
                MAIL_SMTP_PASSWORD,
                $from_name,
                $sent_from_address,
                $to,
                $safe_subject,
                $body
            );
            if (!$success) {
                error_log("メール送信に失敗しました: To={$to} Subject={$safe_subject}");
            }
        }
    }

    $entry = sprintf(
        "==== %s ====\nTo: %s\nFrom: %s <%s>\nSubject: %s\n\n%s\n\n",
        date('Y-m-d H:i:s'),
        $to,
        $from_name,
        $sent_from_address,
        $safe_subject,
        $body
    );

    $dir = dirname(MAIL_LOG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents(MAIL_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);

    return $success;
}

/** 直近の送信ログを取得する（管理画面での確認用、研修用モック） */
function last_mail_entries(int $count = 20): string
{
    if (!file_exists(MAIL_LOG_FILE)) {
        return '（まだ送信履歴はありません）';
    }
    $content = file_get_contents(MAIL_LOG_FILE);

    // 送信時刻を含む厳密な区切りパターンでのみ分割する。
    // お問い合わせ内容や返信本文にたまたま「==== 」という文字列が含まれても誤って分割しないようにするため。
    $parts = preg_split('/(^==== \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} ====$)/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

    $entries = [];
    $currentHeader = null;
    foreach ($parts as $part) {
        if (preg_match('/^==== \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} ====$/', trim($part))) {
            $currentHeader = trim($part);
        } elseif ($currentHeader !== null) {
            $entries[] = $currentHeader . "\n" . trim($part, "\n");
            $currentHeader = null;
        }
    }

    if (empty($entries)) {
        return '（まだ送信履歴はありません）';
    }

    $tail = array_slice($entries, -$count);
    return implode("\n\n", $tail) . "\n";
}
