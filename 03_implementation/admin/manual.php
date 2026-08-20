<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/security.php';

bootstrap_session();
require_login();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>運用マニュアル - ChatPlus</title>
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
    <h1>運用マニュアル</h1>
  </div>
  <nav>
    <a href="/admin/list.php">一覧へ戻る</a>
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
<main class="admin-main">
<div class="manual-content" id="top">

<p>Webサイトから届く「お問い合わせ」を管理画面で確認・対応するためのマニュアルです。パソコンの専門的な知識は必要ありません。</p>

<nav class="manual-toc" aria-label="目次">
  <p>目次</p>
  <ol>
    <li><a href="#m1">このシステムでできること</a></li>
    <li><a href="#m2">ログイン方法</a></li>
    <li><a href="#m3">お問い合わせ一覧の見方</a></li>
    <li><a href="#m4">詳細を確認する</a></li>
    <li><a href="#m5">返信する</a></li>
    <li><a href="#m6">削除する</a></li>
    <li><a href="#m7">メール送信履歴の確認</a></li>
    <li><a href="#m8">管理者アカウントの管理</a></li>
    <li><a href="#m9">パスワードを忘れた・変更したいとき</a></li>
    <li><a href="#m10">ログインできないとき</a></li>
    <li><a href="#m11">よくある質問</a></li>
  </ol>
</nav>

<section id="m1">
<h2>1. このシステムでできること</h2>
<p>Webサイトのお問い合わせフォームから送信された内容が、自動的にこのシステムに保存されます。管理画面にログインすると、次のことができます。</p>
<ul>
  <li>届いたお問い合わせを一覧で確認する</li>
  <li>対応状況（未対応・対応済）を管理する</li>
  <li>お問い合わせ元へメールで返信する</li>
  <li>社内向けのメモを残す</li>
  <li>対応不要になったお問い合わせを削除する</li>
  <li>管理者アカウント（ログインできる人）を管理する</li>
</ul>
</section>

<section id="m2">
<h2>2. ログイン方法</h2>
<ol>
  <li>ブラウザで管理画面のURL（担当者から共有されたもの）を開きます。</li>
  <li>「ログインID または メールアドレス」の欄に、ログインIDと登録済みのメールアドレスのどちらかを入力し、「パスワード」を入力します（ログインIDを忘れてしまった場合は、代わりにメールアドレスでログインできます）。</li>
  <li>「ログインする」ボタンを押します。</li>
</ol>
<p>ログインに成功すると、お問い合わせ一覧の画面が表示されます。</p>
<figure class="manual-figure">
  <img src="/assets/manual/01_login.png" alt="ログイン画面">
  <figcaption>ログイン画面</figcaption>
</figure>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

<section id="m3">
<h2>3. お問い合わせ一覧の見方</h2>
<p>ログイン後、最初に表示されるのがこの一覧画面です。届いたお問い合わせが新しい順に並びます。</p>
<figure class="manual-figure">
  <img src="/assets/manual/02_list.png" alt="お問い合わせ一覧画面">
  <figcaption>お問い合わせ一覧画面（表示中のデータはサンプルです）</figcaption>
</figure>

<h3>画面の見方</h3>
<div class="table-wrap">
<table class="list-table" style="display:table;">
<tr><th>項目</th><th>内容</th></tr>
<tr><td>対応状況</td><td><span class="badge badge-open">未対応</span> ／ <span class="badge badge-done">対応済</span> のバッジで一目で分かります</td></tr>
<tr><td>サービス項目</td><td><span class="badge badge-service-chatplus">ChatPlus</span> ／ <span class="badge badge-service-faqplus">FAQPlus</span> ／ <span class="badge badge-service-aiagentplus">AI AgentPlus</span> の色で区別されます</td></tr>
<tr><td>行をクリック</td><td>そのお問い合わせの詳細画面に移動します</td></tr>
</table>
</div>

<h3>絞り込み・検索</h3>
<p>画面上部で、以下の条件を組み合わせて絞り込めます。</p>
<ul>
  <li><strong>対応状況</strong>: 「すべて」「未対応」「対応済」から選択</li>
  <li><strong>サービス項目</strong>: 「すべて」「ChatPlus」「FAQPlus」「AI AgentPlus」から選択</li>
  <li><strong>キーワード検索</strong>: 会社名・氏名・メールアドレスの一部を入力して「検索」を押す</li>
  <li><strong>表示件数</strong>: 1ページに表示する件数を20件／50件／100件から選択</li>
</ul>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

<section id="m4">
<h2>4. お問い合わせの詳細を確認する</h2>
<p>一覧で行をクリックすると、詳細画面が開きます。</p>
<figure class="manual-figure">
  <img src="/assets/manual/03_detail.png" alt="お問い合わせ詳細画面">
  <figcaption>お問い合わせ詳細画面（表示中のデータはサンプルです）</figcaption>
</figure>
<p>この画面でできること:</p>
<ul>
  <li><strong>入力内容の確認</strong>: 会社名・氏名・連絡先など、お問い合わせフォームに入力された内容がすべて表示されます</li>
  <li><strong>返信履歴の確認</strong>: このお問い合わせにこれまで返信した内容（日時・担当者・件名・本文）が確認できます</li>
  <li><strong>対応状況の変更</strong>: プルダウンで「未対応」「対応済」を選び「保存」を押します</li>
  <li><strong>メモの記入</strong>: 社内向けのメモ（他の担当者への申し送りなど）を書いて「保存」を押します。お問い合わせ元には表示されません</li>
  <li><strong>返信する</strong>: <a href="#m5">5章</a>を参照</li>
  <li><strong>削除する</strong>: <a href="#m6">6章</a>を参照</li>
</ul>
<div class="manual-note"><strong>メモ</strong>対応状況やメモを保存すると、「誰が・いつ」変更したかが自動的に記録されます。</div>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

<section id="m5">
<h2>5. お問い合わせに返信する</h2>
<ol>
  <li>詳細画面で「返信する」ボタンを押します。</li>
  <li>件名と本文を入力します（宛先はお問い合わせ元のメールアドレスに固定されており、変更できません）。</li>
  <li>「送信する」ボタンを押します。</li>
</ol>
<figure class="manual-figure">
  <img src="/assets/manual/04_reply.png" alt="返信メール作成画面">
  <figcaption>返信メール作成画面</figcaption>
</figure>
<p>送信すると、そのお問い合わせの対応状況は自動的に「対応済」に変わります。改めて対応状況を変更する必要はありません。</p>
<div class="manual-warn"><strong>送信に失敗した場合</strong>エラーメッセージが表示され、対応状況も「対応済」には変わりません。時間を置いてもう一度お試しください。改善しない場合は他の管理者や担当者にご相談ください。</div>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

<section id="m6">
<h2>6. お問い合わせを削除する</h2>
<ol>
  <li>詳細画面で「削除する」ボタンを押します。</li>
  <li>確認画面が表示されるので、内容を確認して「削除する」を押します。</li>
</ol>
<div class="manual-warn"><strong>削除すると元に戻せません。</strong>誤操作を防ぐため、必ず確認画面を経由する仕組みになっています。不要であれば「キャンセル」で詳細画面に戻ってください。</div>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

<section id="m7">
<h2>7. 送信したメールの履歴を確認する</h2>
<p>画面右上の「☰」メニューから「メール送信ログ」を選ぶと、これまでにシステムが送信した自動返信・管理者通知・返信メールの内容を確認できます。「相手にメールが届いていないと言われた」際に、実際に送信されたかどうかを確認するのに使えます。</p>
<figure class="manual-figure">
  <img src="/assets/manual/07_maillog.png" alt="メール送信ログ画面">
  <figcaption>メール送信ログ画面</figcaption>
</figure>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

<section id="m8">
<h2>8. 管理者アカウントを管理する</h2>
<p>画面右上の「☰」メニューから「管理者アカウント管理」を選ぶと、ログインできる人を管理できます。</p>
<figure class="manual-figure">
  <img src="/assets/manual/05_accounts.png" alt="管理者アカウント管理画面">
  <figcaption>管理者アカウント管理画面</figcaption>
</figure>

<h3>新しい管理者を追加する</h3>
<ol>
  <li>「新規登録」欄に、ログインID・メールアドレス・パスワード（2回）を入力します。</li>
  <li>パスワードは<strong>8文字以上で、半角英数字に加えて記号を1文字以上</strong>含める必要があります（例: <code>Passw0rd!</code>）。</li>
  <li>「登録する」を押します。</li>
</ol>
<p>登録したメールアドレスは、そのアカウントが後述の「アカウントロック」になった際の通知先として使われます。</p>

<h3>アカウントを無効化する（利用停止）</h3>
<p>対象アカウントの「無効化する」ボタンを押すと、そのアカウントはログインできなくなります。ログイン中の場合も、次の操作で強制的にログアウトされます。<strong>自分自身のアカウントは無効化できません</strong>（誰もログインできなくなる事態を防ぐため）。</p>
<p>再度有効にしたい場合は、同じ場所の「有効化する」ボタンを押します。</p>
<div class="manual-note"><strong>メモ</strong>このシステムでは、退職・異動などでアカウントが不要になった場合も削除はせず「無効化」で対応します。過去の対応履歴を保持するためです。</div>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

<section id="m9">
<h2>9. パスワードを忘れた・変更したいとき</h2>
<p>自分でパスワードを再設定できます。他の管理者や開発者に連絡する必要はありません。</p>
<ol>
  <li>ログイン画面の「パスワードをお忘れの方はこちら」をクリックします。</li>
  <li>自分のログインIDまたは登録済みのメールアドレスを入力して「送信する」を押します（ログインIDを忘れてしまった場合は、メールアドレスでも依頼できます）。</li>
  <li>登録されているメールアドレス宛に、パスワード再設定用のリンクが記載されたメールが届きます。</li>
  <li>メール内のリンクをクリックし、新しいパスワードを入力して「設定する」を押します。</li>
</ol>
<figure class="manual-figure">
  <img src="/assets/manual/06_forgot.png" alt="パスワード再設定依頼画面">
  <figcaption>パスワード再設定依頼画面</figcaption>
</figure>
<div class="manual-note"><strong>有効期限</strong>リンクの有効期限は発行から<strong>30分</strong>です。時間が経って無効になった場合は、もう一度最初からやり直してください。この方法は「パスワードを忘れた」ときだけでなく、「パスワードを変更したい」ときにも使えます。</div>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

<section id="m10">
<h2>10. ログインできないとき（アカウントロック）</h2>
<p>パスワードを<strong>5回連続で</strong>間違えると、そのアカウントは<strong>30分間</strong>ロックされ、ログインできなくなります。</p>
<ul>
  <li>ロックされた瞬間に、そのアカウントの登録メールアドレス宛に警告メールが届きます。心当たりがない場合は、第三者による不正なログイン試行の可能性があるため、他の管理者にご相談ください。</li>
  <li>30分経てば自動的にロックが解除されます。急ぐ場合でも、時間を置く以外に解除する方法はありません。</li>
  <li>ロック中でも、パスワード自体は変わっていません。解除後はそのままのパスワードでログインできます。</li>
</ul>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

<section id="m11">
<h2>11. よくある質問</h2>

<div class="manual-qa">
  <div class="q">Q. お問い合わせへの返信メールが相手に届いていないと言われました。</div>
  <div>A. まず送信先のメールアドレスに誤りがないか、詳細画面で確認してください。相手の迷惑メールフォルダに振り分けられている場合もあります。それでも解決しない場合は、担当者にご確認ください。</div>
</div>
<div class="manual-qa">
  <div class="q">Q. 一覧に表示される件数を増やしたい。</div>
  <div>A. 一覧画面右上の「表示件数」で20件・50件・100件を切り替えられます。</div>
</div>
<div class="manual-qa">
  <div class="q">Q. 対応済にしたお問い合わせを、また未対応に戻せますか。</div>
  <div>A. できます。詳細画面の「対応状況」プルダウンでいつでも変更できます。</div>
</div>
<div class="manual-qa">
  <div class="q">Q. メモは相手に見られますか。</div>
  <div>A. 見られません。メモは管理画面にログインした管理者だけが確認できる、社内向けの項目です。</div>
</div>
<div class="manual-qa">
  <div class="q">Q. スマートフォンからも使えますか。</div>
  <div>A. 使えます。画面幅に応じて表示が調整されます（一覧画面など列の多い画面は横にスクロールして確認してください）。</div>
</div>
<p class="manual-top-link"><a href="#top">↑ 目次に戻る</a></p>
</section>

</div>
</main>
</body>
</html>
