-- =========================================================================
-- FİNANS — Cari hareketler (tahsilat/ödeme/borç/alacak) + cari ekstre kaynağı.
-- Fresh kurulum için aynı tanım install.sql içindedir. Bu migration mevcut
-- kurulumları güvenle yükseltir (idempotent).
-- =========================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `cari_movements` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`   INT UNSIGNED DEFAULT NULL,
    `customer_name` VARCHAR(190) NOT NULL DEFAULT '',
    `movement_date` DATE         DEFAULT NULL,
    `doc_type`      ENUM('collection','payment','invoice','manual') NOT NULL DEFAULT 'manual',
    `direction`     ENUM('debit','credit') NOT NULL DEFAULT 'debit',
    `amount`        DECIMAL(15,2) NOT NULL DEFAULT 0,
    `currency`      VARCHAR(10)  NOT NULL DEFAULT 'TRY',
    `method`        VARCHAR(60)  NOT NULL DEFAULT '',
    `reference`     VARCHAR(100) NOT NULL DEFAULT '',
    `receipt_no`    VARCHAR(40)  NOT NULL DEFAULT '',
    `description`   VARCHAR(500) NOT NULL DEFAULT '',
    `is_deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cari_customer` (`customer_id`),
    KEY `idx_cari_date` (`movement_date`),
    KEY `idx_cari_deleted` (`is_deleted`),
    KEY `idx_cari_receipt` (`receipt_no`),
    CONSTRAINT `fk_cari_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cari_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
