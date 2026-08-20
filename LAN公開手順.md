# 社内LAN公開手順 - contact-form-training

このソースコードを受け取った担当者が、開発機1台だけでなく、社内の他のPCからも管理者ログイン画面・お問い合わせフォームを開いて動作確認できるようにするための手順です。`http://localhost:8000/...` は実行しているPC自身からしか開けないため、他のPCから開けるようにするには以下の追加設定が必要です。

## 前提条件

- README.mdの手順に従って、このPC上でXAMPP（PHP・MySQL）のセットアップと`.env`の作成が完了していること
- 公開するPCと、アクセスする社員のPCが、同じ社内ネットワーク（同じWi-Fi/LAN）に接続されていること

## 手順

### 1. MySQLを起動する

```powershell
C:\xampp\mysql_start.bat
```

### 2. PHPサーバーを「全ての通信を受け付ける」設定で起動する

`03_implementation`フォルダで、通常の`localhost:8000`ではなく`0.0.0.0:8000`を指定して起動します。

```powershell
cd 03_implementation
C:\xampp\php\php.exe -S 0.0.0.0:8000
```

`0.0.0.0`を指定することで、自分のPC以外からの接続も受け付けるようになります。

### 3. このPCのIPアドレスを確認する

社員がアクセスするURLに使うため、このPCの社内ネットワーク上のIPアドレスを確認します。

```powershell
Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*" }
```

「Wi-Fi」や「イーサネット」など、社内ネットワークに接続しているアダプターのIPアドレス（例: `192.168.100.20`）を控えます。

### 4. Windowsファイアウォールでポート8000を許可する（初回のみ）

**管理者としてPowerShellを開いて**、以下を実行します。通常のPowerShellでは「Access is denied」となり実行できません。

```powershell
New-NetFirewallRule -DisplayName "ChatPlus contact-form-training (port 8000)" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow -Profile Any
```

これは一度実行すれば、以降はPHPサーバーを再起動するだけで有効なままです。

### 5. 社員に共有するURL

手順3で確認したIPアドレスを使って、以下のURLを共有します（`192.168.100.20`の部分は実際のIPアドレスに置き換えてください）。

- **お問い合わせフォーム（利用者側）**: `http://192.168.100.20:8000/index.php`
- **管理者ログイン画面**: `http://192.168.100.20:8000/login.php`

管理者ログインのアカウント情報は、別途担当者から共有してください。

## 注意事項

- あくまで研修・動作確認用の一時的な公開方法です。暗号化されていない通信（HTTP）のため、社内の信頼できるネットワーク内でのみ使用してください。インターネットに公開する設定ではありません。
- このPCのIPアドレスは、ネットワークに再接続すると変わる場合があります。社員がアクセスできない場合は、手順3を再度実行してIPアドレスが変わっていないか確認してください。
- 公開を終了する場合は、PHPサーバーを起動しているコマンドプロンプト／PowerShellのウィンドウを閉じるか、`Ctrl+C`で停止してください。
- ファイアウォールのルール（手順4）が不要になった場合は、以下のコマンド（管理者権限）で削除できます。
  ```powershell
  Remove-NetFirewallRule -DisplayName "ChatPlus contact-form-training (port 8000)"
  ```
