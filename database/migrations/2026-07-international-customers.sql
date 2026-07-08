-- =========================================================================
--  EK PART 27 — YURTDIŞI MÜŞTERİLER / İTHALAT-İHRACAT CRM
--  Uluslararası müşteriler + ürün listeleri + mesaj şablonları/logları.
--
--  İLKELER:
--   - Fiziksel silme YOK: is_deleted / deleted_at (soft delete).
--   - Kayıt numarası benzersiz + değişmez: INT-2026-000001 (record_no UNIQUE).
--   - Kimse veri kaybetmez: yalnızca CREATE TABLE IF NOT EXISTS.
--   - utf8mb4 (Türkçe + uluslararası karakter güvenli).
--   - Toplu SPAM yoktur; mesaj gönderimi kullanıcı onaylıdır ve loglanır.
--
--  MariaDB uyumlu. utf8mb4 ile yükleyin:
--    mysql --default-character-set=utf8mb4 DB < 2026-07-international-customers.sql
-- =========================================================================
SET NAMES utf8mb4;

/* -----------------------------------------------------------------------
 |  1) Uluslararası müşteri durumları (düzenlenebilir)
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_statuses` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `color`       VARCHAR(20)  NOT NULL DEFAULT 'muted',
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `is_default`  TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `updated_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ics_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `international_customer_statuses` (`id`, `name`, `color`, `sort_order`, `is_default`, `is_active`) VALUES
    (1, 'Potansiyel',    'info',    1, 1, 1),
    (2, 'İletişimde',    'leave',   2, 0, 1),
    (3, 'Teklif Verildi','info',    3, 0, 1),
    (4, 'Müşteri',       'success', 4, 0, 1),
    (5, 'Pasif',         'muted',   5, 0, 1),
    (6, 'İlgilenmiyor',  'danger',  6, 0, 1);

/* -----------------------------------------------------------------------
 |  2) Şirket tipleri (çok seçimli): Alıcı/Satıcı/Tedarikçi/Bayi ...
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `company_types` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_company_types_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `company_types` (`id`, `name`, `sort_order`) VALUES
    (1, 'Alıcı',            1),
    (2, 'Satıcı',           2),
    (3, 'Tedarikçi',        3),
    (4, 'Bayi',             4),
    (5, 'Distribütör',      5),
    (6, 'Servis Sağlayıcı', 6),
    (7, 'İthalatçı',        7),
    (8, 'İhracatçı',        8),
    (9, 'Potansiyel',       9),
    (10, 'Diğer',          10);

/* -----------------------------------------------------------------------
 |  3) Uluslararası müşteri kategorileri (ağaç: parent_id)
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`  INT UNSIGNED DEFAULT NULL,
    `name`       VARCHAR(150) NOT NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_icc_parent` (`parent_id`),
    KEY `idx_icc_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `international_customer_categories` (`id`, `parent_id`, `name`, `sort_order`) VALUES
    (1, NULL, 'Yazıcı & Sarf',    1),
    (2, NULL, 'Bilişim / IT',     2),
    (3, NULL, 'Ofis Malzemeleri', 3),
    (4, NULL, 'Elektronik',       4),
    (5, NULL, 'Diğer',            99);

/* -----------------------------------------------------------------------
 |  4) Ana tablo — uluslararası müşteriler
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customers` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `record_no`           VARCHAR(30)  NOT NULL,                 -- INT-2026-000001 (benzersiz, değişmez)
    `company_name`        VARCHAR(190) NOT NULL,
    `company_role`        VARCHAR(20)  NOT NULL DEFAULT 'buyer', -- buyer | seller | both
    `status_id`           INT UNSIGNED DEFAULT NULL,             -- international_customer_statuses
    `category_id`         INT UNSIGNED DEFAULT NULL,             -- birincil kategori (ek: relations tablosu)
    `country`             VARCHAR(80)  DEFAULT NULL,
    `city`                VARCHAR(120) DEFAULT NULL,
    `address`             TEXT         DEFAULT NULL,
    `website`             VARCHAR(190) DEFAULT NULL,
    `email`               VARCHAR(190) DEFAULT NULL,
    `phone`               VARCHAR(60)  DEFAULT NULL,
    `whatsapp`            VARCHAR(60)  DEFAULT NULL,
    `tax_no`              VARCHAR(60)  DEFAULT NULL,
    `currency`            VARCHAR(10)  DEFAULT NULL,
    `language`            VARCHAR(40)  DEFAULT NULL,
    `data_source`         VARCHAR(120) DEFAULT NULL,             -- veri kaynağı (fuar, web, referans...)
    `event_name`          VARCHAR(190) DEFAULT NULL,             -- fuar/etkinlik adı (serbest metin)
    `relationship_status` VARCHAR(60)  DEFAULT NULL,             -- ilişki durumu (iletişim durumundan ayrı)
    `communication_status` VARCHAR(60) DEFAULT NULL,             -- iletişim durumu
    `prepared_by_user_id` INT UNSIGNED DEFAULT NULL,             -- kaydı hazırlayan
    `contact_permission`  TINYINT(1)   NOT NULL DEFAULT 1,       -- iletişim izni var mı
    `is_blacklisted`      TINYINT(1)   NOT NULL DEFAULT 0,       -- kara liste (gönderim engellenir)
    `blacklist_reason`    VARCHAR(255) DEFAULT NULL,
    `notes`               TEXT         DEFAULT NULL,             -- tam-metin/LIKE aranabilir
    `last_message_at`     DATETIME     DEFAULT NULL,
    `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,
    `is_deleted`          TINYINT(1)   NOT NULL DEFAULT 0,
    `deleted_at`          DATETIME     DEFAULT NULL,
    `created_by`          INT UNSIGNED DEFAULT NULL,
    `updated_by`          INT UNSIGNED DEFAULT NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ic_record_no` (`record_no`),
    KEY `idx_ic_company` (`company_name`),
    KEY `idx_ic_country` (`country`),
    KEY `idx_ic_role` (`company_role`),
    KEY `idx_ic_status` (`status_id`),
    KEY `idx_ic_deleted` (`is_deleted`),
    KEY `idx_ic_blacklist` (`is_blacklisted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  5) Müşteri kişileri (bir firmada birden çok yetkili)
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_contacts` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `full_name`   VARCHAR(150) NOT NULL,
    `title`       VARCHAR(120) DEFAULT NULL,
    `department`  VARCHAR(120) DEFAULT NULL,
    `email`       VARCHAR(190) DEFAULT NULL,
    `phone`       VARCHAR(60)  DEFAULT NULL,
    `whatsapp`    VARCHAR(60)  DEFAULT NULL,
    `is_primary`  TINYINT(1)   NOT NULL DEFAULT 0,
    `notes`       VARCHAR(255) DEFAULT NULL,
    `is_deleted`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `updated_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_icc_customer` (`customer_id`),
    KEY `idx_icc_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  6) Durum değişiklik logu
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_status_logs` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`    INT UNSIGNED NOT NULL,
    `old_status_id`  INT UNSIGNED DEFAULT NULL,
    `new_status_id`  INT UNSIGNED DEFAULT NULL,
    `old_label`      VARCHAR(100) DEFAULT NULL,
    `new_label`      VARCHAR(100) DEFAULT NULL,
    `note`           VARCHAR(255) DEFAULT NULL,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_icsl_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  7) Marka ilişkisi (mevcut `brands` tablosunu kullanır)
 |     relation_type: distributor | seller | buyer | interested | service
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_brands` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`   INT UNSIGNED NOT NULL,
    `brand_id`      INT UNSIGNED DEFAULT NULL,   -- brands.id (opsiyonel)
    `brand_name`    VARCHAR(150) NOT NULL,       -- serbest ad (CSV içe aktarım güvenli)
    `relation_type` VARCHAR(30)  NOT NULL DEFAULT 'interested',
    `notes`         VARCHAR(255) DEFAULT NULL,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_icb_customer` (`customer_id`),
    KEY `idx_icb_brand` (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  8) Şirket tipi ilişkisi (çok-çok)
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_company_types` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`     INT UNSIGNED NOT NULL,
    `company_type_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_icct` (`customer_id`, `company_type_id`),
    KEY `idx_icct_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  9) Kategori ilişkisi (çok-çok)
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_category_relations` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_iccr` (`customer_id`, `category_id`),
    KEY `idx_iccr_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  10) Müşteri notları (zaman çizgisi)
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_notes` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `body`        TEXT         NOT NULL,
    `is_deleted`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_icn_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  11) Müşteriye gönderilen/planlanan mesajlar (kişi bazlı geçmiş)
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_messages` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`     INT UNSIGNED NOT NULL,
    `channel`         VARCHAR(20)  NOT NULL DEFAULT 'mail',  -- mail | whatsapp
    `template_id`     INT UNSIGNED DEFAULT NULL,
    `product_list_id` INT UNSIGNED DEFAULT NULL,
    `subject`         VARCHAR(255) DEFAULT NULL,
    `body`            TEXT         DEFAULT NULL,
    `to_email`        VARCHAR(190) DEFAULT NULL,
    `to_phone`        VARCHAR(60)  DEFAULT NULL,
    `status`          VARCHAR(20)  NOT NULL DEFAULT 'sent',  -- sent | link | test | failed
    `created_by`      INT UNSIGNED DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_icm_customer` (`customer_id`),
    KEY `idx_icm_channel` (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  12) Kayıtlı filtreler
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_filters` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `params`      TEXT         NOT NULL,          -- JSON
    `is_shared`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_icf_user` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  13) İçe aktarım geçmişi + hataları
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `international_customer_imports` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename`      VARCHAR(255) DEFAULT NULL,
    `total_rows`    INT          NOT NULL DEFAULT 0,
    `inserted`      INT          NOT NULL DEFAULT 0,
    `updated`       INT          NOT NULL DEFAULT 0,
    `skipped`       INT          NOT NULL DEFAULT 0,
    `errors`        INT          NOT NULL DEFAULT 0,
    `mode`          VARCHAR(30)  NOT NULL DEFAULT 'skip', -- skip | update
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ici_user` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `international_customer_import_errors` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `import_id`  INT UNSIGNED NOT NULL,
    `row_no`     INT          NOT NULL DEFAULT 0,
    `reason`     VARCHAR(255) DEFAULT NULL,
    `raw`        TEXT         DEFAULT NULL,       -- ham satır (JSON)
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_icie_import` (`import_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  14) Ürün listeleri (Satış Listesi / Talep Listesi)
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `product_lists` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `list_no`     VARCHAR(30)  NOT NULL,                 -- PL-2026-000001
    `title`       VARCHAR(190) NOT NULL,
    `list_type`   VARCHAR(20)  NOT NULL DEFAULT 'sale',  -- sale (satış) | request (talep)
    `currency`    VARCHAR(10)  DEFAULT NULL,
    `valid_until` DATE         DEFAULT NULL,
    `intro`       TEXT         DEFAULT NULL,
    `notes`       TEXT         DEFAULT NULL,
    `is_deleted`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `updated_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_pl_no` (`list_no`),
    KEY `idx_pl_type` (`list_type`),
    KEY `idx_pl_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_list_items` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `list_id`     INT UNSIGNED NOT NULL,
    `sku`         VARCHAR(80)  DEFAULT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `brand`       VARCHAR(120) DEFAULT NULL,
    `qty`         DECIMAL(15,2) DEFAULT NULL,
    `unit`        VARCHAR(30)  DEFAULT NULL,
    `price`       DECIMAL(15,2) DEFAULT NULL,
    `currency`    VARCHAR(10)  DEFAULT NULL,
    `note`        VARCHAR(255) DEFAULT NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_pli_list` (`list_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_list_recipients` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `list_id`     INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `channel`     VARCHAR(20)  NOT NULL DEFAULT 'mail',
    `status`      VARCHAR(20)  NOT NULL DEFAULT 'sent',   -- sent | link | test
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_plr_list` (`list_id`),
    KEY `idx_plr_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  15) Mesaj şablonları + gönderim logları
 * -------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `message_templates` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `template_type` VARCHAR(30) NOT NULL DEFAULT 'sale_mail', -- sale_mail|request_mail|sale_wa|request_wa|followup|reminder
    `channel`     VARCHAR(20)  NOT NULL DEFAULT 'mail',       -- mail | whatsapp
    `subject`     VARCHAR(255) DEFAULT NULL,
    `body`        TEXT         NOT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `is_deleted`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`  INT UNSIGNED DEFAULT NULL,
    `updated_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mt_type` (`template_type`),
    KEY `idx_mt_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `message_templates` (`id`, `name`, `template_type`, `channel`, `subject`, `body`) VALUES
    (1, 'Satış Listesi — Mail', 'sale_mail', 'mail', 'Product Offer — {product_list_title}',
     'Dear {contact_name},\n\nWe are {sender_name} from {company_name}. Please find our current offer below:\n\n{product_list_text}\n\nValid until: {valid_until}\nMore: {pdf_link}\n\nBest regards,\n{sender_name}\n{sender_email}\n{sender_whatsapp}'),
    (2, 'Talep Listesi — Mail', 'request_mail', 'mail', 'Purchase Request — {product_list_title}',
     'Dear {contact_name},\n\nWe ({company_name}) are looking to purchase the following items:\n\n{product_list_text}\n\nPlease send us your best quotation.\n\nBest regards,\n{sender_name}\n{sender_email}'),
    (3, 'Satış Listesi — WhatsApp', 'sale_wa', 'whatsapp', NULL,
     'Hello {contact_name}, this is {sender_name} from {company_name}. Our offer: {product_list_title}. Details: {pdf_link}'),
    (4, 'Talep — WhatsApp', 'request_wa', 'whatsapp', NULL,
     'Hello {contact_name}, we are looking to buy: {product_list_title}. Can you quote? — {sender_name}, {company_name}');

CREATE TABLE IF NOT EXISTS `message_send_logs` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`     INT UNSIGNED DEFAULT NULL,
    `channel`         VARCHAR(20)  NOT NULL DEFAULT 'mail',    -- mail | whatsapp
    `template_id`     INT UNSIGNED DEFAULT NULL,
    `product_list_id` INT UNSIGNED DEFAULT NULL,
    `to_email`        VARCHAR(190) DEFAULT NULL,
    `to_phone`        VARCHAR(60)  DEFAULT NULL,
    `subject`         VARCHAR(255) DEFAULT NULL,
    `status`          VARCHAR(20)  NOT NULL DEFAULT 'sent',    -- sent | link | test | failed | blocked
    `note`            VARCHAR(255) DEFAULT NULL,
    `created_by`      INT UNSIGNED DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_msl_customer` (`customer_id`),
    KEY `idx_msl_channel` (`channel`),
    KEY `idx_msl_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -----------------------------------------------------------------------
 |  16) Ayarlar (günlük mail limiti vb.)
 * -------------------------------------------------------------------- */
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('intl_daily_mail_limit', '200'),
    ('intl_sender_name',      ''),
    ('intl_sender_whatsapp',  '');
