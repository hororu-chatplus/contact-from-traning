<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/validation.php';
require_once __DIR__ . '/lib/recaptcha.php';

bootstrap_session();

$values = $_SESSION['inquiry_input'] ?? [
    'company_name' => '', 'department' => '', 'position' => '', 'name' => '', 'email' => '', 'phone' => '',
    'contact_role' => '', 'chatplus_status' => '', 'service_type' => '', 'content' => '', 'privacy_consent' => false,
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $result = validate_inquiry($_POST);
    $errors = $result['errors'];
    $values = $result['values'];

    if (empty($errors) && !verify_recaptcha($_POST['g-recaptcha-response'] ?? null)) {
        $errors['recaptcha'] = 'reCAPTCHA認証に失敗しました。再度お試しください';
    }

    if (empty($errors)) {
        $_SESSION['inquiry_input'] = $values;
        header('Location: /confirm.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>お問い合わせ - ChatPlus</title>
<link rel="stylesheet" href="/assets/style.css">
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
<header class="site-header">
  <div class="brand-logo">
    <svg width="26" height="22" viewBox="0 0 30 26" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect x="0" y="0" width="30" height="20" rx="10" fill="#1fbbe8"/>
      <path d="M8 19 L4 26 L12 20 Z" fill="#1fbbe8"/>
    </svg>
    <span class="brand-name">Chat<span class="accent">Plus+</span></span>
  </div>
</header>
<main class="contact-page">
<ol class="step-indicator">
  <li class="active">① 入力</li>
  <li>② 確認</li>
  <li>③ 完了</li>
</ol>
<h1>お問い合わせ</h1>
<form method="post" action="/index.php" novalidate>
<?= csrf_field() ?>

<div class="field">
  <label for="company_name">会社名 <span class="required">必須</span></label>
  <input type="text" id="company_name" name="company_name" value="<?= h($values['company_name']) ?>"
         class="<?= isset($errors['company_name']) ? 'error' : '' ?>" data-required="true" data-max="50">
  <?php if (isset($errors['company_name'])): ?><p class="error-message"><?= h($errors['company_name']) ?></p><?php endif; ?>
</div>

<div class="field">
  <label for="department">部署名</label>
  <input type="text" id="department" name="department" value="<?= h($values['department']) ?>"
         class="<?= isset($errors['department']) ? 'error' : '' ?>" data-max="50">
  <?php if (isset($errors['department'])): ?><p class="error-message"><?= h($errors['department']) ?></p><?php endif; ?>
</div>

<div class="field">
  <label for="position">役職</label>
  <input type="text" id="position" name="position" value="<?= h($values['position']) ?>"
         class="<?= isset($errors['position']) ? 'error' : '' ?>" data-max="50">
  <?php if (isset($errors['position'])): ?><p class="error-message"><?= h($errors['position']) ?></p><?php endif; ?>
</div>

<div class="field">
  <label for="name">氏名 <span class="required">必須</span></label>
  <input type="text" id="name" name="name" value="<?= h($values['name']) ?>"
         class="<?= isset($errors['name']) ? 'error' : '' ?>" data-required="true" data-max="50">
  <?php if (isset($errors['name'])): ?><p class="error-message"><?= h($errors['name']) ?></p><?php endif; ?>
</div>

<div class="field">
  <label for="email">メールアドレス <span class="required">必須</span></label>
  <input type="email" id="email" name="email" value="<?= h($values['email']) ?>"
         class="<?= isset($errors['email']) ? 'error' : '' ?>" data-required="true" data-max="256">
  <?php if (isset($errors['email'])): ?><p class="error-message"><?= h($errors['email']) ?></p><?php endif; ?>
</div>

<div class="field">
  <label for="phone">電話番号 <span class="required">必須</span></label>
  <input type="tel" id="phone" name="phone" value="<?= h($values['phone']) ?>"
         class="<?= isset($errors['phone']) ? 'error' : '' ?>" data-required="true" data-pattern="^(?=.*[0-9])[0-9-]{1,15}$">
  <?php if (isset($errors['phone'])): ?><p class="error-message"><?= h($errors['phone']) ?></p><?php endif; ?>
</div>

<div class="field">
  <label for="contact_role">ご担当 <span class="required">必須</span></label>
  <select id="contact_role" name="contact_role" class="<?= isset($errors['contact_role']) ? 'error' : '' ?>" data-required="true">
    <option value="">選択してください</option>
    <?php foreach (CONTACT_ROLES as $opt): ?>
      <option value="<?= h($opt) ?>" <?= $values['contact_role'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if (isset($errors['contact_role'])): ?><p class="error-message"><?= h($errors['contact_role']) ?></p><?php endif; ?>
</div>

<div class="field">
  <label for="chatplus_status">ChatPlusについて <span class="required">必須</span></label>
  <select id="chatplus_status" name="chatplus_status" class="<?= isset($errors['chatplus_status']) ? 'error' : '' ?>" data-required="true">
    <option value="">選択してください</option>
    <?php foreach (CHATPLUS_STATUSES as $opt): ?>
      <option value="<?= h($opt) ?>" <?= $values['chatplus_status'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if (isset($errors['chatplus_status'])): ?><p class="error-message"><?= h($errors['chatplus_status']) ?></p><?php endif; ?>
</div>

<div class="field">
  <label for="service_type">サービス項目 <span class="required">必須</span></label>
  <select id="service_type" name="service_type" class="<?= isset($errors['service_type']) ? 'error' : '' ?>" data-required="true">
    <option value="">選択してください</option>
    <?php foreach (SERVICE_TYPES as $opt): ?>
      <option value="<?= h($opt) ?>" <?= $values['service_type'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if (isset($errors['service_type'])): ?><p class="error-message"><?= h($errors['service_type']) ?></p><?php endif; ?>
</div>

<div class="field">
  <label for="content">問い合わせ内容 <span class="required">必須</span></label>
  <textarea id="content" name="content" rows="6"
            class="<?= isset($errors['content']) ? 'error' : '' ?>" data-required="true" data-max="1000"><?= h($values['content']) ?></textarea>
  <?php if (isset($errors['content'])): ?><p class="error-message"><?= h($errors['content']) ?></p><?php endif; ?>
</div>

<div class="field field-checkbox">
  <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1"
         <?= $values['privacy_consent'] ? 'checked' : '' ?>
         class="<?= isset($errors['privacy_consent']) ? 'error' : '' ?>" data-required="true">
  <label for="privacy_consent"><a href="<?= h(PRIVACY_POLICY_URL) ?>" target="_blank" rel="noopener">プライバシーポリシー</a>に同意する <span class="required">必須</span></label>
  <?php if (isset($errors['privacy_consent'])): ?><p class="error-message"><?= h($errors['privacy_consent']) ?></p><?php endif; ?>
</div>

<div class="field">
  <div class="g-recaptcha" data-sitekey="<?= h(RECAPTCHA_SITE_KEY) ?>"></div>
  <?php if (isset($errors['recaptcha'])): ?><p class="error-message"><?= h($errors['recaptcha']) ?></p><?php endif; ?>
</div>

<button type="submit" class="btn btn-primary btn-block">確認する</button>
</form>
</main>
<script src="/assets/script.js"></script>
</body>
</html>
