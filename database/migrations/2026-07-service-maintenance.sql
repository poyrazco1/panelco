-- =========================================================================
-- TEKNİK SERVİS — 6 AYLIK (PERİYODİK) BAKIM HATIRLATMA SİSTEMİ. Idempotent.
--
--  MariaDB / MySQL 8 uyumlu. utf8mb4 ile yükleyin:
--    mysql --default-character-set=utf8mb4 DB < 2026-07-service-maintenance.sql
--
--  Not: Uygulama, includes/service_maintenance.php içindeki smaint_ensure_schema()
--  ile bu tabloları çalışma anında da (CREATE TABLE IF NOT EXISTS) oluşturur;
--  bu migration elle kurulum / taze kurulum içindir. Aynı tanımlar install.sql'de.
-- =========================================================================
SET NAMES utf8mb4;

-- ---- 1) Bakım hatırlatma kayıtları (§2, §16) ----
CREATE TABLE IF NOT EXISTS `service_maintenance_reminders` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_id`          INT UNSIGNED NOT NULL,
    `service_product_id`  INT UNSIGNED NOT NULL DEFAULT 0,
    `customer_id`         INT UNSIGNED NULL,
    `assigned_user_id`    INT UNSIGNED NULL,
    `source_delivery_date` DATE NOT NULL,
    `delivery_date`       DATE NULL,
    `maintenance_due_date` DATE NOT NULL,
    `period_key`          VARCHAR(10)  NOT NULL DEFAULT '6m',
    `period_days`         INT UNSIGNED NULL,
    `reminder_stage`      VARCHAR(10)  NOT NULL DEFAULT '',
    `status`              VARCHAR(30)  NOT NULL DEFAULT 'planned',
    `preferred_channel`   VARCHAR(20)  NULL,
    `email_consent`       TINYINT(1)   NOT NULL DEFAULT 0,
    `whatsapp_consent`    TINYINT(1)   NOT NULL DEFAULT 0,
    `phone_consent`       TINYINT(1)   NOT NULL DEFAULT 0,
    `customer_name`       VARCHAR(190) NULL,
    `company_name`        VARCHAR(190) NULL,
    `contact_name`        VARCHAR(150) NULL,
    `phone`               VARCHAR(40)  NULL,
    `whatsapp`            VARCHAR(40)  NULL,
    `email`               VARCHAR(190) NULL,
    `brand_name`          VARCHAR(120) NULL,
    `device_model`        VARCHAR(150) NULL,
    `serial_no`           VARCHAR(120) NULL,
    `work_done`           TEXT         NULL,
    `description`         TEXT         NULL,
    `last_contact_at`     DATETIME NULL,
    `next_contact_at`     DATETIME NULL,
    `last_template_key`   VARCHAR(60) NULL,
    `last_message_at`     DATETIME NULL,
    `reminder_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `postpone_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `response_result`     VARCHAR(40) NULL,
    `response_note`       VARCHAR(500) NULL,
    `response_at`         DATETIME NULL,
    `appointment_at`      DATETIME NULL,
    `appointment_service_type` VARCHAR(60) NULL,
    `appointment_location` VARCHAR(20) NULL,
    `appointment_technician_id` INT UNSIGNED NULL,
    `appointment_note`    VARCHAR(500) NULL,
    `converted_service_id` INT UNSIGNED NULL,
    `completed_at`        DATETIME NULL,
    `completed_by`        INT UNSIGNED NULL,
    `cancelled_at`        DATETIME NULL,
    `cancelled_by`        INT UNSIGNED NULL,
    `cancel_reason`       VARCHAR(500) NULL,
    `created_by`          INT UNSIGNED NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`          DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_smr_period` (`service_id`, `service_product_id`, `source_delivery_date`),
    KEY `idx_smr_service` (`service_id`),
    KEY `idx_smr_customer` (`customer_id`),
    KEY `idx_smr_assigned` (`assigned_user_id`),
    KEY `idx_smr_due` (`maintenance_due_date`),
    KEY `idx_smr_status` (`status`),
    KEY `idx_smr_stage` (`reminder_stage`),
    KEY `idx_smr_next` (`next_contact_at`),
    KEY `idx_smr_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 2) İletişim (e-posta / WhatsApp) gönderim kayıtları (§6, §7, §16) ----
CREATE TABLE IF NOT EXISTS `service_maintenance_communications` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reminder_id`    BIGINT UNSIGNED NOT NULL,
    `service_id`     INT UNSIGNED NULL,
    `channel`        VARCHAR(20)  NOT NULL DEFAULT 'email',
    `direction`      VARCHAR(10)  NOT NULL DEFAULT 'out',
    `to_phone`       VARCHAR(40)  NULL,
    `to_email`       VARCHAR(190) NULL,
    `subject`        VARCHAR(300) NULL,
    `body`           MEDIUMTEXT   NULL,
    `template_key`   VARCHAR(60)  NULL,
    `template_name`  VARCHAR(150) NULL,
    `from_account`   VARCHAR(190) NULL,
    `sent_by_user_id` INT UNSIGNED NULL,
    `sent_by_name`   VARCHAR(190) NULL,
    `wa_opened_at`   DATETIME NULL,
    `marked_sent_at` DATETIME NULL,
    `status`         VARCHAR(20)  NOT NULL DEFAULT 'pending',
    `customer_reply` VARCHAR(30)  NULL,
    `error_message`  TEXT         NULL,
    `note`           VARCHAR(500) NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_smc_reminder` (`reminder_id`),
    KEY `idx_smc_channel` (`channel`),
    KEY `idx_smc_status` (`status`),
    KEY `idx_smc_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 3) Durum geçmişi (§9, §16) ----
CREATE TABLE IF NOT EXISTS `service_maintenance_status_history` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reminder_id` BIGINT UNSIGNED NOT NULL,
    `old_status`  VARCHAR(30) NOT NULL DEFAULT '',
    `new_status`  VARCHAR(30) NOT NULL DEFAULT '',
    `note`        VARCHAR(500) NOT NULL DEFAULT '',
    `changed_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_smsh_reminder` (`reminder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 4) Erteleme geçmişi (§10, §16) ----
CREATE TABLE IF NOT EXISTS `service_maintenance_postponements` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reminder_id`    BIGINT UNSIGNED NOT NULL,
    `old_due_date`   DATE NULL,
    `new_due_date`   DATE NULL,
    `old_contact_at` DATETIME NULL,
    `new_contact_at` DATETIME NULL,
    `reason`         VARCHAR(500) NOT NULL DEFAULT '',
    `postponed_by`   INT UNSIGNED NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_smp_reminder` (`reminder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 5) Ayarlar (tekil satır) (§13, §16) ----
CREATE TABLE IF NOT EXISTS `service_maintenance_settings` (
    `id`                    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `is_active`             TINYINT(1) NOT NULL DEFAULT 1,
    `default_period_key`    VARCHAR(10) NOT NULL DEFAULT '6m',
    `default_period_days`   INT UNSIGNED NOT NULL DEFAULT 180,
    `dashboard_enabled`     TINYINT(1) NOT NULL DEFAULT 1,
    `email_enabled`         TINYINT(1) NOT NULL DEFAULT 1,
    `whatsapp_enabled`      TINYINT(1) NOT NULL DEFAULT 1,
    `auto_email_enabled`    TINYINT(1) NOT NULL DEFAULT 0,
    `manual_email_approval` TINYINT(1) NOT NULL DEFAULT 1,
    `stage_d30`             TINYINT(1) NOT NULL DEFAULT 1,
    `stage_d15`             TINYINT(1) NOT NULL DEFAULT 1,
    `stage_d7`              TINYINT(1) NOT NULL DEFAULT 1,
    `stage_due`             TINYINT(1) NOT NULL DEFAULT 1,
    `stage_o7`              TINYINT(1) NOT NULL DEFAULT 1,
    `stage_o30`             TINYINT(1) NOT NULL DEFAULT 1,
    `overdue_reremind`      TINYINT(1) NOT NULL DEFAULT 1,
    `max_reminders`         INT UNSIGNED NOT NULL DEFAULT 3,
    `default_assigned_user_id` INT UNSIGNED NULL,
    `email_template_id`     INT UNSIGNED NULL,
    `whatsapp_template_id`  INT UNSIGNED NULL,
    `consent_required`      TINYINT(1) NOT NULL DEFAULT 1,
    `sound_enabled`         TINYINT(1) NOT NULL DEFAULT 1,
    `whatsapp_api_enabled`  TINYINT(1) NOT NULL DEFAULT 0,
    `updated_by`            INT UNSIGNED NULL,
    `updated_at`            DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `service_maintenance_settings` (`id`) VALUES (1);

-- ---- 6) Mesaj şablonları (§6, §7, §16) ----
CREATE TABLE IF NOT EXISTS `service_maintenance_templates` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel`    VARCHAR(20)  NOT NULL DEFAULT 'email',
    `name`       VARCHAR(150) NOT NULL,
    `subject`    VARCHAR(300) NULL,
    `body`       MEDIUMTEXT   NOT NULL,
    `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_smt_channel` (`channel`),
    KEY `idx_smt_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 7) Cron çalışma kayıtları (§12, §16) ----
CREATE TABLE IF NOT EXISTS `service_maintenance_cron_logs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `run_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `upcoming_found` INT NOT NULL DEFAULT 0,
    `due_marked`     INT NOT NULL DEFAULT 0,
    `overdue_marked` INT NOT NULL DEFAULT 0,
    `notifications_created` INT NOT NULL DEFAULT 0,
    `emails_sent`    INT NOT NULL DEFAULT 0,
    `errors`         INT NOT NULL DEFAULT 0,
    `duration_ms`    INT NOT NULL DEFAULT 0,
    `detail`         TEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_smcl_run` (`run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 8) Müşteri kartı: iletişim izni kolonları (§8) — idempotent ALTER ----
-- MariaDB: ADD COLUMN IF NOT EXISTS; MySQL 8 için information_schema guard'ı
-- uygulama tarafında (smaint_ensure_customer_consent_columns) yapılır.
SET @need := (SELECT COUNT(*)=0 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='maintenance_opt_in');
SET @sql := IF(@need,
 'ALTER TABLE `customers`
    ADD COLUMN `maintenance_opt_in` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `maint_email_consent` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `maint_whatsapp_consent` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `maint_phone_consent` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN `maint_consent_at` DATETIME NULL,
    ADD COLUMN `maint_consent_source` VARCHAR(60) NULL,
    ADD COLUMN `maint_consent_by_user_id` INT UNSIGNED NULL,
    ADD COLUMN `maint_consent_revoked_at` DATETIME NULL',
 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
