# お問い合わせフォーム作成研修

要件定義 → 設計 → 実装 の一連の流れを体験するための研修用プロジェクトです。

## フォルダ構成

```
contact-form-training/
├── 01_requirements/         … 要件定義
│   └── requirements.md
├── 02_design/                … 設計
│   ├── screen_design.md      画面設計（S-01〜S-10）
│   └── data_design.md        データ・バリデーション設計
├── 03_implementation/        … 実装（PHP + MySQL）
│   ├── config.php            DB接続・アプリ設定
│   ├── schema.sql             DBスキーマ（テーブル定義）
│   ├── bin/create_admin.php  初期管理者アカウント作成用CLIスクリプト
│   ├── lib/                  共通処理（DB接続・セッション/CSRF・バリデーション・メール送信）
│   ├── storage/mail_log.txt  送信メールの記録先（研修用モック）
│   ├── assets/                CSS・クライアント側バリデーションJS
│   ├── index.php / confirm.php / submit.php / complete.php  … 利用者向け（S-01〜S-03）
│   ├── login.php / logout.php                                … ログイン（S-04）
│   ├── forgot_password.php / reset_password.php              … パスワード再設定（S-09・S-10）
│   └── admin/                 … 管理者向け（S-05〜S-08、要ログイン）
├── 04_manual/                … 運用マニュアル（実際に管理画面を使う人向け）
│   └── 運用マニュアル.md
└── README.md
```

## 進め方

1. `01_requirements/requirements.md` で「何を作るか」を確認する
2. `02_design/` で画面・入力項目・バリデーションルール・DB設計を確認する
3. `03_implementation/` で実装内容を確認し、下記の手順で動作を確認する
4. `04_manual/` で、実際に管理画面を操作する人向けの運用マニュアルを確認する

## 動作確認方法（PHP + MySQL が必要です）

このアプリはお問い合わせのDB保存・ログイン・管理画面を含むため、単純にHTMLを開くだけでは動作しません。事前にPHPとMySQLの実行環境が必要です。

### 1. PHP・MySQLの準備

環境がまだない場合は [XAMPP](https://www.apachefriends.org/jp/index.html) をインストールするのが簡単です（PHP・MySQL・Apacheがまとめて入ります）。インストール後、XAMPPコントロールパネルで **MySQL** を起動してください（Apacheは使っても使わなくても構いません。下記2通りの起動方法を参照）。

### 2. データベースの作成

MySQLにログインし、スキーマを流し込みます。

```
mysql -u root -p < 03_implementation/schema.sql
```

（XAMPP付属のphpMyAdminから `schema.sql` の内容を実行しても構いません）

DBのパスワードは秘密情報のため `config.php` には書かず、`.env` ファイル（Git管理外）から読み込む方式にしています。`03_implementation` フォルダで以下を実行し、`.env` を作成してください。

```
cd 03_implementation
copy .env.example .env
```

作成した `.env` の `DB_PASS` に、実際のDBパスワードを設定してください。ホスト・DB名・ユーザー名（`DB_HOST` / `DB_NAME` / `DB_USER`）は既定値と異なる場合のみ `.env` で上書きしてください（既定値のままでよければ空欄・未設定のままで構いません）。

### 3. 初期管理者アカウントの作成

管理画面はログインしないと使えないため、コマンドラインで最初の管理者アカウントを作成します。

```
cd 03_implementation
php bin/create_admin.php admin admin@example.com あなたが決めたパスワード（8文字以上、半角英数字に加えて記号を1文字以上含む）
```

第1引数はログインID、第2引数はメールアドレスです。このメールアドレスは、当該アカウントがログイン試行回数の上限に達してロックされた際の警告通知先（要件 F-23）に加え、ログインIDの代わりにログイン時の識別子（要件 F-10）としても使用されるため、他の管理者アカウントと重複させることはできません。

### 4. サーバーの起動

**方法A: PHP組み込みサーバーを使う（一番簡単）**

```
cd 03_implementation
php -S localhost:8000
```

ブラウザで `http://localhost:8000/index.php`（お問い合わせフォーム）、`http://localhost:8000/login.php`（管理者ログイン）にアクセスします。

同じ社内ネットワーク上の他のPCからも動作確認したい場合は、`LAN公開手順.md` を参照してください（`localhost`ではなく`0.0.0.0`で起動し、IPアドレスで共有する手順です）。

**方法B: XAMPPのApacheを使う**

`03_implementation` フォルダの中身一式を、XAMPPの `htdocs` 配下（例: `htdocs/contact-form/`）にコピーし、`http://localhost/contact-form/index.php` にアクセスします。

### 5. メール送信の確認について

既定では実際にメールは送信せず、送信されるはずの内容を `storage/mail_log.txt` に記録するだけの研修用モックになっています。管理画面にログイン後、「メール送信ログ」から自動返信・管理者通知・返信メール・パスワード再設定メールの内容を確認できます。

自動返信・管理者通知・パスワード再設定メールの送信元は `no-reply@chatplus.jp`、管理者からの個別返信メール（F-17）の送信元は返信可能な `support@chatplus.jp` を使用する想定です（`config.php` の `MAIL_FROM_ADDRESS` / `REPLY_FROM_ADDRESS`、2026-08-13のヒアリングで確定）。ただし下記の通りGmail経由で実際に送信する場合、送信元アドレスはGmailアカウント自身になります。

#### 実際にメールを送信する場合（Gmail経由）

Composer等の外部ライブラリは使わず、`lib/smtp_mailer.php`（PHP標準のstream関数のみで実装したSMTPクライアント）でSTARTTLS + 認証付きのSMTP送信を行います。

1. 送信に使うGoogleアカウントで2段階認証プロセスを有効にする（[https://myaccount.google.com/security](https://myaccount.google.com/security)）
2. [https://myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords) でアプリパスワード（16文字）を発行する（通常のログインパスワードはSMTP認証に使えません）
3. `.env` に以下を追記する

   ```
   MAIL_SEND_REAL=true
   MAIL_SMTP_USERNAME=送信に使うGmailアドレス
   MAIL_SMTP_PASSWORD=発行したアプリパスワード（スペースは入れても抜いても可）
   ```

`MAIL_SMTP_HOST` / `MAIL_SMTP_PORT` は省略時 `smtp.gmail.com` / `587` が使われます。

**注意**: Gmailは認証に使ったアカウント自身をFromアドレスとすることを要求するため、chatplus.jpの独自メールボックスが用意できるまでは、`no-reply@chatplus.jp` ではなく `MAIL_SMTP_USERNAME` に設定したGmailアドレスが実際の送信元として相手に表示されます。表示名（例:「ChatPlus」「ChatPlus サポート」）はそのまま使われます。

自分のGmailアドレス宛に実際に届くか試したい場合、次の2点にも注意してください（いずれもローカル確認専用の一時的な回避策で、本番の要件そのものを変えるものではありません。本番投入前に必ず戻すこと）。

- お問い合わせフォームの「メールアドレス」欄にgmail.com等のフリーメールアドレスを入力すると、要件通りエラーになり先に進めません。ローカル確認のためだけに許可したい場合は `.env` に `ALLOW_FREE_MAIL_FOR_TESTING=true` を追記してください。
- 管理者通知メール（F-08）の宛先は既定で本番想定の `support@chatplus.jp`（実在しないメールボックス）です。自分のGmail宛に届くか確認したい場合は `.env` に `ADMIN_NOTIFY_EMAIL=` と自分のメールアドレスを追記してください。

### 6. パスワード再設定について

ログイン画面の「パスワードをお忘れの方はこちら」からログインIDを入力すると、そのアカウントの登録メールアドレス宛に再設定用リンクが送信されます（要件 F-24）。`MAIL_SEND_REAL`が既定のfalse（研修用モック）のままなら実際のメール送信は行わず、リンクは `storage/mail_log.txt` に記録されるだけなので、そこからリンクをコピーしてブラウザでアクセスしてください。`MAIL_SEND_REAL=true`にしている場合は、上記のSMTP設定を通じて実際にメールが届きます。リンクの有効期限はいずれの場合も発行から30分です。

### 7. reCAPTCHAについて

入力画面には本物のreCAPTCHA v2（チェックボックス）ウィジェットを設置しており、送信時にサーバー側（`lib/recaptcha.php`）でGoogleのAPIに問い合わせて検証しています（要件 F-09）。`config.php`の既定値はGoogleが公開しているテスト用のサイトキー・シークレットキー（常に検証成功する）なので、ローカルでの動作確認はそのままで問題ありません。本番環境にデプロイする際は、Google reCAPTCHA管理コンソールで取得した実際のキーを`.env`の`RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY`に設定して差し替えてください。

### 8. プライバシーポリシーについて

入力画面の同意チェックボックスから参照するプライバシーポリシーのリンク先は、`config.php`の`PRIVACY_POLICY_URL`に設定済みです（https://chatplus.jp/policy/privacy/ 、要件定義書 4.6 F-22参照）。
