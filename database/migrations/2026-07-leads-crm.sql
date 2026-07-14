-- =========================================================================
-- LEAD CRM + GOOGLE PLACES — tablo temeli (§17). Idempotent.
-- Mevcut `leads`, `lead_scans`, `lead_api_log` korunur; leads genişletilir.
-- Fresh kurulum için aynı tanımlar install.sql içindedir.
-- =========================================================================
SET NAMES utf8mb4;

-- ---- 1) leads: eksik alanları ekle (tümü yoksa tek ALTER; idempotent) ----
SET @need := (SELECT COUNT(*)=0 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='leads' AND COLUMN_NAME='place_id');
SET @sql := IF(@need,
 'ALTER TABLE `leads`
    ADD COLUMN `place_id` VARCHAR(160) NULL AFTER `id`,
    ADD COLUMN `alt_phone` VARCHAR(40) NULL AFTER `phone`,
    ADD COLUMN `domain` VARCHAR(190) NULL AFTER `website`,
    ADD COLUMN `country` VARCHAR(80) NULL AFTER `sector`,
    ADD COLUMN `neighborhood` VARCHAR(120) NULL AFTER `district`,
    ADD COLUMN `postal_code` VARCHAR(20) NULL AFTER `neighborhood`,
    ADD COLUMN `lat` DECIMAL(10,7) NULL AFTER `address`,
    ADD COLUMN `lng` DECIMAL(10,7) NULL AFTER `lat`,
    ADD COLUMN `main_category` VARCHAR(120) NULL AFTER `sector`,
    ADD COLUMN `other_categories` VARCHAR(500) NULL AFTER `main_category`,
    ADD COLUMN `working_status` VARCHAR(40) NULL,
    ADD COLUMN `working_hours` TEXT NULL,
    ADD COLUMN `search_keyword` VARCHAR(190) NULL,
    ADD COLUMN `last_contact_at` DATETIME NULL,
    ADD COLUMN `next_action_at` DATETIME NULL,
    ADD COLUMN `deleted_at` DATETIME NULL,
    ADD COLUMN `deleted_by` INT UNSIGNED NULL,
    ADD COLUMN `delete_reason` VARCHAR(255) NULL,
    ADD UNIQUE KEY `uq_leads_place` (`place_id`),
    ADD KEY `idx_leads_domain` (`domain`),
    ADD KEY `idx_leads_assigned` (`assigned_personnel_id`),
    ADD KEY `idx_leads_next_action` (`next_action_at`)',
 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---- 2) Google Places ayarları (tek satır; şifreli anahtar) ----
CREATE TABLE IF NOT EXISTS `google_places_settings` (
    `id`                 TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `api_key_enc`        TEXT DEFAULT NULL,
    `is_active`          TINYINT(1) NOT NULL DEFAULT 0,
    `default_country`    VARCHAR(80)  NOT NULL DEFAULT 'Türkiye',
    `default_city`       VARCHAR(80)  NOT NULL DEFAULT '',
    `default_language`   VARCHAR(10)  NOT NULL DEFAULT 'tr',
    `default_radius`     INT UNSIGNED NOT NULL DEFAULT 5000,
    `max_results`        INT UNSIGNED NOT NULL DEFAULT 60,
    `daily_query_limit`  INT UNSIGNED NOT NULL DEFAULT 1000,
    `monthly_est_limit`  INT UNSIGNED NOT NULL DEFAULT 20000,
    `block_duplicates`   TINYINT(1) NOT NULL DEFAULT 1,
    `include_no_phone`   TINYINT(1) NOT NULL DEFAULT 1,
    `include_no_website` TINYINT(1) NOT NULL DEFAULT 1,
    `auto_details`       TINYINT(1) NOT NULL DEFAULT 1,
    `last_success_at`    DATETIME DEFAULT NULL,
    `last_error`         VARCHAR(255) NOT NULL DEFAULT '',
    `updated_by`         INT UNSIGNED DEFAULT NULL,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `google_places_settings` (`id`) VALUES (1);

-- ---- 3) Google Places aramaları (çalıştırılan taramalar) ----
CREATE TABLE IF NOT EXISTS `google_places_searches` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `search_type`    ENUM('text','nearby') NOT NULL DEFAULT 'text',
    `keyword`        VARCHAR(190) NOT NULL DEFAULT '',
    `country`        VARCHAR(80)  NOT NULL DEFAULT '',
    `city`           VARCHAR(80)  NOT NULL DEFAULT '',
    `district`       VARCHAR(120) NOT NULL DEFAULT '',
    `lat`            DECIMAL(10,7) NULL,
    `lng`            DECIMAL(10,7) NULL,
    `radius`         INT UNSIGNED NULL,
    `filters_json`   TEXT DEFAULT NULL,
    `result_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `new_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `dup_count`      INT UNSIGNED NOT NULL DEFAULT 0,
    `api_calls`      INT UNSIGNED NOT NULL DEFAULT 0,
    `est_cost`       DECIMAL(10,4) NOT NULL DEFAULT 0,
    `status`         ENUM('running','done','error') NOT NULL DEFAULT 'running',
    `error_message`  VARCHAR(255) NOT NULL DEFAULT '',
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_gps_created` (`created_at`), KEY `idx_gps_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 4) Arama sonuçları (önizleme; lead'e dönüşmeden önce) ----
CREATE TABLE IF NOT EXISTS `google_places_search_results` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `search_id`     INT UNSIGNED NOT NULL,
    `place_id`      VARCHAR(160) NOT NULL DEFAULT '',
    `name`          VARCHAR(190) NOT NULL DEFAULT '',
    `main_category` VARCHAR(120) NOT NULL DEFAULT '',
    `phone`         VARCHAR(40)  NOT NULL DEFAULT '',
    `intl_phone`    VARCHAR(40)  NOT NULL DEFAULT '',
    `website`       VARCHAR(255) NOT NULL DEFAULT '',
    `maps_url`      VARCHAR(500) NOT NULL DEFAULT '',
    `address`       VARCHAR(500) NOT NULL DEFAULT '',
    `city`          VARCHAR(80)  NOT NULL DEFAULT '',
    `district`      VARCHAR(120) NOT NULL DEFAULT '',
    `lat`           DECIMAL(10,7) NULL,
    `lng`           DECIMAL(10,7) NULL,
    `rating`        DECIMAL(2,1) NULL,
    `review_count`  INT UNSIGNED NULL,
    `working_status` VARCHAR(40) NOT NULL DEFAULT '',
    `raw_json`      MEDIUMTEXT DEFAULT NULL,
    `is_duplicate`  TINYINT(1) NOT NULL DEFAULT 0,
    `dup_lead_id`   INT UNSIGNED NULL,
    `saved_lead_id` INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_gpsr_search` (`search_id`), KEY `idx_gpsr_place` (`place_id`),
    CONSTRAINT `fk_gpsr_search` FOREIGN KEY (`search_id`) REFERENCES `google_places_searches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 5) API kullanım/maliyet logu ----
CREATE TABLE IF NOT EXISTS `google_places_api_usage` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `endpoint`     VARCHAR(60)  NOT NULL DEFAULT '',
    `op_type`      VARCHAR(40)  NOT NULL DEFAULT '',
    `search_id`    INT UNSIGNED NULL,
    `user_id`      INT UNSIGNED NULL,
    `result_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `success`      TINYINT(1) NOT NULL DEFAULT 0,
    `http_code`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `google_error` VARCHAR(120) NOT NULL DEFAULT '',
    `est_cost`     DECIMAL(10,4) NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_gpu_created` (`created_at`), KEY `idx_gpu_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 6) Lead durumları (yönetilebilir) ----
CREATE TABLE IF NOT EXISTS `lead_statuses` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`         VARCHAR(40)  NOT NULL,
    `name`         VARCHAR(80)  NOT NULL,
    `color`        VARCHAR(20)  NOT NULL DEFAULT 'badge-muted',
    `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
    `is_success`   TINYINT(1) NOT NULL DEFAULT 0,
    `is_failure`   TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_lead_status_code` (`code`), KEY `idx_lead_status_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 7) Durum geçmişi ----
CREATE TABLE IF NOT EXISTS `lead_status_history` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`    INT UNSIGNED NOT NULL,
    `old_status` VARCHAR(40) NOT NULL DEFAULT '',
    `new_status` VARCHAR(40) NOT NULL DEFAULT '',
    `note`       VARCHAR(500) NOT NULL DEFAULT '',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lsh_lead` (`lead_id`),
    CONSTRAINT `fk_lsh_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 8) Aktivite geçmişi ----
CREATE TABLE IF NOT EXISTS `lead_activities` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(40) NOT NULL DEFAULT '',
    `summary`    VARCHAR(500) NOT NULL DEFAULT '',
    `meta_json`  TEXT DEFAULT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_la_lead` (`lead_id`), KEY `idx_la_created` (`created_at`),
    CONSTRAINT `fk_la_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 9) Arama (telefon) kayıtları ----
CREATE TABLE IF NOT EXISTS `lead_call_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`     INT UNSIGNED NOT NULL,
    `result`      VARCHAR(40) NOT NULL DEFAULT '',
    `contact_person` VARCHAR(150) NOT NULL DEFAULT '',
    `duration_sec` INT UNSIGNED NULL,
    `note`        TEXT DEFAULT NULL,
    `next_action` VARCHAR(120) NOT NULL DEFAULT '',
    `next_at`     DATETIME NULL,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lcl_lead` (`lead_id`),
    CONSTRAINT `fk_lcl_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 10) WhatsApp kayıtları ----
CREATE TABLE IF NOT EXISTS `lead_whatsapp_logs` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`     INT UNSIGNED NOT NULL,
    `template_id` INT UNSIGNED NULL,
    `phone`       VARCHAR(40) NOT NULL DEFAULT '',
    `message`     TEXT DEFAULT NULL,
    `note`        VARCHAR(255) NOT NULL DEFAULT '',
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lwl_lead` (`lead_id`),
    CONSTRAINT `fk_lwl_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 11) Notlar ----
CREATE TABLE IF NOT EXISTS `lead_notes` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`    INT UNSIGNED NOT NULL,
    `note`       TEXT DEFAULT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_ln_lead` (`lead_id`),
    CONSTRAINT `fk_ln_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 12) Hatırlatmalar ----
CREATE TABLE IF NOT EXISTS `lead_reminders` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`     INT UNSIGNED NOT NULL,
    `type`        VARCHAR(30) NOT NULL DEFAULT 'call',
    `remind_at`   DATETIME NOT NULL,
    `note`        VARCHAR(500) NOT NULL DEFAULT '',
    `assigned_to` INT UNSIGNED NULL,
    `is_done`     TINYINT(1) NOT NULL DEFAULT 0,
    `done_at`     DATETIME NULL,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lr_lead` (`lead_id`), KEY `idx_lr_remind` (`remind_at`), KEY `idx_lr_done` (`is_done`),
    CONSTRAINT `fk_lr_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 13) Atamalar (geçmiş) ----
CREATE TABLE IF NOT EXISTS `lead_assignments` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`     INT UNSIGNED NOT NULL,
    `personnel_id` INT UNSIGNED NULL,
    `assigned_by` INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lasg_lead` (`lead_id`),
    CONSTRAINT `fk_lasg_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 14) Etiketler + ilişki ----
CREATE TABLE IF NOT EXISTS `lead_tags` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(60) NOT NULL,
    `color`      VARCHAR(20) NOT NULL DEFAULT 'badge-muted',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_lead_tag_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_tag_relations` (
    `lead_id` INT UNSIGNED NOT NULL,
    `tag_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`lead_id`, `tag_id`),
    CONSTRAINT `fk_ltr_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ltr_tag`  FOREIGN KEY (`tag_id`) REFERENCES `lead_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 15) Kayıtlı aramalar ----
CREATE TABLE IF NOT EXISTS `lead_saved_searches` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `config_json` MEDIUMTEXT DEFAULT NULL,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lss_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 16) Çalışma listeleri (arama/whatsapp) ----
CREATE TABLE IF NOT EXISTS `lead_work_lists` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`     INT UNSIGNED NOT NULL,
    `personnel_id` INT UNSIGNED NOT NULL,
    `list_type`   ENUM('call','whatsapp') NOT NULL DEFAULT 'call',
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lwl_active` (`lead_id`, `list_type`),
    KEY `idx_lwl_person` (`personnel_id`, `list_type`, `is_active`),
    CONSTRAINT `fk_lwl_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 17) Denetim (audit) logu ----
CREATE TABLE IF NOT EXISTS `lead_audit_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`    INT UNSIGNED NULL,
    `action`     VARCHAR(60) NOT NULL DEFAULT '',
    `detail`     TEXT DEFAULT NULL,
    `user_id`    INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lal_lead` (`lead_id`), KEY `idx_lal_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Varsayılan lead durumları (§6) ----
INSERT IGNORE INTO `lead_statuses` (`code`,`name`,`color`,`sort_order`,`is_completed`,`is_success`,`is_failure`) VALUES
 ('new','Yeni Lead','badge-info',10,0,0,0),
 ('to_review','İncelenecek','badge-muted',20,0,0,0),
 ('to_call','Arama Bekliyor','badge-warning',30,0,0,0),
 ('called','Arandı','badge-info',40,0,0,0),
 ('unreachable','Ulaşılamadı','badge-warning',50,0,0,0),
 ('call_again','Tekrar Aranacak','badge-warning',60,0,0,0),
 ('wa_to_send','WhatsApp Gönderilecek','badge-warning',70,0,0,0),
 ('wa_sent','WhatsApp Gönderildi','badge-info',80,0,0,0),
 ('email_sent','E-posta Gönderildi','badge-info',90,0,0,0),
 ('met','Görüşme Yapıldı','badge-info',100,0,0,0),
 ('wants_quote','Teklif İstiyor','badge-info',110,0,0,0),
 ('quote_sent','Teklif Gönderildi','badge-info',120,0,0,0),
 ('positive','Olumlu','badge-success',130,0,0,0),
 ('converted','Müşteriye Dönüştü','badge-success',140,1,1,0),
 ('not_interested','İlgilenmiyor','badge-muted',150,1,0,1),
 ('wrong_number','Yanlış Numara','badge-muted',160,1,0,1),
 ('closed','Firma Kapalı','badge-muted',170,1,0,1),
 ('blacklist','Kara Liste','badge-danger',180,1,0,1);
