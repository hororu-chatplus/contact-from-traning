-- ChatPlus お問い合わせフォーム データベーススキーマ
-- 対応: 02_design/data_design.md

CREATE DATABASE IF NOT EXISTS contact_form
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE contact_form;

CREATE TABLE IF NOT EXISTS inquiries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(50) NOT NULL,
  department VARCHAR(50) NULL,
  position VARCHAR(50) NULL,
  name VARCHAR(50) NOT NULL,
  email VARCHAR(256) NOT NULL,
  phone VARCHAR(15) NOT NULL,
  contact_role ENUM('経営者','情報システム関連部門（ツール選定等のご担当）','カスタマーサポート部門（コンタクトセンター）','問い合わせ対応部門','販促・営業・マーケティング部門','お客様への提案（代理店）','その他') NOT NULL,
  chatplus_status ENUM('利用中','検討中（3か月以内）','検討中（1年以内）','検討中','他社サービス利用中','詳しく話が聞きたい','その他') NOT NULL,
  service_type ENUM('ChatPlus','FAQPlus','AI AgentPlus') NOT NULL,
  content VARCHAR(1000) NOT NULL,
  memo VARCHAR(1000) NULL,
  status ENUM('未対応','対応済') NOT NULL DEFAULT '未対応',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_inquiries_status (status),
  INDEX idx_inquiries_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  login_id VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(256) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  login_fail_count INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  password_reset_token VARCHAR(64) NULL,
  password_reset_expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE INDEX idx_admin_users_reset_token (password_reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS operation_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT UNSIGNED NOT NULL,
  inquiry_id BIGINT UNSIGNED NULL,
  action_type VARCHAR(10) NOT NULL,
  target_summary VARCHAR(500) NOT NULL,
  reply_body VARCHAR(4000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_logs_inquiry_id (inquiry_id),
  INDEX idx_logs_admin_id (admin_id),
  INDEX idx_logs_created_at (created_at),
  CONSTRAINT fk_logs_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_logs_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初期管理者アカウントはこのファイルでは作成しない。
-- bin/create_admin.php を実行して、正しくハッシュ化されたパスワードで作成すること。
