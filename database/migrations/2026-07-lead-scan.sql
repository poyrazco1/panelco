-- =========================================================================
-- LEAD TARAMA (Lead Scan Wizard) — tarama işleri + zenginleştirilmiş lead alanları.
-- Fresh kurulum için aynı tanımlar install.sql içindedir. Bu migration mevcut
-- kurulumları güvenle yükseltir (MariaDB IF NOT EXISTS). Mevcut lead/API mantığı
-- BOZULMAZ; yeni alanlar nullable eklenir.
-- =========================================================================
SET NAMES utf8mb4;

-- Zenginleştirilmiş lead alanları (Google puanı, yorum, paket, öncelik, adres, tarama)
ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `address`       VARCHAR(500) DEFAULT NULL AFTER `district`,
    ADD COLUMN IF NOT EXISTS `google_rating` DECIMAL(2,1) DEFAULT NULL AFTER `maps_url`,
    ADD COLUMN IF NOT EXISTS `review_count`  INT UNSIGNED DEFAULT NULL AFTER `google_rating`,
    ADD COLUMN IF NOT EXISTS `has_website`   TINYINT(1)   DEFAULT NULL AFTER `website`,
    ADD COLUMN IF NOT EXISTS `package`       VARCHAR(80)  DEFAULT NULL AFTER `source`,
    ADD COLUMN IF NOT EXISTS `priority`      VARCHAR(20)  NOT NULL DEFAULT 'normal' AFTER `status`,
    ADD COLUMN IF NOT EXISTS `scan_id`       INT UNSIGNED DEFAULT NULL AFTER `assigned_personnel_id`;

-- Tarama işleri
CREATE TABLE IF NOT EXISTS `lead_scans` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                  VARCHAR(190) DEFAULT NULL,
    `config_json`           LONGTEXT     DEFAULT NULL,
    `combos_count`          INT UNSIGNED NOT NULL DEFAULT 0,
    `target_limit`          INT UNSIGNED NOT NULL DEFAULT 0,
    `found_count`           INT UNSIGNED NOT NULL DEFAULT 0,
    `saved_count`           INT UNSIGNED NOT NULL DEFAULT 0,
    `duplicate_count`       INT UNSIGNED NOT NULL DEFAULT 0,
    `skipped_count`         INT UNSIGNED NOT NULL DEFAULT 0,
    `error_count`           INT UNSIGNED NOT NULL DEFAULT 0,
    `status`                VARCHAR(20)  NOT NULL DEFAULT 'draft',
    `package`               VARCHAR(80)  DEFAULT NULL,
    `source`                VARCHAR(80)  DEFAULT NULL,
    `assigned_personnel_id` INT UNSIGNED DEFAULT NULL,
    `created_by`            INT UNSIGNED DEFAULT NULL,
    `updated_by`            INT UNSIGNED DEFAULT NULL,
    `started_at`            DATETIME     DEFAULT NULL,
    `completed_at`          DATETIME     DEFAULT NULL,
    `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`            DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_lead_scans_status` (`status`),
    KEY `idx_lead_scans_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead paketleri + tarama varsayılanları (app_settings)
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('lead_scan_target_default', '100');
