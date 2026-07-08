-- =========================================================================
--  PoyrazTech Yönetim Paneli — Veritabanı Kurulumu (TEK DOSYA)
--  Karakter seti: utf8mb4 / utf8mb4_unicode_ci   ·   Motor: InnoDB
--
--  KURULUM:
--   1) Plesk > Veritabanları'ndan bir veritabanı + kullanıcı oluşturun.
--   2) Bu dosyayı (phpMyAdmin / Plesk import) O veritabanına aktarın.
--   3) config.php içindeki DB_* değerleri bu veritabanına işaret etmelidir.
--
--  VARSAYILAN GİRİŞ:
--   Kullanıcı adı : admin
--   Şifre         : Admin1234!
--   (Şifre aşağıda bcrypt hash olarak saklanır — düz metin değildir.)
-- =========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Bağımlılık sırasına göre temizle
DROP TABLE IF EXISTS `currency_cache`;
DROP TABLE IF EXISTS `app_settings`;
DROP TABLE IF EXISTS `login_logs`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- ------------------------------------------------------------------
--  1) roles  — roller ve modül yetkileri
--     permissions: JSON dizi metni. ["all"] => tüm modüller (Yönetici).
-- ------------------------------------------------------------------
CREATE TABLE `roles` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(64)  NOT NULL,
    `slug`        VARCHAR(80)  NOT NULL,
    `description` VARCHAR(255) NULL,
    `permissions` TEXT         NULL,
    `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_name` (`name`),
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
--  2) users  — kullanıcılar
-- ------------------------------------------------------------------
CREATE TABLE `users` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id`        INT UNSIGNED NULL,
    `username`       VARCHAR(64)  NOT NULL,
    `email`          VARCHAR(190) NOT NULL,
    `password_hash`  VARCHAR(255) NOT NULL,
    `full_name`      VARCHAR(120) NOT NULL DEFAULT '',
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `remember_token` VARCHAR(255) NULL,
    `last_login_at`  DATETIME     NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role_id`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
--  3) password_resets  — şifre sıfırlama token'ları (hash'li)
-- ------------------------------------------------------------------
CREATE TABLE `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used`       TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pr_token` (`token_hash`),
    KEY `idx_pr_user` (`user_id`),
    CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
--  4) login_logs  — giriş denemesi kayıtları
-- ------------------------------------------------------------------
CREATE TABLE `login_logs` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED    NULL,
    `username_input` VARCHAR(190)    NOT NULL DEFAULT '',
    `ip_address`     VARCHAR(45)     NOT NULL DEFAULT '',
    `user_agent`     VARCHAR(255)    NOT NULL DEFAULT '',
    `success`        TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ll_user` (`user_id`),
    KEY `idx_ll_created` (`created_at`),
    CONSTRAINT `fk_ll_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
--  5) app_settings  — anahtar/değer ayarları
-- ------------------------------------------------------------------
CREATE TABLE `app_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         NULL,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
--  6) currency_cache  — Frankfurter kur önbelleği (fallback için)
-- ------------------------------------------------------------------
CREATE TABLE `currency_cache` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cache_key`      VARCHAR(64)  NOT NULL,
    `base_currency`  VARCHAR(3)   NOT NULL DEFAULT 'EUR',
    `rates_json`     LONGTEXT     NULL,
    `fetched_at`     DATETIME     NULL,
    `expires_at`     DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_cc_key` (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
--  VARSAYILAN VERİLER
-- ==================================================================

-- Roller (sabit id: admin kullanıcı role_id = 1'e bağlanır)
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `permissions`, `is_system`) VALUES
    (1, 'Yönetici', 'yonetici', 'Tam yetkili sistem yöneticisi',   '["all"]', 1),
    (2, 'Muhasebe', 'muhasebe', 'Muhasebe ve kur erişimi',         '["dashboard","currency"]', 0),
    (3, 'Satış',    'satis',    'Satış ekibi',                      '["dashboard","currency"]', 0),
    (4, 'Depo',     'depo',     'Depo personeli',                   '["dashboard"]', 0),
    (5, 'Personel', 'personel', 'Genel personel',                   '["dashboard"]', 0);

-- Varsayılan yönetici kullanıcı
-- Şifre: Admin1234!  (bcrypt — PHP password_verify ile doğrulanır)
INSERT INTO `users`
    (`role_id`, `username`, `email`, `password_hash`, `full_name`, `is_active`)
VALUES
    (1, 'admin', 'admin@example.com',
     '$2y$12$xwKI3TBTd5sFMecAs4ilVOihT5jRdAv2X.tqfYO2asXXKPyEV4QXW',
     'Sistem Yöneticisi', 1);

-- Varsayılan uygulama ayarları
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('site_name', 'PoyrazTech Yönetim Paneli'),
    ('base_currency', 'TRY'),
    ('items_per_page', '20'),
    ('default_timezone', 'Europe/Istanbul');

-- ------------------------------------------------------------------
--  Kargo yöntemleri
-- ------------------------------------------------------------------
DROP TABLE IF EXISTS `shipping_method_prices`;
DROP TABLE IF EXISTS `shipping_methods`;

CREATE TABLE `shipping_methods` (
    `id`                  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`                VARCHAR(120)   NOT NULL,
    `code`                VARCHAR(80)    NOT NULL,
    `logo_path`           VARCHAR(255)   NULL,
    `pricing_type`        VARCHAR(30)    NOT NULL DEFAULT 'desi',
    `free_shipping_limit` DECIMAL(10,2)  NULL,
    `sort_order`          INT            NOT NULL DEFAULT 0,
    `is_active`           TINYINT(1)     NOT NULL DEFAULT 1,
    `description`         TEXT           NULL,
    `created_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_shipping_code` (`code`),
    KEY `idx_shipping_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shipping_method_prices` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `shipping_method_id` INT UNSIGNED  NOT NULL,
    `min_desi`           DECIMAL(10,2) NOT NULL,
    `max_desi`           DECIMAL(10,2) NOT NULL,
    `price`              DECIMAL(10,2) NOT NULL,
    `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_smp_method` (`shipping_method_id`),
    CONSTRAINT `fk_smp_method` FOREIGN KEY (`shipping_method_id`)
        REFERENCES `shipping_methods` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan kargo yöntemleri (hepsi aktif)
INSERT INTO `shipping_methods` (`id`, `name`, `code`, `pricing_type`, `free_shipping_limit`, `sort_order`, `is_active`) VALUES
    (1, 'Mağaza Teslim',  'magaza-teslim', 'desi', 0.00,    1, 1),
    (2, 'HepsiJET',       'hepsijet',      'desi', 750.00,  2, 1),
    (3, 'DHL eCommerce',  'dhl-ecommerce', 'desi', 1000.00, 3, 1),
    (4, 'Aras Kargo',     'aras-kargo',    'desi', 750.00,  4, 1),
    (5, 'Elden Teslimat', 'elden-teslimat','desi', 0.00,    5, 1);

-- Örnek desi kademeleri (HepsiJET ve Aras Kargo)
INSERT INTO `shipping_method_prices` (`shipping_method_id`, `min_desi`, `max_desi`, `price`) VALUES
    (2, 0.00, 1.00,  85.00),
    (2, 1.01, 3.00,  110.00),
    (2, 3.01, 5.00,  135.00),
    (2, 5.01, 10.00, 190.00),
    (4, 0.00, 1.00,  90.00),
    (4, 1.01, 3.00,  120.00),
    (4, 3.01, 5.00,  145.00);

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------
--  Markalar
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `brands` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150)  NOT NULL,
    `code`        VARCHAR(80)   DEFAULT NULL,
    `slug`        VARCHAR(180)  NOT NULL,
    `logo_path`   VARCHAR(255)  DEFAULT NULL,
    `description` TEXT          DEFAULT NULL,
    `sort_order`  INT           NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_brands_slug` (`slug`),
    KEY `idx_brands_active` (`is_active`),
    KEY `idx_brands_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan markalar (tekrar çalıştırılabilir: slug UNIQUE + INSERT IGNORE)
INSERT IGNORE INTO `brands` (`name`, `code`, `slug`, `sort_order`, `is_active`) VALUES
    ('HP',             'hp',             'hp',             1,  1),
    ('Canon',          'canon',          'canon',          2,  1),
    ('Brother',        'brother',        'brother',        3,  1),
    ('Epson',          'epson',          'epson',          4,  1),
    ('Samsung',        'samsung',        'samsung',        5,  1),
    ('Kyocera',        'kyocera',        'kyocera',        6,  1),
    ('Xerox',          'xerox',          'xerox',          7,  1),
    ('Lexmark',        'lexmark',        'lexmark',        8,  1),
    ('Ricoh',          'ricoh',          'ricoh',          9,  1),
    ('OKI',            'oki',            'oki',            10, 1),
    ('Pantum',         'pantum',         'pantum',         11, 1),
    ('Toshiba',        'toshiba',        'toshiba',        12, 1),
    ('Sharp',          'sharp',          'sharp',          13, 1),
    ('Develop',        'develop',        'develop',        14, 1),
    ('Konica Minolta', 'konica-minolta', 'konica-minolta', 15, 1);

-- ------------------------------------------------------------------
--  Personeller (panel kullanıcısından ayrı; user_id opsiyonel bağ)
--  Not: user_id FK ile bağlanmadı — eski/farklı users yapılarında
--  kurulum patlamasın diye yalnızca index bırakıldı.
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `personnel` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED  DEFAULT NULL,
    `first_name`        VARCHAR(100)  NOT NULL,
    `last_name`         VARCHAR(100)  DEFAULT NULL,
    `full_name`         VARCHAR(180)  NOT NULL,
    `personnel_code`    VARCHAR(80)   DEFAULT NULL,
    `photo_path`        VARCHAR(255)  DEFAULT NULL,
    `department`        VARCHAR(120)  DEFAULT NULL,
    `position`          VARCHAR(120)  DEFAULT NULL,
    `phone`             VARCHAR(40)   DEFAULT NULL,
    `internal_phone`    VARCHAR(40)   DEFAULT NULL,
    `email`             VARCHAR(180)  DEFAULT NULL,
    `whatsapp`          VARCHAR(40)   DEFAULT NULL,
    `birth_date`        DATE          DEFAULT NULL,
    `show_birthday_notifications` TINYINT(1) NOT NULL DEFAULT 1,
    `hire_date`         DATE          DEFAULT NULL,
    `termination_date`  DATE          DEFAULT NULL,
    `employment_status` ENUM('active','passive','on_leave','left') NOT NULL DEFAULT 'active',
    `description`       TEXT          DEFAULT NULL,
    `sort_order`        INT           NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_personnel_code` (`personnel_code`),
    KEY `idx_personnel_user_id` (`user_id`),
    KEY `idx_personnel_email` (`email`),
    KEY `idx_personnel_department` (`department`),
    KEY `idx_personnel_status` (`employment_status`),
    KEY `idx_personnel_active` (`is_active`),
    KEY `idx_personnel_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
--  Servis Kabul / Dış Tamir Takip
--  (users/brands ile FK kurulmadı; farklı kurulumlarda patlamasın diye index)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `external_repairers` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(150) NOT NULL,
    `company_name` VARCHAR(150) DEFAULT NULL,
    `phone`        VARCHAR(40)  DEFAULT NULL,
    `email`        VARCHAR(180) DEFAULT NULL,
    `address`      TEXT         DEFAULT NULL,
    `specialty`    VARCHAR(120) DEFAULT NULL,
    `description`  TEXT         DEFAULT NULL,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_repairer_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_records` (
    `id`                         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `reference_code`             VARCHAR(40)   NOT NULL,
    `customer_name`              VARCHAR(180)  NOT NULL,
    `customer_phone`             VARCHAR(40)   DEFAULT NULL,
    `customer_email`             VARCHAR(180)  DEFAULT NULL,
    `customer_address`           TEXT          DEFAULT NULL,
    `customer_tax_no`            VARCHAR(40)   DEFAULT NULL,
    `device_type`                VARCHAR(40)   DEFAULT NULL,
    `brand_id`                   INT UNSIGNED  DEFAULT NULL,
    `brand_name`                 VARCHAR(120)  DEFAULT NULL,
    `device_model`               VARCHAR(150)  DEFAULT NULL,
    `quantity`                   INT           NOT NULL DEFAULT 1,
    `serial_no`                  VARCHAR(120)  DEFAULT NULL,
    `accessories`                TEXT          DEFAULT NULL,
    `problem_description`        TEXT          DEFAULT NULL,
    `physical_condition`         TEXT          DEFAULT NULL,
    `received_photo_path`        VARCHAR(255)  DEFAULT NULL,
    `received_at`                DATE          DEFAULT NULL,
    `received_by_user_id`        INT UNSIGNED  DEFAULT NULL,
    `received_by_personnel_id`   INT UNSIGNED  DEFAULT NULL,
    `external_repairer_id`       INT UNSIGNED  DEFAULT NULL,
    `sent_to_repairer_at`        DATE          DEFAULT NULL,
    `returned_from_repairer_at`  DATE          DEFAULT NULL,
    `repairer_cost`              DECIMAL(10,2) DEFAULT NULL,
    `customer_price`             DECIMAL(10,2) DEFAULT NULL,
    `profit_amount`              DECIMAL(10,2) DEFAULT NULL,
    `approval_required`          TINYINT(1)    NOT NULL DEFAULT 1,
    `approval_status`            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approval_method`            VARCHAR(30)   DEFAULT NULL,
    `approval_at`                DATETIME      DEFAULT NULL,
    `approval_by_user_id`        INT UNSIGNED  DEFAULT NULL,
    `approval_note`              TEXT          DEFAULT NULL,
    `payment_status`             ENUM('pending','partial','paid','free') NOT NULL DEFAULT 'pending',
    `payment_method`             VARCHAR(30)   DEFAULT NULL,
    `paid_amount`                DECIMAL(10,2) DEFAULT NULL,
    `paid_at`                    DATETIME      DEFAULT NULL,
    `payment_received_by_user_id` INT UNSIGNED DEFAULT NULL,
    `status`                     VARCHAR(60)   NOT NULL DEFAULT 'new',
    `final_note`                 TEXT          DEFAULT NULL,
    `delivered_to_customer_at`   DATETIME      DEFAULT NULL,
    `closed_at`                  DATETIME      DEFAULT NULL,
    `is_digital_approved`        TINYINT(1)    NOT NULL DEFAULT 0,
    `digital_approved_at`        DATETIME      DEFAULT NULL,
    `created_at`                 DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                 DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_service_ref` (`reference_code`),
    KEY `idx_service_status` (`status`),
    KEY `idx_service_approval` (`approval_status`),
    KEY `idx_service_payment` (`payment_status`),
    KEY `idx_service_repairer` (`external_repairer_id`),
    KEY `idx_service_phone` (`customer_phone`),
    KEY `idx_service_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_status_history` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_record_id` INT UNSIGNED NOT NULL,
    `old_status`        VARCHAR(60)  DEFAULT NULL,
    `new_status`        VARCHAR(60)  NOT NULL,
    `note`              TEXT         DEFAULT NULL,
    `changed_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ssh_record` (`service_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_terms` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(150) NOT NULL,
    `content`    TEXT         NOT NULL,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan servis koşulu (yalnızca tablo boşsa eklenir)
INSERT INTO `service_terms` (`title`, `content`, `is_active`)
SELECT 'Servis Koşulları',
       'Teknik servise teslim edilen ürünler 15 gün boyunca firmamızın sorumluluğundadır.\nBu süreden sonra ürünler üzerinde herhangi bir sorumluluğumuz bulunmamaktadır.\nTeslim alınmayan ve tamiri mümkün olmayan cihazlar, 15 gün sonunda geri dönüşüme gönderilir.\nMüşterinin belirtilen sürede ürünü teslim alması zorunludur.',
       1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `service_terms`);

-- Servis için firma bilgileri (ayarlardan düzenlenebilir)
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('company_name', 'Poyraz Toner'),
    ('company_address', ''),
    ('company_phone', ''),
    ('company_email', ''),
    ('company_website', ''),
    ('company_tax', '');

-- ------------------------------------------------------------------
--  İade-Değişim Yönetimi (RMA / satış sonrası süreç)
--  (users/kargo ile FK kurulmadı; index bırakıldı)
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rma_records` (
    `id`                          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `process_date`                DATE          DEFAULT NULL,
    `customer_name`               VARCHAR(180)  NOT NULL,
    `customer_phone`              VARCHAR(40)   DEFAULT NULL,
    `invoice_date`                DATE          DEFAULT NULL,
    `platform`                    VARCHAR(80)   DEFAULT NULL,
    `product_model`               VARCHAR(180)  DEFAULT NULL,
    `quantity`                    INT           NOT NULL DEFAULT 1,
    `process_type`                ENUM('iade','degisim','iptal') NOT NULL DEFAULT 'iade',
    `description`                 TEXT          DEFAULT NULL,
    `supplier`                    VARCHAR(180)  DEFAULT NULL,
    `loss_amount`                 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `received_product`            VARCHAR(255)  DEFAULT NULL,
    `received_product_status`     VARCHAR(120)  DEFAULT NULL,
    `received_cargo_company`      VARCHAR(120)  DEFAULT NULL,
    `received_cargo_tracking_no`  VARCHAR(120)  DEFAULT NULL,
    `received_cargo_tracking_url` VARCHAR(255)  DEFAULT NULL,
    `received_at`                 DATE          DEFAULT NULL,
    `received_note`               TEXT          DEFAULT NULL,
    `sent_product`                VARCHAR(255)  DEFAULT NULL,
    `sent_cargo_company`          VARCHAR(120)  DEFAULT NULL,
    `sent_cargo_tracking_no`      VARCHAR(120)  DEFAULT NULL,
    `sent_cargo_tracking_url`     VARCHAR(255)  DEFAULT NULL,
    `sent_at`                     DATE          DEFAULT NULL,
    `delivered_at`                DATE          DEFAULT NULL,
    `sent_note`                   TEXT          DEFAULT NULL,
    `status`                      VARCHAR(60)   NOT NULL DEFAULT 'requested',
    `created_by_user_id`          INT UNSIGNED  DEFAULT NULL,
    `updated_by_user_id`          INT UNSIGNED  DEFAULT NULL,
    `created_at`                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rma_process_date` (`process_date`),
    KEY `idx_rma_phone` (`customer_phone`),
    KEY `idx_rma_platform` (`platform`),
    KEY `idx_rma_type` (`process_type`),
    KEY `idx_rma_status` (`status`),
    KEY `idx_rma_supplier` (`supplier`),
    KEY `idx_rma_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rma_status_history` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rma_record_id`      INT UNSIGNED NOT NULL,
    `old_status`         VARCHAR(60)  DEFAULT NULL,
    `new_status`         VARCHAR(60)  NOT NULL,
    `note`               TEXT         DEFAULT NULL,
    `changed_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rma_hist_record` (`rma_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
--  ÇOKLU ÜRÜN + PUBLIC TAKIP GÜNCELLEMESİ
--  (MariaDB 10.2+ : ADD COLUMN/INDEX IF NOT EXISTS ile idempotent)
-- ==================================================================

-- Müşteriden alınan ürünler (bir RMA kaydına birden fazla)
CREATE TABLE IF NOT EXISTS `rma_received_items` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `rma_record_id`      INT UNSIGNED  NOT NULL,
    `product_name`       VARCHAR(180)  DEFAULT NULL,
    `product_model`      VARCHAR(180)  DEFAULT NULL,
    `brand_id`           INT UNSIGNED  DEFAULT NULL,
    `brand_name`         VARCHAR(120)  DEFAULT NULL,
    `quantity`           INT           NOT NULL DEFAULT 1,
    `serial_no`          VARCHAR(120)  DEFAULT NULL,
    `condition_note`     VARCHAR(255)  DEFAULT NULL,
    `received_status`    VARCHAR(120)  DEFAULT NULL,
    `cargo_company`      VARCHAR(120)  DEFAULT NULL,
    `cargo_tracking_no`  VARCHAR(120)  DEFAULT NULL,
    `cargo_tracking_url` VARCHAR(255)  DEFAULT NULL,
    `received_at`        DATE          DEFAULT NULL,
    `note`               TEXT          DEFAULT NULL,
    `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rri_record` (`rma_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Müşteriye gönderilen ürünler (bir RMA kaydına birden fazla)
CREATE TABLE IF NOT EXISTS `rma_sent_items` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `rma_record_id`      INT UNSIGNED  NOT NULL,
    `product_name`       VARCHAR(180)  DEFAULT NULL,
    `product_model`      VARCHAR(180)  DEFAULT NULL,
    `brand_id`           INT UNSIGNED  DEFAULT NULL,
    `brand_name`         VARCHAR(120)  DEFAULT NULL,
    `quantity`           INT           NOT NULL DEFAULT 1,
    `serial_no`          VARCHAR(120)  DEFAULT NULL,
    `cargo_company`      VARCHAR(120)  DEFAULT NULL,
    `cargo_tracking_no`  VARCHAR(120)  DEFAULT NULL,
    `cargo_tracking_url` VARCHAR(255)  DEFAULT NULL,
    `sent_at`            DATE          DEFAULT NULL,
    `delivered_at`       DATE          DEFAULT NULL,
    `sent_status`        VARCHAR(120)  DEFAULT NULL,
    `note`               TEXT          DEFAULT NULL,
    `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rsi_record` (`rma_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rma_records: yeni alanlar (çoklu ürün yapısıyla tekil alanlar artık kullanılmıyor)
ALTER TABLE `rma_records`
    ADD COLUMN IF NOT EXISTS `reference_code`          VARCHAR(40)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `customer_email`          VARCHAR(180) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `reason_type`             VARCHAR(80)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `public_token`            VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `public_tracking_enabled` TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `customer_public_note`    TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `internal_note`           TEXT         DEFAULT NULL;

ALTER TABLE `rma_records` ADD UNIQUE INDEX IF NOT EXISTS `uniq_rma_ref` (`reference_code`);

-- service_records: public takip + iç/müşteri notu + tutar görünürlüğü
ALTER TABLE `service_records`
    ADD COLUMN IF NOT EXISTS `public_token`            VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `public_tracking_enabled` TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS `customer_public_note`    TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `internal_note`           TEXT         DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `show_price_public`       TINYINT(1)   NOT NULL DEFAULT 0;

-- Durum geçmişi: müşteriye açık mı?
ALTER TABLE `rma_status_history`     ADD COLUMN IF NOT EXISTS `is_public` TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `service_status_history` ADD COLUMN IF NOT EXISTS `is_public` TINYINT(1) NOT NULL DEFAULT 1;

-- Mevcut kayıtlar için referans kodu + public token üret (boş olanlar)
UPDATE `rma_records`
   SET `reference_code` = CONCAT('RMA-', DATE_FORMAT(COALESCE(`created_at`, NOW()), '%Y%m%d'), '-', LPAD(`id`, 4, '0'))
 WHERE `reference_code` IS NULL OR `reference_code` = '';
UPDATE `rma_records`
   SET `public_token` = SHA2(CONCAT(`id`, '-', RAND(), '-', UUID()), 256)
 WHERE `public_token` IS NULL OR `public_token` = '';
UPDATE `service_records`
   SET `public_token` = SHA2(CONCAT(`id`, '-', RAND(), '-', UUID()), 256)
 WHERE `public_token` IS NULL OR `public_token` = '';

-- ==================================================================
--  SOFT DELETE (ARŞİV) + İŞLEM LOGU  (MariaDB 10.2+ idempotent)
--  Servis/RMA kayıtları fiziksel silinmez; is_deleted=1 ile arşivlenir.
-- ==================================================================
ALTER TABLE `service_records`
    ADD COLUMN IF NOT EXISTS `is_deleted`         TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `deleted_at`         DATETIME     DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `deleted_by_user_id` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `service_records` ADD INDEX IF NOT EXISTS `idx_service_deleted` (`is_deleted`);

ALTER TABLE `rma_records`
    ADD COLUMN IF NOT EXISTS `is_deleted`         TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `deleted_at`         DATETIME     DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `deleted_by_user_id` INT UNSIGNED DEFAULT NULL;
ALTER TABLE `rma_records` ADD INDEX IF NOT EXISTS `idx_rma_deleted` (`is_deleted`);

-- İşlem (silme/arşiv vb.) denetim logu
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED DEFAULT NULL,
    `action`         VARCHAR(80)  NOT NULL,
    `entity_type`    VARCHAR(40)  DEFAULT NULL,
    `entity_id`      INT UNSIGNED DEFAULT NULL,
    `reference_code` VARCHAR(60)  DEFAULT NULL,
    `ip`             VARCHAR(64)  DEFAULT NULL,
    `result`         VARCHAR(20)  DEFAULT NULL,
    `detail`         TEXT         DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_act_entity` (`entity_type`, `entity_id`),
    KEY `idx_act_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
--  KULLANICI ARAYÜZ TERCİHLERİ (tema, sidebar durumu vb.)
--  Kullanıcı bazlı; tekrar çalıştırılabilir (IF NOT EXISTS).
-- ==================================================================
CREATE TABLE IF NOT EXISTS `user_preferences` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED  NOT NULL,
    `preference_key`   VARCHAR(120)  NOT NULL,
    `preference_value` LONGTEXT      DEFAULT NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_user_preference` (`user_id`, `preference_key`),
    KEY `idx_user_preferences_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
--  İK / PERSONEL — YILLIK İZİN TAKİBİ
--  Foreign key yerine index (eski kurulumları patlatmamak için).
-- ==================================================================
CREATE TABLE IF NOT EXISTS `leave_types` (
    `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                   VARCHAR(120) NOT NULL,
    `code`                   VARCHAR(80)  NOT NULL,
    `is_paid`                TINYINT(1)   NOT NULL DEFAULT 1,
    `deducts_annual_balance` TINYINT(1)   NOT NULL DEFAULT 0,
    `allow_hourly`           TINYINT(1)   NOT NULL DEFAULT 0,
    `allow_half_day`         TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`             INT          NOT NULL DEFAULT 0,
    `is_active`              TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_leave_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_no`            VARCHAR(40)  NOT NULL,
    `personnel_id`          INT UNSIGNED NOT NULL,
    `leave_type_id`         INT UNSIGNED NOT NULL,
    `start_date`            DATE         NOT NULL,
    `end_date`              DATE         NOT NULL,
    `start_time`            TIME         DEFAULT NULL,
    `end_time`              TIME         DEFAULT NULL,
    `calculated_days`       DECIMAL(6,2) NOT NULL DEFAULT 0,
    `calculated_hours`      DECIMAL(6,2) DEFAULT NULL,
    `is_half_day`           TINYINT(1)   NOT NULL DEFAULT 0,
    `description`           TEXT         DEFAULT NULL,
    `status`                ENUM('draft','pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `requested_by_user_id`  INT UNSIGNED DEFAULT NULL,
    `approved_by_user_id`   INT UNSIGNED DEFAULT NULL,
    `approved_at`           DATETIME     DEFAULT NULL,
    `rejected_by_user_id`   INT UNSIGNED DEFAULT NULL,
    `rejected_at`           DATETIME     DEFAULT NULL,
    `reject_reason`         TEXT         DEFAULT NULL,
    `attachment_path`       VARCHAR(255) DEFAULT NULL,
    `is_deleted`            TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_leave_request_no` (`request_no`),
    KEY `idx_leave_personnel` (`personnel_id`),
    KEY `idx_leave_type` (`leave_type_id`),
    KEY `idx_leave_status` (`status`),
    KEY `idx_leave_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_balance_adjustments` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `personnel_id`       INT UNSIGNED NOT NULL,
    `adjustment_type`    ENUM('add','subtract','carry_over','correction') NOT NULL,
    `days`               DECIMAL(6,2) NOT NULL,
    `year`               SMALLINT UNSIGNED DEFAULT NULL,
    `description`        TEXT         DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_leave_adjustment_personnel` (`personnel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_holidays` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `holiday_date` DATE         NOT NULL,
    `title`        VARCHAR(150) NOT NULL,
    `is_recurring` TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_company_holiday_date_title` (`holiday_date`, `title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan izin türleri (mevcut kaydı ezmez)
INSERT IGNORE INTO `leave_types` (`name`, `code`, `is_paid`, `deducts_annual_balance`, `allow_hourly`, `allow_half_day`, `sort_order`) VALUES
    ('Yıllık İzin',      'annual',      1, 1, 0, 1, 1),
    ('Ücretsiz İzin',    'unpaid',      0, 0, 0, 1, 2),
    ('Mazeret İzni',     'excuse',      1, 0, 1, 1, 3),
    ('Hastalık / Rapor', 'sick',        1, 0, 0, 0, 4),
    ('Doğum İzni',       'maternity',   1, 0, 0, 0, 5),
    ('Babalık İzni',     'paternity',   1, 0, 0, 0, 6),
    ('Evlilik İzni',     'marriage',    1, 0, 0, 0, 7),
    ('Vefat İzni',       'bereavement', 1, 0, 0, 0, 8),
    ('Yarım Gün İzin',   'half_day',    1, 1, 0, 1, 9),
    ('Saatlik İzin',     'hourly',      1, 0, 1, 0, 10),
    ('Diğer',            'other',       1, 0, 1, 1, 11);

-- Varsayılan izin/çalışma takvimi ayarları (app_settings; mevcut değeri ezmez)
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('leave_annual_default_days', '14'),
    ('leave_exclude_weekends',    '1'),
    ('leave_exclude_holidays',    '1'),
    ('leave_weekend_days',        '6,0');

-- ==================================================================
--  İK / PERSONEL — PUANTAJ (Attendance)
--  FK yerine index; ilişkiler kod tarafında kontrol edilir.
-- ==================================================================
CREATE TABLE IF NOT EXISTS `attendance_statuses` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(120) NOT NULL,
    `code`                 VARCHAR(20)  NOT NULL,
    `color`                VARCHAR(20)  DEFAULT NULL,
    `is_paid`              TINYINT(1)   NOT NULL DEFAULT 1,
    `counts_as_workday`    TINYINT(1)   NOT NULL DEFAULT 0,
    `counts_as_absence`    TINYINT(1)   NOT NULL DEFAULT 0,
    `deducts_annual_leave` TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`           INT          NOT NULL DEFAULT 0,
    `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_attendance_status_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_records` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `personnel_id`       INT UNSIGNED NOT NULL,
    `attendance_date`    DATE         NOT NULL,
    `status_id`          INT UNSIGNED NOT NULL,
    `check_in`           TIME         DEFAULT NULL,
    `check_out`          TIME         DEFAULT NULL,
    `break_minutes`      INT          DEFAULT 0,
    `work_minutes`       INT          DEFAULT 0,
    `overtime_minutes`   INT          DEFAULT 0,
    `missing_minutes`    INT          DEFAULT 0,
    `leave_request_id`   INT UNSIGNED DEFAULT NULL,
    `note`               TEXT         DEFAULT NULL,
    `created_by_user_id` INT UNSIGNED DEFAULT NULL,
    `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_attendance_personnel_date` (`personnel_id`, `attendance_date`),
    KEY `idx_attendance_date` (`attendance_date`),
    KEY `idx_attendance_personnel` (`personnel_id`),
    KEY `idx_attendance_status` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `work_schedules` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(120) NOT NULL,
    `start_time`       TIME         DEFAULT NULL,
    `end_time`         TIME         DEFAULT NULL,
    `break_minutes`    INT          DEFAULT 0,
    `weekly_work_days` VARCHAR(50)  DEFAULT '1,2,3,4,5',
    `is_default`       TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan puantaj durumları (mevcut kaydı ezmez)
INSERT IGNORE INTO `attendance_statuses`
    (`name`, `code`, `color`, `is_paid`, `counts_as_workday`, `counts_as_absence`, `deducts_annual_leave`, `sort_order`) VALUES
    ('Çalıştı',          'worked',       '#e8f5ec', 1, 1, 0, 0, 1),
    ('İzinli',           'leave',        '#eef4ff', 1, 0, 0, 0, 2),
    ('Yıllık İzin',      'annual_leave', '#eaf1ff', 1, 0, 0, 1, 3),
    ('Ücretsiz İzin',    'unpaid_leave', '#f3f4f6', 0, 0, 0, 0, 4),
    ('Raporlu',          'sick',         '#fff4e6', 1, 0, 0, 0, 5),
    ('Gelmedi',          'absent',       '#fdeaea', 0, 0, 1, 0, 6),
    ('Yarım Gün',        'half_day',     '#f5f3ff', 1, 1, 0, 0, 7),
    ('Hafta Tatili',     'week_off',     '#f3f4f6', 1, 0, 0, 0, 8),
    ('Resmi Tatil',      'holiday',      '#fef9e7', 1, 0, 0, 0, 9),
    ('Fazla Mesai',      'overtime',     '#e7f0ff', 1, 1, 0, 0, 10),
    ('Geç Geldi',        'late',         '#fff7ed', 1, 1, 0, 0, 11),
    ('Erken Çıktı',      'early_out',    '#fff7ed', 1, 1, 0, 0, 12),
    ('Uzaktan Çalışma',  'remote',       '#ecfeff', 1, 1, 0, 0, 13),
    ('Diğer',            'other',        '#f3f4f6', 1, 0, 0, 0, 14);

-- Varsayılan çalışma programı
INSERT IGNORE INTO `work_schedules` (`id`, `name`, `start_time`, `end_time`, `break_minutes`, `weekly_work_days`, `is_default`, `is_active`) VALUES
    (1, 'Standart Mesai', '09:00:00', '18:00:00', 60, '1,2,3,4,5', 1, 1);

-- ==================================================================
--  İK BİLDİRİMLERİ / PERSONEL HATIRLATMALARI
--  Mevcut personnel tablosuna eksik kolonları güvenle ekle (MariaDB).
--  Yeni kurulumda kolonlar zaten CREATE TABLE içinde tanımlı; bu ALTER
--  eski kurulumlar için idempotenttir (IF NOT EXISTS).
-- ==================================================================
ALTER TABLE `personnel` ADD COLUMN IF NOT EXISTS `birth_date` DATE DEFAULT NULL;
ALTER TABLE `personnel` ADD COLUMN IF NOT EXISTS `show_birthday_notifications` TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS `notification_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`   VARCHAR(120) NOT NULL,
    `setting_value` TEXT         DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_notification_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_notifications` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED NOT NULL,
    `notification_type` VARCHAR(80)  NOT NULL,
    `title`             VARCHAR(180) NOT NULL,
    `message`           TEXT         NOT NULL,
    `related_type`      VARCHAR(80)  DEFAULT NULL,
    `related_id`        INT UNSIGNED DEFAULT NULL,
    `notification_date` DATE         DEFAULT NULL,
    `is_read`           TINYINT(1)   NOT NULL DEFAULT 0,
    `read_at`           DATETIME     DEFAULT NULL,
    `is_deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_notifications_user` (`user_id`),
    KEY `idx_user_notifications_type` (`notification_type`),
    KEY `idx_user_notifications_read` (`is_read`),
    KEY `idx_user_notifications_date` (`notification_date`),
    UNIQUE KEY `uniq_user_notif_dedupe` (`user_id`, `notification_type`, `related_type`, `related_id`, `notification_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_templates` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_key` VARCHAR(120) NOT NULL,
    `title`        VARCHAR(180) NOT NULL,
    `channel`      ENUM('panel','email','whatsapp') NOT NULL DEFAULT 'panel',
    `subject`      VARCHAR(180) DEFAULT NULL,
    `body`         TEXT         NOT NULL,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_notification_template` (`template_key`, `channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `notification_type` VARCHAR(80)  NOT NULL,
    `channel`           ENUM('panel','email','whatsapp') NOT NULL,
    `personnel_id`      INT UNSIGNED DEFAULT NULL,
    `user_id`           INT UNSIGNED DEFAULT NULL,
    `recipient`         VARCHAR(180) DEFAULT NULL,
    `subject`           VARCHAR(180) DEFAULT NULL,
    `message`           TEXT         DEFAULT NULL,
    `status`            ENUM('pending','sent','failed','manual_opened','skipped') NOT NULL DEFAULT 'pending',
    `error_message`     TEXT         DEFAULT NULL,
    `sent_at`           DATETIME     DEFAULT NULL,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notification_logs_type` (`notification_type`),
    KEY `idx_notification_logs_channel` (`channel`),
    KEY `idx_notification_logs_personnel` (`personnel_id`),
    KEY `idx_notification_logs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan bildirim ayarları (mevcut değeri ezmez)
INSERT IGNORE INTO `notification_settings` (`setting_key`, `setting_value`) VALUES
    ('birthday_enabled',        '1'),
    ('birthday_days_before',    '7'),
    ('anniversary_enabled',     '1'),
    ('anniversary_days_before', '7'),
    ('leave_enabled',           '1'),
    ('leave_days_before',       '3'),
    ('mail_auto',               '0'),
    ('whatsapp_auto',           '0'),
    ('show_age',                '0');

-- Varsayılan kutlama şablonları (mevcut kaydı ezmez)
INSERT IGNORE INTO `notification_templates` (`template_key`, `title`, `channel`, `subject`, `body`) VALUES
    ('birthday', 'Doğum Günü — WhatsApp', 'whatsapp', NULL,
     'Merhaba {personnel_name}, doğum gününüzü kutlar; sağlıklı, mutlu ve başarılı bir yaş dileriz.\n\n{company_name}'),
    ('birthday', 'Doğum Günü — E-posta', 'email', 'Doğum Gününüz Kutlu Olsun',
     'Merhaba {personnel_name},\n\nDoğum gününüzü kutlar; sağlıklı, mutlu ve başarılı bir yaş dileriz.\n\n{company_name}'),
    ('anniversary', 'Çalışma Yıl Dönümü — WhatsApp', 'whatsapp', NULL,
     'Merhaba {personnel_name}, şirketimizdeki {year}. yılınızı kutlarız. Emekleriniz ve katkılarınız için teşekkür ederiz.\n\n{company_name}'),
    ('anniversary', 'Çalışma Yıl Dönümü — E-posta', 'email', 'Çalışma Yıl Dönümünüz Kutlu Olsun',
     'Merhaba {personnel_name},\n\nŞirketimizdeki {year}. yılınızı kutlarız. Emekleriniz ve katkılarınız için teşekkür ederiz.\n\n{company_name}');

/* =========================================================================
   FAZ A — Detaylı rol/yetki sistemi + genişletilmiş şirket ayarları
   (İdempotent: mevcut kayıtları ezmez; INSERT IGNORE ile eklenir.)
   Yetkiler işlem bazlı anahtarlarla saklanır: "modul.islem"
   ====================================================================== */

-- Varsayılan roller (mevcut roller 1-5 korunur; yenileri eklenir)
INSERT IGNORE INTO `roles` (`name`, `slug`, `description`, `permissions`, `is_system`) VALUES
    ('Süper Admin', 'super-admin', 'Tüm modül ve işlemlerde tam yetki', '["all"]', 1),
    ('Teknik Servis', 'teknik-servis', 'Servis ve iade/değişim operasyonu',
     '["dashboard.view","service.view","service.create","service.edit","service.status","service.mail","service.whatsapp","service.print","service.pdf","rma.view","tsoft_products.view"]', 0),
    ('Satın Alma', 'satin-alma', 'Tedarikçi ve satın alma',
     '["dashboard.view","suppliers.view","suppliers.create","suppliers.edit","tsoft_products.view","orders.view","reports.view"]', 0),
    ('İhracat', 'ihracat', 'İhracat / yurtdışı satış',
     '["dashboard.view","customers.view","quotes.view","quotes.create","quotes.edit","quotes.pdf","quotes.mail","quotes.whatsapp","orders.view","reports.view"]', 0),
    ('İnsan Kaynakları', 'insan-kaynaklari', 'Personel, izin ve prim',
     '["dashboard.view","personnel.view","personnel.create","personnel.edit","leave.view","leave.approve","attendance.view","commissions.view"]', 0),
    ('Sadece Görüntüleme', 'sadece-goruntuleme', 'Yalnızca görüntüleme yetkisi',
     '["dashboard.view","customers.view","quotes.view","orders.view","suppliers.view","service.view","rma.view","reports.view","inventory.view"]', 0);

-- Genişletilmiş şirket / genel ayar anahtarları (boş varsayılan; mevcut değeri ezmez)
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('company_name',        ''),
    ('company_legal_name',  ''),
    ('company_tax_office',  ''),
    ('company_tax_no',      ''),
    ('company_tax',         ''),
    ('company_address',     ''),
    ('company_phone',       ''),
    ('company_whatsapp',    ''),
    ('company_email',       ''),
    ('company_website',     ''),
    ('company_logo',        ''),
    ('company_favicon',     ''),
    ('default_currency',    'TRY'),
    ('default_vat',         '20'),
    ('mail_from_name',      ''),
    ('mail_from_email',     '');

/* =========================================================================
   FAZ B — Satış çekirdeği: müşteriler, tedarikçiler, teklifler, siparişler,
   mutabakat. (İdempotent: CREATE TABLE IF NOT EXISTS; canlıda veri kaybı yok.)
   ====================================================================== */

CREATE TABLE IF NOT EXISTS `customers` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`          VARCHAR(40)  DEFAULT NULL,
    `company_name`  VARCHAR(190) NOT NULL,
    `contact_name`  VARCHAR(150) DEFAULT NULL,
    `phone`         VARCHAR(40)  DEFAULT NULL,
    `whatsapp`      VARCHAR(40)  DEFAULT NULL,
    `email`         VARCHAR(190) DEFAULT NULL,
    `tax_office`    VARCHAR(120) DEFAULT NULL,
    `tax_no`        VARCHAR(40)  DEFAULT NULL,
    `country`       VARCHAR(80)  DEFAULT NULL,
    `city`          VARCHAR(80)  DEFAULT NULL,
    `district`      VARCHAR(80)  DEFAULT NULL,
    `address`       TEXT         DEFAULT NULL,
    `website`       VARCHAR(190) DEFAULT NULL,
    `customer_type` VARCHAR(20)  NOT NULL DEFAULT 'tr',
    `source`        VARCHAR(80)  DEFAULT NULL,
    `notes`         TEXT         DEFAULT NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `is_deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `updated_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customers_type` (`customer_type`),
    KEY `idx_customers_company` (`company_name`),
    KEY `idx_customers_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`           VARCHAR(40)  DEFAULT NULL,
    `company_name`   VARCHAR(190) NOT NULL,
    `contact_name`   VARCHAR(150) DEFAULT NULL,
    `phone`          VARCHAR(40)  DEFAULT NULL,
    `whatsapp`       VARCHAR(40)  DEFAULT NULL,
    `email`          VARCHAR(190) DEFAULT NULL,
    `country`        VARCHAR(80)  DEFAULT NULL,
    `city`           VARCHAR(80)  DEFAULT NULL,
    `address`        TEXT         DEFAULT NULL,
    `website`        VARCHAR(190) DEFAULT NULL,
    `product_groups` VARCHAR(255) DEFAULT NULL,
    `payment_terms`  VARCHAR(255) DEFAULT NULL,
    `delivery_time`  VARCHAR(120) DEFAULT NULL,
    `currency`       VARCHAR(10)  NOT NULL DEFAULT 'TRY',
    `notes`          TEXT         DEFAULT NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `is_deleted`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `updated_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_suppliers_company` (`company_name`),
    KEY `idx_suppliers_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotes` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quote_no`      VARCHAR(40)  NOT NULL,
    `customer_id`   INT UNSIGNED DEFAULT NULL,
    `customer_name` VARCHAR(190) NOT NULL,
    `contact_name`  VARCHAR(150) DEFAULT NULL,
    `phone`         VARCHAR(40)  DEFAULT NULL,
    `email`         VARCHAR(190) DEFAULT NULL,
    `quote_date`    DATE         DEFAULT NULL,
    `valid_until`   DATE         DEFAULT NULL,
    `currency`      VARCHAR(10)  NOT NULL DEFAULT 'TRY',
    `vat_mode`      VARCHAR(10)  NOT NULL DEFAULT 'excl',
    `discount_type` VARCHAR(10)  NOT NULL DEFAULT 'none',
    `discount_value` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `subtotal`      DECIMAL(15,2) NOT NULL DEFAULT 0,
    `discount_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `vat_total`     DECIMAL(15,2) NOT NULL DEFAULT 0,
    `grand_total`   DECIMAL(15,2) NOT NULL DEFAULT 0,
    `notes`         TEXT         DEFAULT NULL,
    `terms`         TEXT         DEFAULT NULL,
    `status`        VARCHAR(20)  NOT NULL DEFAULT 'draft',
    `prepared_by`   INT UNSIGNED DEFAULT NULL,
    `is_deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `updated_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quotes_no` (`quote_no`),
    KEY `idx_quotes_customer` (`customer_id`),
    KEY `idx_quotes_status` (`status`),
    KEY `idx_quotes_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_items` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quote_id`    INT UNSIGNED NOT NULL,
    `product_code` VARCHAR(80) DEFAULT NULL,
    `barcode`     VARCHAR(80)  DEFAULT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `brand`       VARCHAR(120) DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `qty`         DECIMAL(12,2) NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(15,2) NOT NULL DEFAULT 0,
    `vat_rate`    DECIMAL(5,2)  NOT NULL DEFAULT 0,
    `discount`    DECIMAL(5,2)  NOT NULL DEFAULT 0,
    `line_total`  DECIMAL(15,2) NOT NULL DEFAULT 0,
    `sort`        INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_quote_items_quote` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no`       VARCHAR(40)  NOT NULL,
    `customer_id`    INT UNSIGNED DEFAULT NULL,
    `customer_name`  VARCHAR(190) NOT NULL,
    `order_date`     DATE         DEFAULT NULL,
    `currency`       VARCHAR(10)  NOT NULL DEFAULT 'TRY',
    `subtotal`       DECIMAL(15,2) NOT NULL DEFAULT 0,
    `vat_total`      DECIMAL(15,2) NOT NULL DEFAULT 0,
    `grand_total`    DECIMAL(15,2) NOT NULL DEFAULT 0,
    `cargo_company`  VARCHAR(120) DEFAULT NULL,
    `tracking_no`    VARCHAR(120) DEFAULT NULL,
    `shipping_address` TEXT       DEFAULT NULL,
    `billing_address`  TEXT       DEFAULT NULL,
    `payment_method` VARCHAR(60)  DEFAULT NULL,
    `payment_status` VARCHAR(20)  NOT NULL DEFAULT 'pending',
    `status`         VARCHAR(20)  NOT NULL DEFAULT 'draft',
    `notes`          TEXT         DEFAULT NULL,
    `is_deleted`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `updated_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_no` (`order_no`),
    KEY `idx_orders_customer` (`customer_id`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`    INT UNSIGNED NOT NULL,
    `product_code` VARCHAR(80) DEFAULT NULL,
    `barcode`     VARCHAR(80)  DEFAULT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `brand`       VARCHAR(120) DEFAULT NULL,
    `qty`         DECIMAL(12,2) NOT NULL DEFAULT 1,
    `unit_price`  DECIMAL(15,2) NOT NULL DEFAULT 0,
    `vat_rate`    DECIMAL(5,2)  NOT NULL DEFAULT 0,
    `line_total`  DECIMAL(15,2) NOT NULL DEFAULT 0,
    `sort`        INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reconciliations` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recon_no`       VARCHAR(40)  NOT NULL,
    `customer_id`    INT UNSIGNED DEFAULT NULL,
    `customer_name`  VARCHAR(190) NOT NULL,
    `cari_code`      VARCHAR(40)  DEFAULT NULL,
    `period`         VARCHAR(40)  DEFAULT NULL,
    `debit`          DECIMAL(15,2) NOT NULL DEFAULT 0,
    `credit`         DECIMAL(15,2) NOT NULL DEFAULT 0,
    `balance`        DECIMAL(15,2) NOT NULL DEFAULT 0,
    `currency`       VARCHAR(10)  NOT NULL DEFAULT 'TRY',
    `agreement`      VARCHAR(20)  NOT NULL DEFAULT 'pending',
    `description`    TEXT         DEFAULT NULL,
    `authorized_name` VARCHAR(150) DEFAULT NULL,
    `recon_date`     DATE         DEFAULT NULL,
    `prepared_by`    INT UNSIGNED DEFAULT NULL,
    `is_deleted`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `updated_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_recon_no` (`recon_no`),
    KEY `idx_recon_customer` (`customer_id`),
    KEY `idx_recon_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =========================================================================
   FAZ C — Primler + Envanter/Demirbaş (idempotent CREATE TABLE IF NOT EXISTS)
   ====================================================================== */

CREATE TABLE IF NOT EXISTS `commissions` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `personnel_id`         INT UNSIGNED NOT NULL,
    `period_start`         DATE         DEFAULT NULL,
    `period_end`           DATE         DEFAULT NULL,
    `sales_amount`         DECIMAL(15,2) NOT NULL DEFAULT 0,
    `profit_amount`        DECIMAL(15,2) NOT NULL DEFAULT 0,
    `commission_rate`      DECIMAL(6,3)  NOT NULL DEFAULT 0,
    `fixed_commission`     DECIMAL(15,2) NOT NULL DEFAULT 0,
    `target_amount`        DECIMAL(15,2) NOT NULL DEFAULT 0,
    `target_ratio`         DECIMAL(6,2)  NOT NULL DEFAULT 0,
    `calculated_commission` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `currency`             VARCHAR(10)  NOT NULL DEFAULT 'TRY',
    `description`          TEXT         DEFAULT NULL,
    `is_deleted`           TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`           INT UNSIGNED DEFAULT NULL,
    `updated_by`           INT UNSIGNED DEFAULT NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_commissions_personnel` (`personnel_id`),
    KEY `idx_commissions_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(190) NOT NULL,
    `category`             VARCHAR(40)  NOT NULL DEFAULT 'other',
    `brand`                VARCHAR(120) DEFAULT NULL,
    `model`                VARCHAR(120) DEFAULT NULL,
    `serial_no`            VARCHAR(120) DEFAULT NULL,
    `purchase_date`        DATE         DEFAULT NULL,
    `invoice_no`           VARCHAR(80)  DEFAULT NULL,
    `warranty_end`         DATE         DEFAULT NULL,
    `assigned_personnel_id` INT UNSIGNED DEFAULT NULL,
    `location`             VARCHAR(120) DEFAULT NULL,
    `status`               VARCHAR(20)  NOT NULL DEFAULT 'active',
    `description`          TEXT         DEFAULT NULL,
    `file_path`            VARCHAR(255) DEFAULT NULL,
    `is_deleted`           TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`           INT UNSIGNED DEFAULT NULL,
    `updated_by`           INT UNSIGNED DEFAULT NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inventory_category` (`category`),
    KEY `idx_inventory_status` (`status`),
    KEY `idx_inventory_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =========================================================================
   FAZ D — Şifre Kasası (şifreler AES-256-GCM ile şifreli saklanır)
   ====================================================================== */

CREATE TABLE IF NOT EXISTS `password_vault` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`              VARCHAR(190) NOT NULL,
    `category`           VARCHAR(40)  NOT NULL DEFAULT 'other',
    `username`           VARCHAR(190) DEFAULT NULL,
    `secret_enc`         TEXT         DEFAULT NULL,   -- AES-256-GCM (base64: iv|tag|ciphertext)
    `url`                VARCHAR(255) DEFAULT NULL,
    `description`        TEXT         DEFAULT NULL,
    `responsible_person` VARCHAR(150) DEFAULT NULL,
    `last_changed_at`    DATETIME     DEFAULT NULL,
    `change_period_days` INT UNSIGNED DEFAULT NULL,
    `allowed_user_ids`   VARCHAR(255) DEFAULT NULL,   -- JSON dizi; boşsa tüm yetkililer görür
    `file_path`          VARCHAR(255) DEFAULT NULL,
    `is_deleted`         TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`         INT UNSIGNED DEFAULT NULL,
    `updated_by`         INT UNSIGNED DEFAULT NULL,
    `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vault_category` (`category`),
    KEY `idx_vault_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_vault_history` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vault_id`   INT UNSIGNED NOT NULL,
    `username`   VARCHAR(190) DEFAULT NULL,
    `secret_enc` TEXT         DEFAULT NULL,
    `changed_by` INT UNSIGNED DEFAULT NULL,
    `changed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vault_hist_vault` (`vault_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_vault_access` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vault_id`   INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `action`     VARCHAR(20)  NOT NULL DEFAULT 'reveal',
    `ip`         VARCHAR(64)  DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vault_access_vault` (`vault_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =========================================================================
   FAZ D — Entegrasyon ayarları + PayTR (secret alanlar AES-256-GCM şifreli)
   ====================================================================== */

CREATE TABLE IF NOT EXISTS `integrations` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `int_key`      VARCHAR(40)  NOT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 0,
    `api_url`      VARCHAR(255) DEFAULT NULL,
    `username`     VARCHAR(190) DEFAULT NULL,
    `secret_enc`   TEXT         DEFAULT NULL,   -- şifreli JSON (token/key/salt/şifre)
    `config_json`  TEXT         DEFAULT NULL,   -- şifresiz ek ayarlar (JSON)
    `last_test_at` DATETIME     DEFAULT NULL,
    `last_error`   VARCHAR(500) DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_integrations_key` (`int_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `integration_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `int_key`     VARCHAR(40)  NOT NULL,
    `action`      VARCHAR(40)  NOT NULL DEFAULT 'test',
    `status`      VARCHAR(20)  NOT NULL DEFAULT 'info',
    `message`     VARCHAR(500) DEFAULT NULL,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_integration_logs_key` (`int_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =========================================================================
   FAZ D — Lead Yönetimi (Chrome eklentisi güvenli API ile besleyebilir)
   ====================================================================== */

CREATE TABLE IF NOT EXISTS `leads` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_name`         VARCHAR(190) NOT NULL,
    `contact_name`         VARCHAR(150) DEFAULT NULL,
    `phone`                VARCHAR(40)  DEFAULT NULL,
    `whatsapp`             VARCHAR(40)  DEFAULT NULL,
    `email`                VARCHAR(190) DEFAULT NULL,
    `website`              VARCHAR(190) DEFAULT NULL,
    `instagram`            VARCHAR(190) DEFAULT NULL,
    `maps_url`             VARCHAR(500) DEFAULT NULL,
    `sector`               VARCHAR(120) DEFAULT NULL,
    `city`                 VARCHAR(80)  DEFAULT NULL,
    `district`             VARCHAR(80)  DEFAULT NULL,
    `source`               VARCHAR(80)  DEFAULT NULL,
    `notes`                TEXT         DEFAULT NULL,
    `status`               VARCHAR(30)  NOT NULL DEFAULT 'new',
    `assigned_personnel_id` INT UNSIGNED DEFAULT NULL,
    `last_message_at`      DATETIME     DEFAULT NULL,
    `is_deleted`           TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`           INT UNSIGNED DEFAULT NULL,
    `updated_by`           INT UNSIGNED DEFAULT NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_leads_status` (`status`),
    KEY `idx_leads_deleted` (`is_deleted`),
    KEY `idx_leads_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_api_log` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip`         VARCHAR(64)  DEFAULT NULL,
    `result`     VARCHAR(20)  NOT NULL DEFAULT 'ok',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lead_api_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead ayarları (API token + WhatsApp şablonu)
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('leads_api_token', ''),
    ('lead_wa_template', 'Merhaba {yetkili}, {firma} olarak sizinle iletişime geçmek istiyoruz.');

/* =========================================================================
   EK PART 25 — Yardım Merkezi / Panel Kullanım Rehberi
   ====================================================================== */

CREATE TABLE IF NOT EXISTS `help_articles` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`            VARCHAR(160) NOT NULL,
    `title`           VARCHAR(190) NOT NULL,
    `category`        VARCHAR(40)  NOT NULL DEFAULT 'dashboard',
    `short_desc`      VARCHAR(500) DEFAULT NULL,
    `content`         TEXT         DEFAULT NULL,
    `steps`           TEXT         DEFAULT NULL,   -- her satır bir adım
    `screen_path`     VARCHAR(190) DEFAULT NULL,   -- ör. "Ayarlar → Şirket Bilgileri"
    `related_module`  VARCHAR(40)  DEFAULT NULL,   -- ilgili modül anahtarı
    `search_keywords` VARCHAR(500) DEFAULT NULL,
    `tags`            VARCHAR(300) DEFAULT NULL,
    `sort`            INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by`      INT UNSIGNED DEFAULT NULL,
    `updated_by`      INT UNSIGNED DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_help_slug` (`slug`),
    KEY `idx_help_category` (`category`),
    KEY `idx_help_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan yardım konuları (idempotent: slug'a göre INSERT IGNORE)
INSERT IGNORE INTO `help_articles` (`slug`, `title`, `category`, `short_desc`, `content`, `steps`, `screen_path`, `related_module`, `search_keywords`, `tags`, `sort`) VALUES
('musteri-nasil-eklenir', 'Müşteri nasıl eklenir?', 'customers', 'Yeni bir müşteri (cari) kaydı oluşturma adımları.', 'Müşteriler modülünden yeni cari kartı oluşturabilir, kategori (TR, Yurtdışı, Bayi vb.) atayabilirsiniz.', 'Sol menüden "Müşteriler" bölümüne girin.\n"Yeni Müşteri" butonuna tıklayın.\nFirma adı ve gerekli bilgileri doldurun.\nMüşteri tipini seçin.\n"Kaydet" butonuna basın.', 'Müşteriler → Yeni Müşteri', 'customers', 'musteri ekle cari yeni firma kayit', 'müşteri,cari,ekleme', 10),
('teklif-nasil-olusturulur', 'Teklif nasıl oluşturulur?', 'quotes', 'Ürün satırlı, KDV/iskontolu teklif hazırlama.', 'Teklifler modülünde müşteri seçip ürün satırları ekleyerek, T-Soft''tan ürün aratarak teklif hazırlarsınız. Toplamlar otomatik hesaplanır.', 'Sol menüden "Teklifler" bölümüne girin.\n"Yeni Teklif" butonuna tıklayın.\nMüşteri seçin veya manuel girin.\n"T-Soft''ta Ara" ile ürün ekleyin ya da "Satır ekle" ile manuel girin.\nKDV/iskonto ayarlayın; toplam otomatik hesaplanır.\n"Kaydet" ile teklifi oluşturun; ardından PDF/WhatsApp/Mail ile paylaşın.', 'Teklifler → Yeni Teklif', 'quotes', 'teklif olustur urun kdv iskonto tsoft pdf', 'teklif,fiyat,pdf', 20),
('siparis-nasil-olusturulur', 'Sipariş nasıl oluşturulur?', 'orders', 'Sipariş kaydı ve kargo/ödeme bilgileri.', 'Siparişler modülünde ürün satırları, kargo firması, takip no ve ödeme durumuyla sipariş oluşturursunuz.', 'Sol menüden "Siparişler" bölümüne girin.\n"Yeni Sipariş" butonuna tıklayın.\nMüşteri ve ürün satırlarını girin.\nKargo ve ödeme bilgilerini doldurun.\n"Kaydet" butonuna basın.', 'Siparişler → Yeni Sipariş', 'orders', 'siparis olustur kargo odeme takip', 'sipariş,kargo', 30),
('servis-kaydi-nasil-acilir', 'Servis kaydı nasıl açılır?', 'service', 'Teknik servise cihaz kabul kaydı.', 'Teknik Servis modülünden cihaz kabul kaydı oluşturur, müşteri ve arıza bilgilerini girersiniz.', 'Sol menüden "Servis Kabul" bölümüne girin.\nMüşteri ve cihaz bilgilerini doldurun.\nArıza/servis sebebini yazın.\n"Kaydet" ile servis kaydını oluşturun.', 'Teknik Servis → Servis Kabul', 'service', 'servis kayit cihaz kabul ariza', 'servis,cihaz', 40),
('servis-durumu-nasil-degistirilir', 'Servis durumu nasıl değiştirilir?', 'service', 'Servis sürecinin durumunu güncelleme ve müşteriyi bilgilendirme.', 'Servis detay sayfasında yeni durum seçip kaydedebilir, ardından WhatsApp/e-posta ile müşteriyi bilgilendirebilirsiniz.', 'Servis kaydının detayına girin.\n"Durum" bölümünden yeni durumu seçin.\nGerekirse not ekleyin ve güncelleyin.\n"Durumu WhatsApp''tan bildir" veya "e-posta ile bildir" butonuyla müşteriyi bilgilendirin.', 'Teknik Servis → (kayıt) → Durum', 'service', 'servis durum degistir bilgilendirme whatsapp', 'servis,durum', 50),
('rma-kaydi-nasil-eklenir', 'İade/değişim kaydı nasıl eklenir?', 'rma', 'İade-değişim süreci kaydı oluşturma.', 'İade-Değişim Yönetimi modülünden yeni kayıt açar, platform, ürün ve işlem türünü girersiniz.', 'Sol menüden "İade-Değişim Yönetimi" bölümüne girin.\n"Yeni Kayıt" butonuna tıklayın.\nFirma, ürün, platform ve işlem türünü doldurun.\n"Kaydet" butonuna basın.', 'İade-Değişim → Yeni Kayıt', 'rma', 'iade degisim rma kayit platform', 'iade,değişim,rma', 60),
('rma-csv-nasil-ice-aktarilir', 'CSV nasıl içeri aktarılır?', 'rma', 'İade-değişim kayıtlarını CSV ile toplu içe aktarma.', 'Önce "Örnek CSV indir" ile şablonu alın, doldurun ve içe aktarın. Sistem başarılı/atlanan/hatalı satır sayısını raporlar.', 'İade-Değişim → "CSV İçe Aktar" bölümüne girin.\n"Örnek CSV indir" ile şablonu indirin.\nŞablonu Excel''de doldurun (Türkçe karakter/UTF-8).\nDoldurulmuş dosyayı seçip "İçe aktar" deyin.\nSonuç özetini (eklenen/atlanan/hatalı) kontrol edin.', 'İade-Değişim → CSV İçe Aktar', 'rma', 'csv ice aktar import ornek sablon excel', 'csv,import', 70),
('logo-favicon-nasil-degistirilir', 'Logo ve favicon nasıl değiştirilir?', 'settings', 'Panel logosu ve favicon yükleme.', 'Ayarlar → Şirket Bilgileri ekranından logo ve favicon dosyalarını yükleyebilirsiniz. Favicon tüm panelde sekme ikonu olarak görünür.', 'Sol menüden "Genel Ayarlar → Şirket Bilgileri" bölümüne girin.\n"Logo & Favicon" kartına gelin.\nLogo için PNG/JPG/SVG, favicon için ICO/PNG/SVG seçin.\n"Kaydet" butonuna basın.', 'Ayarlar → Şirket Bilgileri → Logo & Favicon', 'settings', 'logo favicon yukle degistir gorsel', 'logo,favicon', 80),
('rol-yetkisi-nasil-verilir', 'Kullanıcı yetkisi nasıl verilir?', 'roles', 'Rollere modül ve işlem bazlı yetki atama.', 'Ayarlar → Roller ekranında her modül için Görüntüle/Ekle/Düzenle/Sil gibi işlem yetkilerini checkbox ile atarsınız. Süper Admin tüm yetkilere sahiptir.', 'Sol menüden "Genel Ayarlar → Roller" bölümüne girin.\nDüzenlemek istediğiniz rolü açın.\nModül başlıkları altındaki işlem kutularını işaretleyin.\n"Tümünü seç/Kaldır" ile hızlı seçim yapın.\n"Kaydet" butonuna basın.', 'Ayarlar → Roller', 'settings', 'rol yetki izin permission kullanici checkbox', 'rol,yetki', 90),
('sifre-kasasi-nasil-kullanilir', 'Şifre kasası nasıl kullanılır?', 'password_vault', 'Şifreleri güvenli saklama ve panel şifresiyle görüntüleme.', 'Şifre Kasası şifreleri AES-256 ile şifreli saklar. Bir şifreyi görmek için kendi panel şifrenizi yeniden girmeniz gerekir; her görüntüleme loglanır.', 'Sol menüden "Şifre Kasası" bölümüne girin.\n"Yeni Kayıt" ile başlık, kullanıcı adı ve şifreyi girin.\nKaydı açıp "Şifreyi Göster" butonuna basın.\nPanel şifrenizi girerek şifreyi güvenle görüntüleyin.', 'Şifre Kasası', 'password_vault', 'sifre kasa vault guvenli goster panel sifre', 'şifre,güvenlik', 100),
('sevkiyat-nasil-olusturulur', 'Sevkiyat nasıl oluşturulur?', 'shipments', 'Teslimat/toplama sevkiyatı oluşturma ve sevkiyatçı atama.', 'Sevkiyat Takibi modülünde adres seçip sevkiyat oluşturur, bir sevkiyatçıya atarsınız. Durum değiştikçe yöneticiye WhatsApp bilgilendirme butonu çıkar.', 'Sol menüden "Sevkiyat Takibi" bölümüne girin.\n"Yeni Sevkiyat" butonuna tıklayın.\nMüşteri/adres, tip (teslimat/toplama) ve tarih girin.\nSevkiyatçı personel atayın.\n"Kaydet" butonuna basın.', 'Sevkiyat Takibi → Yeni Sevkiyat', 'shipments', 'sevkiyat olustur teslimat toplama sevkiyatci atama', 'sevkiyat,teslimat', 110),
('sevkiyat-fotograf-nasil-yuklenir', 'Sevkiyatçı teslimat fotoğrafı nasıl yükler?', 'shipments', 'Sevkiyatçının teslimat/toplama fotoğrafı yüklemesi.', 'Sevkiyatçı kendi sevkiyatının detayında durumu günceller ve kamera ile teslimat/toplama fotoğrafı yükler. Fotoğraf sevkiyata bağlanır ve loglanır.', 'Size atanan sevkiyatın detayına girin.\nKonum linkini açıp adrese gidin.\nDurumu güncelleyin (ör. Teslim edildi).\n"Fotoğraf yükle" ile kamera/dosya seçip yükleyin.\nGerekirse teslimat notu yazıp tamamlayın.', 'Sevkiyat Takibi → (sevkiyat) → Fotoğraf', 'shipments', 'sevkiyat fotograf yukle teslimat kamera sevkiyatci', 'sevkiyat,fotoğraf', 120);

/* =========================================================================
   EK PART 26 — Sevkiyat Takibi (adresler, sevkiyatlar, toplama, foto, rota)
   ====================================================================== */

CREATE TABLE IF NOT EXISTS `shipment_addresses` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_name`   VARCHAR(190) NOT NULL,
    `contact_name`   VARCHAR(150) DEFAULT NULL,
    `phone`          VARCHAR(40)  DEFAULT NULL,
    `whatsapp`       VARCHAR(40)  DEFAULT NULL,
    `email`          VARCHAR(190) DEFAULT NULL,
    `country`        VARCHAR(80)  DEFAULT NULL,
    `city`           VARCHAR(80)  DEFAULT NULL,
    `district`       VARCHAR(80)  DEFAULT NULL,
    `neighborhood`   VARCHAR(120) DEFAULT NULL,
    `address`        TEXT         DEFAULT NULL,
    `location_url`   VARCHAR(500) DEFAULT NULL,
    `lat`            VARCHAR(32)  DEFAULT NULL,
    `lng`            VARCHAR(32)  DEFAULT NULL,
    `address_type`   VARCHAR(20)  NOT NULL DEFAULT 'delivery',
    `default_note`   VARCHAR(500) DEFAULT NULL,
    `working_hours`  VARCHAR(190) DEFAULT NULL,
    `vehicle_access` VARCHAR(120) DEFAULT NULL,
    `floor_building` VARCHAR(120) DEFAULT NULL,
    `has_elevator`   TINYINT(1)   NOT NULL DEFAULT 0,
    `notes`          TEXT         DEFAULT NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `is_deleted`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `updated_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ship_addr_city` (`city`),
    KEY `idx_ship_addr_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipments` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipment_no`   VARCHAR(40)  NOT NULL,
    `shipment_date` DATE         DEFAULT NULL,
    `planned_date`  DATE         DEFAULT NULL,
    `customer_name` VARCHAR(190) DEFAULT NULL,
    `address_id`    INT UNSIGNED DEFAULT NULL,
    `shipment_type` VARCHAR(20)  NOT NULL DEFAULT 'delivery',
    `related_type`  VARCHAR(20)  NOT NULL DEFAULT 'manual',
    `related_ref`   VARCHAR(60)  DEFAULT NULL,
    `courier_id`    INT UNSIGNED DEFAULT NULL,
    `vehicle_info`  VARCHAR(120) DEFAULT NULL,
    `priority`      VARCHAR(20)  NOT NULL DEFAULT 'normal',
    `status`        VARCHAR(30)  NOT NULL DEFAULT 'planned',
    `description`   TEXT         DEFAULT NULL,
    `manager_note`  TEXT         DEFAULT NULL,
    `courier_note`  TEXT         DEFAULT NULL,
    `fail_reason`   VARCHAR(60)  DEFAULT NULL,
    `route_date`    DATE         DEFAULT NULL,
    `route_seq`     INT UNSIGNED DEFAULT NULL,
    `route_time`    VARCHAR(40)  DEFAULT NULL,
    `is_deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `updated_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_shipment_no` (`shipment_no`),
    KEY `idx_shipments_status` (`status`),
    KEY `idx_shipments_courier` (`courier_id`),
    KEY `idx_shipments_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipment_items` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipment_id`  INT UNSIGNED NOT NULL,
    `name`         VARCHAR(255) NOT NULL,
    `product_code` VARCHAR(80)  DEFAULT NULL,
    `barcode`      VARCHAR(80)  DEFAULT NULL,
    `qty`          DECIMAL(12,2) NOT NULL DEFAULT 1,
    `note`         VARCHAR(255) DEFAULT NULL,
    `sort`         INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_shipment_items_ship` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipment_collections` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipment_id`   INT UNSIGNED DEFAULT NULL,
    `company_name`  VARCHAR(190) DEFAULT NULL,
    `address_id`    INT UNSIGNED DEFAULT NULL,
    `collection_date` DATE       DEFAULT NULL,
    `package_count` INT UNSIGNED DEFAULT NULL,
    `photo_required` TINYINT(1)  NOT NULL DEFAULT 0,
    `note`          TEXT         DEFAULT NULL,
    `status`        VARCHAR(30)  NOT NULL DEFAULT 'planned',
    `is_deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `updated_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ship_coll_ship` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipment_collection_items` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `collection_id` INT UNSIGNED NOT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `product_code`  VARCHAR(80)  DEFAULT NULL,
    `barcode`       VARCHAR(80)  DEFAULT NULL,
    `qty`           DECIMAL(12,2) NOT NULL DEFAULT 1,
    `taken_qty`     DECIMAL(12,2) NOT NULL DEFAULT 0,
    `condition_note` VARCHAR(255) DEFAULT NULL,
    `is_taken`      TINYINT(1)   NOT NULL DEFAULT 0,
    `note`          VARCHAR(255) DEFAULT NULL,
    `sort`          INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_ship_coll_items_coll` (`collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipment_photos` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipment_id`   INT UNSIGNED NOT NULL,
    `collection_id` INT UNSIGNED DEFAULT NULL,
    `file_path`     VARCHAR(255) NOT NULL,
    `photo_type`    VARCHAR(20)  NOT NULL DEFAULT 'delivery',
    `uploaded_by`   INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ship_photos_ship` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sevkiyat durum logları: hem eski (shipment_id) hem yeni rota/durak (route_id/stop_id)
-- kaynaklarını tutabilir. Alanların tamamı nullable; kaynak neyse o doldurulur.
CREATE TABLE IF NOT EXISTS `shipment_status_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipment_id` INT UNSIGNED DEFAULT NULL,
    `route_id`    INT UNSIGNED DEFAULT NULL,
    `stop_id`     INT UNSIGNED DEFAULT NULL,
    `old_status`  VARCHAR(30)  DEFAULT NULL,
    `new_status`  VARCHAR(30)  NOT NULL,
    `note`        VARCHAR(500) DEFAULT NULL,
    `changed_by`  INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ship_status_logs_ship` (`shipment_id`),
    KEY `idx_ship_status_logs_route` (`route_id`),
    KEY `idx_ship_status_logs_stop` (`stop_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipment_route_plans` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plan_date`  DATE         DEFAULT NULL,
    `courier_id` INT UNSIGNED DEFAULT NULL,
    `notes`      VARCHAR(500) DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ship_route_date` (`plan_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- Sevkiyat: Rota tabanlı model (Adres Defteri + Günlük Rota + Duraklar + Kanıt)
-- Ana kayıt mantığı "tek tek sevkiyat" değil, "rota ve rota durakları"dır.
-- =========================================================================

-- Günlük rotalar
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

-- Rota durakları
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

-- Teslimat / toplama kanıtı (fotoğraf)
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

-- Sevkiyat ayarları (yönetici WhatsApp + mesaj şablonu + rota varsayılanları) — app_settings
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('shipment_manager_name', ''),
    ('shipment_manager_whatsapp', ''),
    ('shipment_wa_template', 'Merhaba, {sevkiyat_no} numaralı sevkiyat güncellendi.\n\nFirma: {firma_adi}\nTip: {sevkiyat_tipi}\nDurum: {durum}\nSevkiyatçı: {sevkiyatci_adi}\nAdres: {adres}\nNot: {not}\n\nPanelden kontrol edebilirsiniz: {panel_linki}'),
    ('shipment_default_start', ''),
    ('shipment_default_end', ''),
    ('shipment_photo_max_mb', '8'),
    ('shipment_photo_types', 'jpg,jpeg,png,webp');
