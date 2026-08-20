<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

const SERVICE_TYPES = ['ChatPlus', 'FAQPlus', 'AI AgentPlus'];
const CONTACT_ROLES = [
    '経営者',
    '情報システム関連部門（ツール選定等のご担当）',
    'カスタマーサポート部門（コンタクトセンター）',
    '問い合わせ対応部門',
    '販促・営業・マーケティング部門',
    'お客様への提案（代理店）',
    'その他',
];
const CHATPLUS_STATUSES = [
    '利用中',
    '検討中（3か月以内）',
    '検討中（1年以内）',
    '検討中',
    '他社サービス利用中',
    '詳しく話が聞きたい',
    'その他',
];
const STATUSES = ['未対応', '対応済'];

/** メールアドレスのドメインがフリーメール禁止リストと完全一致するか判定する（データ設計書 2.1） */
function is_free_mail_domain(string $email): bool
{
    $at = strrpos($email, '@');
    if ($at === false) {
        return false;
    }
    $domain = strtolower(substr($email, $at + 1));
    return in_array($domain, FREE_MAIL_DOMAINS, true);
}

/** メールアドレスの形式チェック（データ設計書 2章・6.3章で共通利用） */
function is_valid_email_format(string $email): bool
{
    return (bool)preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
}

/**
 * お問い合わせフォームの入力値をバリデーションする（データ設計書 1章・2章）
 * クライアント側チェックの有無に関わらず、ここで必ずサーバー側の検証を行う（要件 F-02）
 */
function validate_inquiry(array $input): array
{
    $errors = [];

    $company_name = trim((string)($input['company_name'] ?? ''));
    if ($company_name === '') {
        $errors['company_name'] = '会社名を入力してください';
    } elseif (mb_strlen($company_name) > 50) {
        $errors['company_name'] = '会社名は50文字以内で入力してください';
    }

    $department = trim((string)($input['department'] ?? ''));
    if (mb_strlen($department) > 50) {
        $errors['department'] = '部署名は50文字以内で入力してください';
    }

    $position = trim((string)($input['position'] ?? ''));
    if (mb_strlen($position) > 50) {
        $errors['position'] = '役職は50文字以内で入力してください';
    }

    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = '氏名を入力してください';
    } elseif (mb_strlen($name) > 50) {
        $errors['name'] = '氏名は50文字以内で入力してください';
    }

    $email = trim((string)($input['email'] ?? ''));
    if ($email === '') {
        $errors['email'] = 'メールアドレスを入力してください';
    } elseif (!is_valid_email_format($email)) {
        $errors['email'] = '正しい形式のメールアドレスを入力してください';
    } elseif (mb_strlen($email) > 256) {
        $errors['email'] = 'メールアドレスは256文字以内で入力してください';
    } elseif (is_free_mail_domain($email)) {
        $errors['email'] = 'フリーメールアドレスはご利用いただけません';
    }

    $phone = trim((string)($input['phone'] ?? ''));
    if ($phone === '') {
        $errors['phone'] = '電話番号を入力してください';
    } elseif (!preg_match('/^(?=.*[0-9])[0-9\-]{1,15}$/', $phone)) {
        $errors['phone'] = '電話番号は数字とハイフンのみで入力してください';
    }

    $contact_role = (string)($input['contact_role'] ?? '');
    if ($contact_role === '') {
        $errors['contact_role'] = 'ご担当を選択してください';
    } elseif (!in_array($contact_role, CONTACT_ROLES, true)) {
        $errors['contact_role'] = '不正なご担当が指定されています';
    }

    $chatplus_status = (string)($input['chatplus_status'] ?? '');
    if ($chatplus_status === '') {
        $errors['chatplus_status'] = 'ChatPlusについてを選択してください';
    } elseif (!in_array($chatplus_status, CHATPLUS_STATUSES, true)) {
        $errors['chatplus_status'] = '不正な選択肢が指定されています';
    }

    $service_type = (string)($input['service_type'] ?? '');
    if ($service_type === '') {
        $errors['service_type'] = 'サービス項目を選択してください';
    } elseif (!in_array($service_type, SERVICE_TYPES, true)) {
        $errors['service_type'] = '不正なサービス項目が指定されています';
    }

    $content = trim((string)($input['content'] ?? ''));
    if ($content === '') {
        $errors['content'] = '問い合わせ内容を入力してください';
    } elseif (mb_strlen($content) > 1000) {
        $errors['content'] = '問い合わせ内容は1000文字以内で入力してください';
    }

    $privacy_consent = !empty($input['privacy_consent']);
    if (!$privacy_consent) {
        $errors['privacy_consent'] = 'プライバシーポリシーへの同意にチェックを入れてください';
    }

    return [
        'errors' => $errors,
        'values' => compact(
            'company_name', 'department', 'position', 'name', 'email', 'phone',
            'contact_role', 'chatplus_status', 'service_type', 'content', 'privacy_consent'
        ),
    ];
}

/** ステータスが許可された値かどうか（データ設計書 6.1） */
function validate_status(string $status): bool
{
    return in_array($status, STATUSES, true);
}

/** メモの文字数チェック（データ設計書 6.1）。問題なければnullを返す */
function validate_memo(string $memo): ?string
{
    if (mb_strlen($memo) > 1000) {
        return 'メモは1000文字以内で入力してください';
    }
    return null;
}

/** 返信メールの件名・本文をバリデーションする（データ設計書 6.2） */
function validate_reply(array $input): array
{
    $errors = [];
    $subject = trim((string)($input['subject'] ?? ''));
    $body = trim((string)($input['body'] ?? ''));

    if ($subject === '') {
        $errors['subject'] = '件名を入力してください';
    } elseif (mb_strlen($subject) > 200) {
        $errors['subject'] = '件名は200文字以内で入力してください';
    }

    if ($body === '') {
        $errors['body'] = '本文を入力してください';
    } elseif (mb_strlen($body) > 4000) {
        $errors['body'] = '本文は4000文字以内で入力してください';
    }

    return ['errors' => $errors, 'values' => compact('subject', 'body')];
}

/**
 * パスワードが8文字以上、半角英数字に加えて記号を1文字以上含むか判定する（要件 8.9で確定）。
 * 上限72文字は、ハッシュ関数bcrypt（password_hash()のPASSWORD_DEFAULT）が72バイトを超える部分を
 * 無視する仕様のため、それより長く設定しても意味がないことを利用者に伝える目的で設ける
 * （許可文字が半角英数記号のみのため、1文字=1バイトで72文字=72バイトとなる）
 */
function is_valid_admin_password(string $password): bool
{
    if (mb_strlen($password) < 8 || mb_strlen($password) > 72) {
        return false;
    }
    // 半角英数字・記号（ASCIIの印字可能文字）以外の文字（空白・全角文字等）を含まないこと
    if (!preg_match('/^[A-Za-z0-9!-\/:-@\[-`{-~]+$/', $password)) {
        return false;
    }
    // 半角英数字を1文字以上含むこと（記号だけのパスワードを許可しないため）
    if (!preg_match('/[A-Za-z0-9]/', $password)) {
        return false;
    }
    // 記号を1文字以上含むこと
    return (bool)preg_match('/[!-\/:-@\[-`{-~]/', $password);
}

/** 管理者アカウント新規登録のバリデーション（データ設計書 6.3） */
function validate_new_admin(array $input, PDO $pdo): array
{
    $errors = [];
    $login_id = trim((string)($input['login_id'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $password_confirm = (string)($input['password_confirm'] ?? '');

    if ($login_id === '') {
        $errors['login_id'] = 'ログインIDを入力してください';
    } elseif (mb_strlen($login_id) < 3 || mb_strlen($login_id) > 50) {
        $errors['login_id'] = 'ログインIDは3〜50文字で入力してください';
    } else {
        // ログインID・メールアドレスはどちらもログイン識別子として使われる（要件 F-10）ため、
        // 一方が他方のアカウントの値と一致してしまうと、ログイン時にどちらのアカウントか一意に
        // 定まらなくなる。login_id・emailどちらのカラムとも重複しないことを確認する。
        // PDO(ATTR_EMULATE_PREPARES=false)のネイティブプリペアドステートメントは同名プレースホルダの
        // 再利用を確実にサポートしないため、値が同じでも別名のプレースホルダとして渡す
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE login_id = :login_id OR email = :login_id_as_email');
        $stmt->execute(['login_id' => $login_id, 'login_id_as_email' => $login_id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['login_id'] = 'このログインIDは既に使用されています';
        }
    }

    if ($email === '') {
        $errors['email'] = 'メールアドレスを入力してください';
    } elseif (!is_valid_email_format($email)) {
        $errors['email'] = '正しい形式のメールアドレスを入力してください';
    } elseif (mb_strlen($email) > 256) {
        $errors['email'] = 'メールアドレスは256文字以内で入力してください';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE email = :email OR login_id = :email_as_login_id');
        $stmt->execute(['email' => $email, 'email_as_login_id' => $email]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['email'] = 'このメールアドレスは既に使用されています';
        }
    }

    if ($password === '') {
        $errors['password'] = 'パスワードを入力してください';
    } elseif (!is_valid_admin_password($password)) {
        $errors['password'] = 'パスワードは8文字以上72文字以内で、半角英数字に加えて記号を1文字以上含めてください';
    } elseif ($password !== $password_confirm) {
        $errors['password_confirm'] = 'パスワードが一致しません';
    }

    return ['errors' => $errors, 'values' => compact('login_id', 'email')];
}

/**
 * パスワード再設定依頼のバリデーション（データ設計書 6.4、S-09、要件 F-24）。
 * ログインID・メールアドレスのどちらでも依頼できる（要件 F-10、2026-08-14のヒアリングで追加。
 * ログイン画面と同様、ログインIDを忘れた場合の救済手段を再設定依頼画面にも揃える）
 */
function validate_password_reset_request(array $input): array
{
    $errors = [];
    $identifier = trim((string)($input['identifier'] ?? ''));

    if ($identifier === '') {
        $errors['identifier'] = 'ログインIDまたはメールアドレスを入力してください';
    }

    return ['errors' => $errors, 'values' => compact('identifier')];
}

/** 新しいパスワードのバリデーション（データ設計書 6.4、S-10、要件 F-24。8.9のパスワードポリシーと同一） */
function validate_new_password(array $input): array
{
    $errors = [];
    $password = (string)($input['password'] ?? '');
    $password_confirm = (string)($input['password_confirm'] ?? '');

    if ($password === '') {
        $errors['password'] = 'パスワードを入力してください';
    } elseif (!is_valid_admin_password($password)) {
        $errors['password'] = 'パスワードは8文字以上72文字以内で、半角英数字に加えて記号を1文字以上含めてください';
    } elseif ($password !== $password_confirm) {
        $errors['password_confirm'] = 'パスワードが一致しません';
    }

    return ['errors' => $errors, 'values' => []];
}
