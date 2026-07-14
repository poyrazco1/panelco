-- =========================================================================
-- LEAD TAKİP (FOLLOW-UP) + BİLDİRİM SİSTEMİ (§21). Idempotent.
-- lead_reminders genişletilir; postpone geçmişi + user_notifications alanları
-- + lead_statuses.requires_followup eklenir. Fresh kurulum install.sql'de.
-- =========================================================================
SET NAMES utf8mb4;

-- ---- 1) lead_reminders: zengin takip alanları (tümü yoksa tek ALTER) ----
SET @need := (SELECT COUNT(*)=0 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lead_reminders' AND COLUMN_NAME='status');
SET @sql := IF(@need,
 'ALTER TABLE `lead_reminders`
    ADD COLUMN `assigned_user_id` INT UNSIGNED NULL AFTER `lead_id`,
    ADD COLUMN `reminder_type` VARCHAR(30) NOT NULL DEFAULT ''call'' AFTER `type`,
    ADD COLUMN `reminder_date` DATE NULL AFTER `remind_at`,
    ADD COLUMN `reminder_time` TIME NULL AFTER `reminder_date`,
    ADD COLUMN `remind_before_minutes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `reminder_time`,
    ADD COLUMN `priority` VARCHAR(20) NOT NULL DEFAULT ''normal'' AFTER `remind_before_minutes`,
    ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT ''pending'' AFTER `priority`,
    ADD COLUMN `is_overdue` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN `postpone_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_overdue`,
    ADD COLUMN `notify_stage` VARCHAR(20) NOT NULL DEFAULT '''' AFTER `postpone_count`,
    ADD COLUMN `completed_at` DATETIME NULL,
    ADD COLUMN `completed_by` INT UNSIGNED NULL,
    ADD COLUMN `completion_result` VARCHAR(40) NULL,
    ADD COLUMN `completion_note` TEXT NULL,
    ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN `deleted_at` DATETIME NULL,
    ADD KEY `idx_lr_assigned_user` (`assigned_user_id`),
    ADD KEY `idx_lr_status` (`status`),
    ADD KEY `idx_lr_overdue` (`is_overdue`),
    ADD KEY `idx_lr_remind_at` (`remind_at`)',
 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Mevcut kayıtları yeni alanlara taşı (idempotent; yalnız boş olanları doldurur)
UPDATE `lead_reminders`
   SET `assigned_user_id` = COALESCE(`assigned_user_id`, `assigned_to`),
       `reminder_type`    = CASE WHEN `reminder_type` = '' OR `reminder_type` IS NULL THEN COALESCE(`type`,'call') ELSE `reminder_type` END,
       `reminder_date`    = COALESCE(`reminder_date`, DATE(`remind_at`)),
       `reminder_time`    = COALESCE(`reminder_time`, TIME(`remind_at`)),
       `status`           = CASE WHEN `is_done` = 1 THEN 'done' ELSE `status` END,
       `completed_at`     = CASE WHEN `is_done` = 1 THEN COALESCE(`completed_at`, `done_at`) ELSE `completed_at` END
 WHERE `assigned_user_id` IS NULL OR `reminder_date` IS NULL;

-- ---- 2) Erteleme (postpone) geçmişi ----
CREATE TABLE IF NOT EXISTS `lead_reminder_postpones` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reminder_id`  BIGINT UNSIGNED NOT NULL,
    `lead_id`      INT UNSIGNED NOT NULL,
    `old_remind_at` DATETIME NULL,
    `new_remind_at` DATETIME NULL,
    `reason`       VARCHAR(500) NOT NULL DEFAULT '',
    `postponed_by` INT UNSIGNED NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lrp_reminder` (`reminder_id`), KEY `idx_lrp_lead` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- 3) user_notifications: aksiyon linki + kapatma (dismiss) ----
SET @need := (SELECT COUNT(*)=0 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_notifications' AND COLUMN_NAME='action_url');
SET @sql := IF(@need,
 'ALTER TABLE `user_notifications`
    ADD COLUMN `action_url` VARCHAR(255) NULL AFTER `related_id`,
    ADD COLUMN `is_dismissed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `read_at`,
    ADD COLUMN `dismissed_at` DATETIME NULL AFTER `is_dismissed`',
 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---- 4) lead_statuses: takip zorunluluğu bayrağı ----
SET @need := (SELECT COUNT(*)=0 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lead_statuses' AND COLUMN_NAME='requires_followup');
SET @sql := IF(@need,
 'ALTER TABLE `lead_statuses` ADD COLUMN `requires_followup` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_failure`',
 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Takip gerektiren yeni/mevcut durumlar (§21)
INSERT INTO `lead_statuses` (`code`,`name`,`color`,`sort_order`,`is_completed`,`is_success`,`is_failure`,`requires_followup`) VALUES
 ('call_later','Daha Sonra Aranacak','badge-warning',35,0,0,0,1),
 ('meet_again','Tekrar Görüşülecek','badge-warning',105,0,0,0,1),
 ('quote_followup','Teklif İçin Dönüş Yapılacak','badge-warning',115,0,0,0,1)
ON DUPLICATE KEY UPDATE `requires_followup` = VALUES(`requires_followup`);

-- Mevcut takip gerektiren durumlara bayrağı işle
UPDATE `lead_statuses` SET `requires_followup` = 1
 WHERE `code` IN ('call_again','wa_to_send');
