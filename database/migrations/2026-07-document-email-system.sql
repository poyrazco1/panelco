-- =========================================================================
-- BELGE E-POSTA / GÖNDERİM SİSTEMİ
-- SMTP profilleri, departman e-posta hesapları, e-posta şablonları,
-- belge gönderim logları, güvenli belge erişim token'ları/onayları ve
-- kullanıcı e-posta tercihleri.
--
-- Fresh kurulum için aynı tanımlar install.sql içindedir. Bu migration mevcut
-- kurulumları güvenle yükseltir (idempotent — CREATE TABLE IF NOT EXISTS).
-- Not: SMTP şifreleri UYGULAMA ANAHTARIYLA (VAULT_KEY) şifreli saklanır;
-- veritabanında düz metin tutulmaz.
-- =========================================================================
SET NAMES utf8mb4;

-- 1) SMTP profilleri (çoklu; şifreler vault ile şifreli)
CREATE TABLE IF NOT EXISTS `smtp_profiles` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(150) NOT NULL,
    `host`          VARCHAR(190) NOT NULL DEFAULT '',
    `port`          SMALLINT UNSIGNED NOT NULL DEFAULT 465,
    `encryption`    ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
    `username`      VARCHAR(190) NOT NULL DEFAULT '',
    `password_enc`  TEXT DEFAULT NULL,                      -- vault_encrypt() çıktısı
    `from_email`    VARCHAR(190) NOT NULL DEFAULT '',
    `from_name`     VARCHAR(190) NOT NULL DEFAULT '',
    `timeout`       SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    `test_email`    VARCHAR(190) NOT NULL DEFAULT '',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `is_default`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_smtp_name` (`name`),
    KEY `idx_smtp_active` (`is_active`),
    KEY `idx_smtp_default` (`is_default`),
    CONSTRAINT `fk_smtp_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) E-posta şablonları (modül bazlı; {{degisken}} destekli)
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key`  VARCHAR(60)  NOT NULL DEFAULT '',         -- ör. reconciliation, quotes, service
    `name`        VARCHAR(150) NOT NULL,
    `subject`     VARCHAR(300) NOT NULL DEFAULT '',
    `body`        MEDIUMTEXT   DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `is_default`  TINYINT(1)   NOT NULL DEFAULT 0,           -- modül için varsayılan
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tpl_module` (`module_key`),
    KEY `idx_tpl_active` (`is_active`),
    CONSTRAINT `fk_tpl_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Departman / modül e-posta hesapları
CREATE TABLE IF NOT EXISTS `department_email_accounts` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_name`  VARCHAR(150) NOT NULL,
    `module_key`       VARCHAR(60)  NOT NULL DEFAULT '',     -- eşleştiği modül (boş=genel)
    `from_name`        VARCHAR(190) NOT NULL DEFAULT '',
    `from_email`       VARCHAR(190) NOT NULL DEFAULT '',     -- departman hesabı (görünen From)
    `reply_to_mode`    ENUM('user','department','fixed') NOT NULL DEFAULT 'user',
    `reply_to_fixed`   VARCHAR(190) NOT NULL DEFAULT '',
    `default_cc`       VARCHAR(500) NOT NULL DEFAULT '',     -- virgülle ayrık
    `default_bcc`      VARCHAR(500) NOT NULL DEFAULT '',
    `forward_to`       VARCHAR(500) NOT NULL DEFAULT '',     -- gelen cevapların yönlendirileceği adresler (bilgi amaçlı)
    `smtp_profile_id`  INT UNSIGNED DEFAULT NULL,
    `template_id`      INT UNSIGNED DEFAULT NULL,
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by`       INT UNSIGNED DEFAULT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dept_module` (`module_key`),
    KEY `idx_dept_active` (`is_active`),
    CONSTRAINT `fk_dept_smtp` FOREIGN KEY (`smtp_profile_id`) REFERENCES `smtp_profiles` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dept_tpl`  FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dept_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Belge gönderim logları (gönderim geçmişi)
CREATE TABLE IF NOT EXISTS `document_email_logs` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type`    VARCHAR(60)  NOT NULL DEFAULT '',     -- ör. reconciliation
    `document_id`      INT UNSIGNED DEFAULT NULL,
    `document_no`      VARCHAR(100) NOT NULL DEFAULT '',
    `sent_by_user_id`  INT UNSIGNED DEFAULT NULL,
    `sent_by_name`     VARCHAR(190) NOT NULL DEFAULT '',
    `department`       VARCHAR(150) NOT NULL DEFAULT '',
    `smtp_profile_id`  INT UNSIGNED DEFAULT NULL,
    `from_email`       VARCHAR(190) NOT NULL DEFAULT '',
    `reply_to`         VARCHAR(190) NOT NULL DEFAULT '',
    `to_email`         VARCHAR(500) NOT NULL DEFAULT '',
    `cc`               VARCHAR(500) NOT NULL DEFAULT '',
    `bcc`              VARCHAR(500) NOT NULL DEFAULT '',
    `subject`          VARCHAR(300) NOT NULL DEFAULT '',
    `body`             MEDIUMTEXT   DEFAULT NULL,
    `has_pdf`          TINYINT(1)   NOT NULL DEFAULT 0,
    `pdf_path`         VARCHAR(255) NOT NULL DEFAULT '',
    `doc_link`         VARCHAR(255) NOT NULL DEFAULT '',
    `status`           ENUM('sent','failed') NOT NULL DEFAULT 'failed',
    `error_message`    TEXT         DEFAULT NULL,
    `message_id`       VARCHAR(255) NOT NULL DEFAULT '',
    `resend_of_id`     BIGINT UNSIGNED DEFAULT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_del_doc` (`document_type`, `document_id`),
    KEY `idx_del_created` (`created_at`),
    KEY `idx_del_status` (`status`),
    CONSTRAINT `fk_del_user` FOREIGN KEY (`sent_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Güvenli belge erişim token'ları (müşteri onay bağlantısı — mutabakat vb.)
CREATE TABLE IF NOT EXISTS `document_access_tokens` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type`  VARCHAR(60)  NOT NULL DEFAULT '',
    `document_id`    INT UNSIGNED NOT NULL,
    `token_hash`     CHAR(64)     NOT NULL,                  -- sha256(token); ham token saklanmaz
    `expires_at`     DATETIME     DEFAULT NULL,
    `revoked_at`     DATETIME     DEFAULT NULL,
    `used_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `last_used_at`   DATETIME     DEFAULT NULL,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dat_hash` (`token_hash`),
    KEY `idx_dat_doc` (`document_type`, `document_id`),
    CONSTRAINT `fk_dat_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Belge onayları (müşteri dijital onayı — mutabıkız / mutabık değiliz)
CREATE TABLE IF NOT EXISTS `document_approvals` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type`  VARCHAR(60)  NOT NULL DEFAULT '',
    `document_id`    INT UNSIGNED NOT NULL,
    `token_id`       BIGINT UNSIGNED DEFAULT NULL,
    `decision`       ENUM('agreed','disagreed') NOT NULL,
    `note`           TEXT         DEFAULT NULL,
    `authorized_name`VARCHAR(190) NOT NULL DEFAULT '',
    `ip_address`     VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent`     VARCHAR(255) NOT NULL DEFAULT '',
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dap_doc` (`document_type`, `document_id`),
    CONSTRAINT `fk_dap_token` FOREIGN KEY (`token_id`) REFERENCES `document_access_tokens` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) Kullanıcı e-posta tercihleri
CREATE TABLE IF NOT EXISTS `user_email_preferences` (
    `user_id`               INT UNSIGNED NOT NULL,
    `corporate_email`       VARCHAR(190) NOT NULL DEFAULT '',   -- boşsa users.email kullanılır
    `department`            VARCHAR(150) NOT NULL DEFAULT '',
    `default_account_id`    INT UNSIGNED DEFAULT NULL,          -- department_email_accounts
    `can_receive_replies`   TINYINT(1)   NOT NULL DEFAULT 1,
    `auto_cc`               TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
    `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_uep_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_uep_account` FOREIGN KEY (`default_account_id`) REFERENCES `department_email_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
