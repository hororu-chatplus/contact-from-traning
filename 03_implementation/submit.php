<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/validation.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/mail.php';

bootstrap_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['inquiry_input'])) {
    header('Location: /index.php');
    exit;
}
verify_csrf();

// クライアント側のチェックのみに依存せず、サーバー側で再度バリデーションする（要件 F-02）
$result = validate_inquiry($_SESSION['inquiry_input']);
if (!empty($result['errors'])) {
    header('Location: /index.php');
    exit;
}
$v = $result['values'];

$pdo = get_pdo();
$stmt = $pdo->prepare(
    'INSERT INTO inquiries (company_name, department, position, name, email, phone, contact_role, chatplus_status, service_type, content)
     VALUES (:company_name, :department, :position, :name, :email, :phone, :contact_role, :chatplus_status, :service_type, :content)'
);
$stmt->execute([
    'company_name' => $v['company_name'],
    'department' => $v['department'] !== '' ? $v['department'] : null,
    'position' => $v['position'] !== '' ? $v['position'] : null,
    'name' => $v['name'],
    'email' => $v['email'],
    'phone' => $v['phone'],
    'contact_role' => $v['contact_role'],
    'chatplus_status' => $v['chatplus_status'],
    'service_type' => $v['service_type'] !== '' ? $v['service_type'] : null,
    'content' => $v['content'],
]);

// 自動返信メール（要件 F-07）。文面は2026-08-07に確定
$auto_reply_body = <<<TEXT
{$v['name']} 様

お問い合わせいただき、誠にありがとうございます。
下記の内容で承りました。内容を確認のうえ、担当者より改めてご連絡いたします。

-----------------------------------
【お問い合わせ内容】
{$v['content']}
-----------------------------------

※本メールは送信専用のため、直接ご返信いただいても対応できません。
お急ぎのご用件がございましたら、下記までご連絡ください。

ChatPlus サポート窓口
support@chatplus.jp
TEXT;
send_mail($v['email'], '【ChatPlus】お問い合わせを受け付けました', $auto_reply_body);

// 管理者通知メール（要件 F-08）。文面は2026-08-07に確定
$department_line = $v['department'] !== '' ? $v['department'] : '-';
$position_line = $v['position'] !== '' ? $v['position'] : '-';
$service_type_line = $v['service_type'] !== '' ? $v['service_type'] : '-';
$admin_notify_body = <<<TEXT
新しいお問い合わせが届きました。管理画面にログインしてご確認ください。

-----------------------------------
会社名: {$v['company_name']}
部署名: {$department_line}
役職: {$position_line}
氏名: {$v['name']}
メールアドレス: {$v['email']}
電話番号: {$v['phone']}
ご担当: {$v['contact_role']}
ChatPlusについて: {$v['chatplus_status']}
サービス項目: {$service_type_line}
-----------------------------------

【お問い合わせ内容】
{$v['content']}
TEXT;
send_mail(ADMIN_NOTIFY_EMAIL, '【ChatPlus】新しいお問い合わせが届きました', $admin_notify_body);

unset($_SESSION['inquiry_input']);
$_SESSION['inquiry_completed'] = true;
header('Location: /complete.php');
exit;
