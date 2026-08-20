<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/validation.php';

bootstrap_session();
require_login();

$status = (string)($_GET['status'] ?? '');
$service_type_filter = (string)($_GET['service_type'] ?? '');
$keyword = trim((string)($_GET['keyword'] ?? ''));

$where_sql = ' WHERE 1=1';
$params = [];

if (in_array($status, STATUSES, true)) {
    $where_sql .= ' AND status = :status';
    $params['status'] = $status;
}
if (in_array($service_type_filter, SERVICE_TYPES, true)) {
    $where_sql .= ' AND service_type = :service_type';
    $params['service_type'] = $service_type_filter;
}
if ($keyword !== '') {
    $where_sql .= " AND (company_name LIKE :kw1 ESCAPE '\\\\' OR name LIKE :kw2 ESCAPE '\\\\' OR email LIKE :kw3 ESCAPE '\\\\')";
    // "%"・"_"はLIKEのワイルドカードとして解釈されるため、検索語に含まれる場合はエスケープする
    $like = '%' . addcslashes($keyword, '%_\\') . '%';
    $params['kw1'] = $like;
    $params['kw2'] = $like;
    $params['kw3'] = $like;
}

$pdo = get_pdo();

const PAGE_SIZE_OPTIONS = [20, 50, 100];
$per_page = (int)($_GET['per_page'] ?? 20);
if (!in_array($per_page, PAGE_SIZE_OPTIONS, true)) {
    $per_page = 20;
}

$count_stmt = $pdo->prepare('SELECT COUNT(*) FROM inquiries' . $where_sql);
$count_stmt->execute($params);
$total_count = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_count / $per_page));

$page = (int)($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
} elseif ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$sql = 'SELECT id, company_name, name, email, service_type, chatplus_status, content, created_at, status FROM inquiries'
    . $where_sql . ' ORDER BY created_at DESC LIMIT ' . $per_page . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

/** 一覧のページネーションリンクを組み立てる（status・service_type・keyword・per_pageの条件は維持する） */
function list_page_url(int $page, string $status, string $service_type, string $keyword, int $per_page): string
{
    $query = ['page' => $page, 'per_page' => $per_page];
    if ($status !== '') {
        $query['status'] = $status;
    }
    if ($service_type !== '') {
        $query['service_type'] = $service_type;
    }
    if ($keyword !== '') {
        $query['keyword'] = $keyword;
    }
    return '/admin/list.php?' . http_build_query($query);
}

/** サービス項目の種類ごとにバッジの配色クラスを分ける（要件 F-14、2026-08-13のヒアリングで確定） */
function service_type_badge_class(?string $type): string
{
    return match ($type) {
        'ChatPlus' => 'badge-service-chatplus',
        'FAQPlus' => 'badge-service-faqplus',
        'AI AgentPlus' => 'badge-service-aiagentplus',
        default => '',
    };
}

/** 指定した文字数を超える場合は省略記号（…）で短縮する（要件 F-14、2026-08-14のヒアリングで追加） */
function truncate_for_list(string $value, int $max_length): string
{
    return h(mb_substr($value, 0, $max_length)) . (mb_strlen($value) > $max_length ? '…' : '');
}

/**
 * ページネーションのHTMLを出力する（要件 F-14、2026-08-14のヒアリングで追加。
 * 一覧が長い場合に上までスクロールし直さずに済むよう、テーブルの上下両方で呼び出す）
 */
function render_pagination(int $page, int $total_pages, string $status, string $service_type, string $keyword, int $per_page): void
{
    if ($total_pages <= 1) {
        return;
    }
    ?>
<nav class="pagination">
  <?php if ($page > 1): ?>
    <a class="btn btn-secondary" href="<?= h(list_page_url($page - 1, $status, $service_type, $keyword, $per_page)) ?>">« 前へ</a>
  <?php endif; ?>
  <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <?php if ($p === $page): ?>
      <span class="pagination-current"><?= $p ?></span>
    <?php else: ?>
      <a href="<?= h(list_page_url($p, $status, $service_type, $keyword, $per_page)) ?>"><?= $p ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $total_pages): ?>
    <a class="btn btn-secondary" href="<?= h(list_page_url($page + 1, $status, $service_type, $keyword, $per_page)) ?>">次へ »</a>
  <?php endif; ?>
</nav>
    <?php
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>お問い合わせ一覧 - ChatPlus</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="admin-header">
  <div class="admin-header-left">
    <div class="brand-logo">
      <svg width="24" height="20" viewBox="0 0 30 26" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect x="0" y="0" width="30" height="20" rx="10" fill="#1fbbe8"/>
        <path d="M8 19 L4 26 L12 20 Z" fill="#1fbbe8"/>
      </svg>
      <span class="brand-name">Chat<span class="accent">Plus+</span></span>
    </div>
    <h1>お問い合わせ一覧</h1>
  </div>
  <nav>
    <details class="menu">
      <summary class="menu-toggle" aria-label="メニュー">☰</summary>
      <div class="menu-dropdown">
        <a href="/admin/accounts.php">管理者アカウント管理</a>
        <a href="/admin/mail_log.php">メール送信ログ</a>
        <a href="/admin/manual.php">運用マニュアル</a>
        <form method="post" action="/logout.php" class="logout-form">
          <?= csrf_field() ?>
          <button type="submit" class="link-button">ログアウト</button>
        </form>
      </div>
    </details>
  </nav>
</header>
<main class="admin-main admin-main-wide">

<form method="get" action="/admin/list.php" class="filter-bar">
  <label>対応状況
    <select name="status">
      <option value="">すべて</option>
      <option value="未対応" <?= $status === '未対応' ? 'selected' : '' ?>>未対応</option>
      <option value="対応済" <?= $status === '対応済' ? 'selected' : '' ?>>対応済</option>
    </select>
  </label>
  <label>サービス項目
    <select name="service_type">
      <option value="">すべて</option>
      <?php foreach (SERVICE_TYPES as $opt): ?>
        <option value="<?= h($opt) ?>" <?= $service_type_filter === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <input type="text" name="keyword" placeholder="会社名・氏名・メールアドレスで検索" value="<?= h($keyword) ?>">
  <button type="submit" class="btn btn-secondary">検索</button>
  <label>表示件数
    <select name="per_page" onchange="this.form.submit()">
      <?php foreach (PAGE_SIZE_OPTIONS as $opt): ?>
        <option value="<?= $opt ?>" <?= $per_page === $opt ? 'selected' : '' ?>><?= $opt ?>件</option>
      <?php endforeach; ?>
    </select>
  </label>
</form>

<?php render_pagination($page, $total_pages, $status, $service_type_filter, $keyword, $per_page); ?>

<table class="list-table">
<thead>
<tr>
  <th>対応状況</th><th>会社名</th><th>氏名</th><th>メールアドレス</th><th>サービス項目</th><th>ChatPlusについて</th><th>内容</th><th>登録日時</th>
</tr>
</thead>
<tbody>
<?php if (empty($inquiries)): ?>
  <tr><td colspan="8">該当するお問い合わせはありません</td></tr>
<?php else: foreach ($inquiries as $row): ?>
  <tr onclick="location.href='/admin/detail.php?id=<?= (int)$row['id'] ?>'">
    <td><span class="badge badge-<?= $row['status'] === '未対応' ? 'open' : 'done' ?>"><?= h($row['status']) ?></span></td>
    <td><?= truncate_for_list($row['company_name'], 15) ?></td>
    <td><?= truncate_for_list($row['name'], 8) ?></td>
    <td><?= truncate_for_list($row['email'], 20) ?></td>
    <td><span class="badge <?= service_type_badge_class($row['service_type']) ?>"><?= h($row['service_type'] ?? '-') ?></span></td>
    <td><?= h($row['chatplus_status']) ?></td>
    <td><?= truncate_for_list($row['content'], 20) ?></td>
    <td><?= h($row['created_at']) ?></td>
  </tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<?php render_pagination($page, $total_pages, $status, $service_type_filter, $keyword, $per_page); ?>
</main>
</body>
</html>
