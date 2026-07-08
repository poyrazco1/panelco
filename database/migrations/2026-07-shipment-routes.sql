-- =========================================================================
-- Sevkiyat: Rota tabanlı model (Adres Defteri + Günlük Rota + Duraklar +
-- Teslimat/Toplama Kanıtı). Mevcut kurulumları güvenle yükseltmek için
-- idempotent (tekrar çalıştırılabilir) migration. MariaDB 10.2+ /
-- MySQL 8 uyumlu. Fresh kurulum için aynı tanımlar install.sql içindedir.
-- =========================================================================

-- 1) Günlük rotalar
CREATE TABLE IF NOT EXISTS `shipment_routes` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `route_name`     VARCHAR(190) NOT NULL,
    `route_date`     DATE         DEFAULT NULL,
    `driver_user_id` INT UNSIGNED DEFAULT NULL,
    `start_point`    VARCHAR(255) DEFAULT NULL,
    `end_point`      VARCHAR(255) DEFAULT NULL,
    `status`         VARCHAR(20)  NOT NULL DEFAULT 'draft',
    `note`           TEXT         DEFAULT NULL,
    `started_at`     DATETIME     DEFAULT NULL,
    `completed_at`   DATETIME     DEFAULT NULL,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `updated_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ship_routes_date` (`route_date`),
    KEY `idx_ship_routes_driver` (`driver_user_id`),
    KEY `idx_ship_routes_status` (`status`),
    KEY `idx_ship_routes_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Rota durakları
CREATE TABLE IF NOT EXISTS `shipment_route_stops` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `route_id`       INT UNSIGNED NOT NULL,
    `address_id`     INT UNSIGNED DEFAULT NULL,
    `stop_order`     INT UNSIGNED NOT NULL DEFAULT 0,
    `operation_type` VARCHAR(20)  NOT NULL DEFAULT 'delivery',
    `status`         VARCHAR(20)  NOT NULL DEFAULT 'pending',
    `reference_no`   VARCHAR(80)  DEFAULT NULL,
    `stop_note`      TEXT         DEFAULT NULL,
    `driver_note`    TEXT         DEFAULT NULL,
    `completed_at`   DATETIME     DEFAULT NULL,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `updated_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ship_stops_route` (`route_id`),
    KEY `idx_ship_stops_addr` (`address_id`),
    KEY `idx_ship_stops_status` (`status`),
    KEY `idx_ship_stops_order` (`route_id`, `stop_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Teslimat / toplama kanıtı (fotoğraf)
CREATE TABLE IF NOT EXISTS `shipment_proofs` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `route_id`      INT UNSIGNED DEFAULT NULL,
    `stop_id`       INT UNSIGNED DEFAULT NULL,
    `uploaded_by`   INT UNSIGNED DEFAULT NULL,
    `file_path`     VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) DEFAULT NULL,
    `mime_type`     VARCHAR(100) DEFAULT NULL,
    `file_size`     INT UNSIGNED DEFAULT NULL,
    `note`          VARCHAR(500) DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ship_proofs_route` (`route_id`),
    KEY `idx_ship_proofs_stop` (`stop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) shipment_status_logs: rota/durak loglarını da tutabilsin.
--    (Eski shipment_id tabanlı loglar korunur; yeni kolonlar nullable eklenir.)
ALTER TABLE `shipment_status_logs`
    MODIFY `shipment_id` INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `route_id` INT UNSIGNED DEFAULT NULL AFTER `shipment_id`,
    ADD COLUMN IF NOT EXISTS `stop_id`  INT UNSIGNED DEFAULT NULL AFTER `route_id`;

-- 5) Yeni sevkiyat ayarları (varsayılan başlangıç/bitiş, foto limitleri)
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('shipment_default_start', ''),
    ('shipment_default_end', ''),
    ('shipment_photo_max_mb', '8'),
    ('shipment_photo_types', 'jpg,jpeg,png,webp');
