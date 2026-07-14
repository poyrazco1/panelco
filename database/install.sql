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
    `recon_type`     VARCHAR(20)  NOT NULL DEFAULT 'cari',
    `period`         VARCHAR(40)  DEFAULT NULL,
    `period_start`   DATE         DEFAULT NULL,
    `period_end`     DATE         DEFAULT NULL,
    `debit`          DECIMAL(15,2) NOT NULL DEFAULT 0,
    `credit`         DECIMAL(15,2) NOT NULL DEFAULT 0,
    `balance`        DECIMAL(15,2) NOT NULL DEFAULT 0,
    `currency`       VARCHAR(10)  NOT NULL DEFAULT 'TRY',
    `agreement`      VARCHAR(20)  NOT NULL DEFAULT 'pending',
    `description`    TEXT         DEFAULT NULL,
    `extra_note`     TEXT         DEFAULT NULL,
    `authorized_name` VARCHAR(150) DEFAULT NULL,
    `recon_date`     DATE         DEFAULT NULL,
    `prepared_by`    INT UNSIGNED DEFAULT NULL,
    `approved_by`    INT UNSIGNED DEFAULT NULL,
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
    `place_id`             VARCHAR(160) DEFAULT NULL,
    `company_name`         VARCHAR(190) NOT NULL,
    `contact_name`         VARCHAR(150) DEFAULT NULL,
    `phone`                VARCHAR(40)  DEFAULT NULL,
    `alt_phone`            VARCHAR(40)  DEFAULT NULL,
    `whatsapp`             VARCHAR(40)  DEFAULT NULL,
    `email`                VARCHAR(190) DEFAULT NULL,
    `website`              VARCHAR(190) DEFAULT NULL,
    `domain`               VARCHAR(190) DEFAULT NULL,
    `instagram`            VARCHAR(190) DEFAULT NULL,
    `maps_url`             VARCHAR(500) DEFAULT NULL,
    `google_rating`        DECIMAL(2,1) DEFAULT NULL,
    `review_count`         INT UNSIGNED DEFAULT NULL,
    `has_website`          TINYINT(1)   DEFAULT NULL,
    `sector`               VARCHAR(120) DEFAULT NULL,
    `main_category`        VARCHAR(120) DEFAULT NULL,
    `other_categories`     VARCHAR(500) DEFAULT NULL,
    `country`              VARCHAR(80)  DEFAULT NULL,
    `city`                 VARCHAR(80)  DEFAULT NULL,
    `district`             VARCHAR(80)  DEFAULT NULL,
    `neighborhood`         VARCHAR(120) DEFAULT NULL,
    `postal_code`          VARCHAR(20)  DEFAULT NULL,
    `address`              VARCHAR(500) DEFAULT NULL,
    `lat`                  DECIMAL(10,7) DEFAULT NULL,
    `lng`                  DECIMAL(10,7) DEFAULT NULL,
    `working_status`       VARCHAR(40)  DEFAULT NULL,
    `working_hours`        TEXT         DEFAULT NULL,
    `search_keyword`       VARCHAR(190) DEFAULT NULL,
    `source`               VARCHAR(80)  DEFAULT NULL,
    `package`              VARCHAR(80)  DEFAULT NULL,
    `notes`                TEXT         DEFAULT NULL,
    `status`               VARCHAR(30)  NOT NULL DEFAULT 'new',
    `priority`             VARCHAR(20)  NOT NULL DEFAULT 'normal',
    `assigned_personnel_id` INT UNSIGNED DEFAULT NULL,
    `scan_id`              INT UNSIGNED DEFAULT NULL,
    `last_message_at`      DATETIME     DEFAULT NULL,
    `last_contact_at`      DATETIME     DEFAULT NULL,
    `next_action_at`       DATETIME     DEFAULT NULL,
    `is_deleted`           TINYINT(1)   NOT NULL DEFAULT 0,
    `deleted_at`           DATETIME     DEFAULT NULL,
    `deleted_by`           INT UNSIGNED DEFAULT NULL,
    `delete_reason`        VARCHAR(255) DEFAULT NULL,
    `created_by`           INT UNSIGNED DEFAULT NULL,
    `updated_by`           INT UNSIGNED DEFAULT NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_leads_place` (`place_id`),
    KEY `idx_leads_status` (`status`),
    KEY `idx_leads_deleted` (`is_deleted`),
    KEY `idx_leads_phone` (`phone`),
    KEY `idx_leads_domain` (`domain`),
    KEY `idx_leads_assigned` (`assigned_personnel_id`),
    KEY `idx_leads_next_action` (`next_action_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_api_log` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip`         VARCHAR(64)  DEFAULT NULL,
    `result`     VARCHAR(20)  NOT NULL DEFAULT 'ok',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lead_api_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead Tarama işleri (scan wizard)
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

-- Lead ayarları (API token + WhatsApp şablonu + tarama varsayılanı)
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('leads_api_token', ''),
    ('lead_wa_template', 'Merhaba {yetkili}, {firma} olarak sizinle iletişime geçmek istiyoruz.'),
    ('lead_scan_target_default', '100');

/* =========================================================================
   FAZ D2 — Lead CRM + Google Places (§17). Ayrıntı: database/migrations/2026-07-leads-crm.sql
   ====================================================================== */

-- Google Places ayarları (tek satır; şifreli anahtar)
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

-- Google Places aramaları (çalıştırılan taramalar)
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

-- Arama sonuçları (önizleme; lead'e dönüşmeden önce)
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

-- API kullanım/maliyet logu
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

-- Lead durumları (yönetilebilir)
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

-- Durum geçmişi
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

-- Aktivite geçmişi
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

-- Arama (telefon) kayıtları
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

-- WhatsApp kayıtları
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

-- Notlar
CREATE TABLE IF NOT EXISTS `lead_notes` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`    INT UNSIGNED NOT NULL,
    `note`       TEXT DEFAULT NULL,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_ln_lead` (`lead_id`),
    CONSTRAINT `fk_ln_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hatırlatmalar
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

-- Atamalar (geçmiş)
CREATE TABLE IF NOT EXISTS `lead_assignments` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id`     INT UNSIGNED NOT NULL,
    `personnel_id` INT UNSIGNED NULL,
    `assigned_by` INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lasg_lead` (`lead_id`),
    CONSTRAINT `fk_lasg_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etiketler + ilişki
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

-- Kayıtlı aramalar
CREATE TABLE IF NOT EXISTS `lead_saved_searches` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `config_json` MEDIUMTEXT DEFAULT NULL,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_lss_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Çalışma listeleri (arama/whatsapp)
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

-- Denetim (audit) logu
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

-- =========================================================================
-- FORM MERKEZİ (Form Center) — iç/dış formlar, gönderimler, onay, token, ek
-- =========================================================================

-- 1) Form kategorileri
CREATE TABLE IF NOT EXISTS `form_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(150) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_form_cat_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Form şablonları
CREATE TABLE IF NOT EXISTS `form_templates` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id`      INT UNSIGNED DEFAULT NULL,
    `form_name`        VARCHAR(190) NOT NULL,
    `form_key`         VARCHAR(120) NOT NULL,
    `form_type`        VARCHAR(20)  NOT NULL DEFAULT 'internal',
    `description`      VARCHAR(500) DEFAULT NULL,
    `fields_json`      LONGTEXT     DEFAULT NULL,
    `requires_approval` TINYINT(1)  NOT NULL DEFAULT 0,
    `is_external`      TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`       INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`       INT UNSIGNED DEFAULT NULL,
    `updated_by`       INT UNSIGNED DEFAULT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_form_key` (`form_key`),
    KEY `idx_form_tpl_cat` (`category_id`),
    KEY `idx_form_tpl_active` (`is_active`),
    KEY `idx_form_tpl_type` (`form_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Form gönderimleri
CREATE TABLE IF NOT EXISTS `form_submissions` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_template_id`     INT UNSIGNED NOT NULL,
    `submission_no`        VARCHAR(40)  NOT NULL,
    `submitted_by_user_id` INT UNSIGNED DEFAULT NULL,
    `external_token_id`    INT UNSIGNED DEFAULT NULL,
    `customer_name`        VARCHAR(190) DEFAULT NULL,
    `company_name`         VARCHAR(190) DEFAULT NULL,
    `phone`                VARCHAR(40)  DEFAULT NULL,
    `email`                VARCHAR(190) DEFAULT NULL,
    `data_json`            LONGTEXT     DEFAULT NULL,
    `status`               VARCHAR(20)  NOT NULL DEFAULT 'new',
    `assigned_user_id`     INT UNSIGNED DEFAULT NULL,
    `approval_status`      VARCHAR(20)  NOT NULL DEFAULT 'none',
    `approved_by`          INT UNSIGNED DEFAULT NULL,
    `approved_at`          DATETIME     DEFAULT NULL,
    `rejected_by`          INT UNSIGNED DEFAULT NULL,
    `rejected_at`          DATETIME     DEFAULT NULL,
    `rejection_reason`     VARCHAR(500) DEFAULT NULL,
    `related_module`       VARCHAR(40)  DEFAULT NULL,
    `related_record_id`    INT UNSIGNED DEFAULT NULL,
    `converted_at`         DATETIME     DEFAULT NULL,
    `converted_by`         INT UNSIGNED DEFAULT NULL,
    `created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`           DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_submission_no` (`submission_no`),
    KEY `idx_form_sub_tpl` (`form_template_id`),
    KEY `idx_form_sub_status` (`status`),
    KEY `idx_form_sub_approval` (`approval_status`),
    KEY `idx_form_sub_assigned` (`assigned_user_id`),
    KEY `idx_form_sub_submitter` (`submitted_by_user_id`),
    KEY `idx_form_sub_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Dış form token linkleri
CREATE TABLE IF NOT EXISTS `form_external_tokens` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `form_template_id` INT UNSIGNED NOT NULL,
    `token`            VARCHAR(64)  NOT NULL,
    `title`            VARCHAR(190) DEFAULT NULL,
    `expires_at`       DATETIME     DEFAULT NULL,
    `max_uses`         INT UNSIGNED DEFAULT NULL,
    `used_count`       INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
    `created_by`       INT UNSIGNED DEFAULT NULL,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`       DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_form_token` (`token`),
    KEY `idx_form_token_tpl` (`form_template_id`),
    KEY `idx_form_token_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Gönderim durum logları
CREATE TABLE IF NOT EXISTS `form_submission_logs` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `submission_id` INT UNSIGNED NOT NULL,
    `old_status`    VARCHAR(30)  DEFAULT NULL,
    `new_status`    VARCHAR(30)  NOT NULL,
    `note`          VARCHAR(500) DEFAULT NULL,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_form_log_sub` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Form dosya ekleri
CREATE TABLE IF NOT EXISTS `form_uploads` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `submission_id` INT UNSIGNED NOT NULL,
    `field_name`    VARCHAR(120) DEFAULT NULL,
    `file_path`     VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) DEFAULT NULL,
    `mime_type`     VARCHAR(100) DEFAULT NULL,
    `file_size`     INT UNSIGNED DEFAULT NULL,
    `uploaded_by`   INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_form_upload_sub` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Merkezi mesaj şablonları (WhatsApp/e-posta) — app_settings
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('form_msg_received', 'Merhaba {ad_soyad}, {form_adi} talebiniz alındı. Talep No: {talep_no}. En kısa sürede dönüş yapılacaktır.'),
    ('form_msg_approved', 'Merhaba {ad_soyad}, {form_adi} talebiniz ({talep_no}) ONAYLANDI.'),
    ('form_msg_rejected', 'Merhaba {ad_soyad}, {form_adi} talebiniz ({talep_no}) reddedildi. Sebep: {sebep}'),
    ('form_upload_max_mb', '8'),
    ('form_upload_types', 'jpg,jpeg,png,webp,pdf');

-- Kategoriler (ilk kurulum)
INSERT IGNORE INTO `form_categories` (`name`, `description`, `sort_order`, `is_active`) VALUES
    ('Şirket İçi Formlar', 'Personelin dolduracağı iç formlar', 10, 1),
    ('Müşteri / Dış Formlar', 'Müşteri ve dış kullanıcı formları', 20, 1),
    ('Teknik Servis Formları', 'Servis başvuru ve süreç formları', 30, 1),
    ('Sevkiyat Formları', 'Teslim ve toplama formları', 40, 1),
    ('İK Formları', 'İzin ve personel formları', 50, 1),
    ('Muhasebe / Satın Alma Formları', 'Masraf, satın alma ve tedarik formları', 60, 1);

-- Form şablonları (ilk kurulum)
INSERT IGNORE INTO `form_templates` (`category_id`,`form_name`,`form_key`,`form_type`,`description`,`fields_json`,`requires_approval`,`is_external`,`is_active`,`sort_order`)
SELECT id, 'Lead / Teklif Talep Formu', 'lead_teklif_talep', 'both', NULL, '[{"label":"Ad Soyad","name":"ad_soyad","type":"text","required":true,"placeholder":"","help_text":""},{"label":"Firma","name":"firma","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Telefon","name":"telefon","type":"phone","required":true,"placeholder":"05xx xxx xx xx","help_text":""},{"label":"E-posta","name":"email","type":"email","required":false,"placeholder":"","help_text":""},{"label":"Talep Konusu","name":"talep_konusu","type":"text","required":true,"placeholder":"","help_text":""},{"label":"Açıklama","name":"aciklama","type":"textarea","required":false,"placeholder":"","help_text":""},{"label":"Dosya / Görsel","name":"dosya","type":"file","required":false,"placeholder":"","help_text":"Opsiyonel"}]', 0, 1, 1, 10 FROM `form_categories` WHERE `name`='Müşteri / Dış Formlar' LIMIT 1;
INSERT IGNORE INTO `form_templates` (`category_id`,`form_name`,`form_key`,`form_type`,`description`,`fields_json`,`requires_approval`,`is_external`,`is_active`,`sort_order`)
SELECT id, 'Teknik Servis Ön Başvuru Formu', 'teknik_servis_on_basvuru', 'both', NULL, '[{"label":"Ad Soyad / Firma","name":"ad_firma","type":"text","required":true,"placeholder":"","help_text":""},{"label":"Telefon","name":"telefon","type":"phone","required":true,"placeholder":"","help_text":""},{"label":"Ürün Tipi","name":"urun_tipi","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Marka / Model","name":"marka_model","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Seri No","name":"seri_no","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Arıza Açıklaması","name":"ariza","type":"textarea","required":true,"placeholder":"","help_text":""},{"label":"Fotoğraf","name":"fotograf","type":"file","required":false,"placeholder":"","help_text":"Opsiyonel"}]', 0, 1, 1, 20 FROM `form_categories` WHERE `name`='Teknik Servis Formları' LIMIT 1;
INSERT IGNORE INTO `form_templates` (`category_id`,`form_name`,`form_key`,`form_type`,`description`,`fields_json`,`requires_approval`,`is_external`,`is_active`,`sort_order`)
SELECT id, 'İade / Değişim Talep Formu', 'iade_degisim_talep', 'both', NULL, '[{"label":"Ad Soyad / Firma","name":"ad_firma","type":"text","required":true,"placeholder":"","help_text":""},{"label":"Telefon","name":"telefon","type":"phone","required":true,"placeholder":"","help_text":""},{"label":"Platform","name":"platform","type":"select","required":false,"placeholder":"","help_text":"","options":["T-Soft","Trendyol","Hepsiburada","N11","Amazon","Mağaza","Diğer"]},{"label":"Fatura No","name":"fatura_no","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Ürün Modeli","name":"urun_modeli","type":"text","required":false,"placeholder":"","help_text":""},{"label":"İşlem Tipi","name":"islem_tipi","type":"select","required":true,"placeholder":"","help_text":"","options":["İade","Değişim","İptal"]},{"label":"Açıklama","name":"aciklama","type":"textarea","required":false,"placeholder":"","help_text":""},{"label":"Görsel / Belge","name":"belge","type":"file","required":false,"placeholder":"","help_text":"Opsiyonel"}]', 0, 1, 1, 30 FROM `form_categories` WHERE `name`='Müşteri / Dış Formlar' LIMIT 1;
INSERT IGNORE INTO `form_templates` (`category_id`,`form_name`,`form_key`,`form_type`,`description`,`fields_json`,`requires_approval`,`is_external`,`is_active`,`sort_order`)
SELECT id, 'İzin Talep Formu', 'izin_talep', 'internal', NULL, '[{"label":"İzin Türü","name":"izin_turu","type":"select","required":true,"placeholder":"","help_text":"","options":["Yıllık İzin","Ücretsiz İzin","Mazeret İzni","Rapor","Diğer"]},{"label":"Başlangıç Tarihi","name":"baslangic","type":"date","required":true,"placeholder":"","help_text":""},{"label":"Bitiş Tarihi","name":"bitis","type":"date","required":true,"placeholder":"","help_text":""},{"label":"Gün Sayısı","name":"gun_sayisi","type":"number","required":false,"placeholder":"","help_text":""},{"label":"Açıklama","name":"aciklama","type":"textarea","required":false,"placeholder":"","help_text":""}]', 1, 0, 1, 40 FROM `form_categories` WHERE `name`='İK Formları' LIMIT 1;
INSERT IGNORE INTO `form_templates` (`category_id`,`form_name`,`form_key`,`form_type`,`description`,`fields_json`,`requires_approval`,`is_external`,`is_active`,`sort_order`)
SELECT id, 'Satın Alma Talep Formu', 'satin_alma_talep', 'internal', NULL, '[{"label":"Ürün / Hizmet Adı","name":"urun_hizmet","type":"text","required":true,"placeholder":"","help_text":""},{"label":"Miktar","name":"miktar","type":"number","required":true,"placeholder":"","help_text":""},{"label":"Tahmini Tutar","name":"tahmini_tutar","type":"number","required":false,"placeholder":"","help_text":""},{"label":"Tedarikçi","name":"tedarikci","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Gerekçe","name":"gerekce","type":"textarea","required":true,"placeholder":"","help_text":""},{"label":"Aciliyet","name":"aciliyet","type":"select","required":false,"placeholder":"","help_text":"","options":["Normal","Acil","Kritik"]}]', 1, 0, 1, 50 FROM `form_categories` WHERE `name`='Muhasebe / Satın Alma Formları' LIMIT 1;
INSERT IGNORE INTO `form_templates` (`category_id`,`form_name`,`form_key`,`form_type`,`description`,`fields_json`,`requires_approval`,`is_external`,`is_active`,`sort_order`)
SELECT id, 'Masraf Talep Formu', 'masraf_talep', 'internal', NULL, '[{"label":"Masraf Türü","name":"masraf_turu","type":"select","required":true,"placeholder":"","help_text":"","options":["Yol","Konaklama","Yemek","Malzeme","Diğer"]},{"label":"Tutar","name":"tutar","type":"number","required":true,"placeholder":"","help_text":""},{"label":"Tarih","name":"tarih","type":"date","required":true,"placeholder":"","help_text":""},{"label":"Açıklama","name":"aciklama","type":"textarea","required":false,"placeholder":"","help_text":""},{"label":"Fiş / Fatura","name":"fis_fatura","type":"file","required":true,"placeholder":"","help_text":"Fiş veya fatura görseli"}]', 1, 0, 1, 60 FROM `form_categories` WHERE `name`='Muhasebe / Satın Alma Formları' LIMIT 1;
INSERT IGNORE INTO `form_templates` (`category_id`,`form_name`,`form_key`,`form_type`,`description`,`fields_json`,`requires_approval`,`is_external`,`is_active`,`sort_order`)
SELECT id, 'Sevkiyat Teslim Formu', 'sevkiyat_teslim', 'internal', NULL, '[{"label":"Firma / Müşteri","name":"firma_musteri","type":"text","required":true,"placeholder":"","help_text":""},{"label":"Telefon","name":"telefon","type":"phone","required":false,"placeholder":"","help_text":""},{"label":"Teslimat Adresi","name":"teslimat_adresi","type":"textarea","required":true,"placeholder":"","help_text":""},{"label":"Teslim Edilen Ürünler","name":"urunler","type":"textarea","required":true,"placeholder":"","help_text":""},{"label":"Teslim Alan Kişi","name":"teslim_alan","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Fotoğraf","name":"fotograf","type":"file","required":false,"placeholder":"","help_text":""},{"label":"Not","name":"not","type":"textarea","required":false,"placeholder":"","help_text":""}]', 0, 0, 1, 70 FROM `form_categories` WHERE `name`='Sevkiyat Formları' LIMIT 1;
INSERT IGNORE INTO `form_templates` (`category_id`,`form_name`,`form_key`,`form_type`,`description`,`fields_json`,`requires_approval`,`is_external`,`is_active`,`sort_order`)
SELECT id, 'Ürün Toplama Formu', 'urun_toplama', 'internal', NULL, '[{"label":"Firma / Müşteri","name":"firma_musteri","type":"text","required":true,"placeholder":"","help_text":""},{"label":"Telefon","name":"telefon","type":"phone","required":false,"placeholder":"","help_text":""},{"label":"Toplama Adresi","name":"toplama_adresi","type":"textarea","required":true,"placeholder":"","help_text":""},{"label":"Alınacak Ürünler","name":"urunler","type":"textarea","required":true,"placeholder":"","help_text":""},{"label":"Fotoğraf","name":"fotograf","type":"file","required":false,"placeholder":"","help_text":""},{"label":"Not","name":"not","type":"textarea","required":false,"placeholder":"","help_text":""}]', 0, 0, 1, 80 FROM `form_categories` WHERE `name`='Sevkiyat Formları' LIMIT 1;
INSERT IGNORE INTO `form_templates` (`category_id`,`form_name`,`form_key`,`form_type`,`description`,`fields_json`,`requires_approval`,`is_external`,`is_active`,`sort_order`)
SELECT id, 'Müşteri Bilgi Formu', 'musteri_bilgi', 'both', NULL, '[{"label":"Firma Adı","name":"firma_adi","type":"text","required":true,"placeholder":"","help_text":""},{"label":"Yetkili Kişi","name":"yetkili","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Telefon","name":"telefon","type":"phone","required":true,"placeholder":"","help_text":""},{"label":"E-posta","name":"email","type":"email","required":false,"placeholder":"","help_text":""},{"label":"Vergi Dairesi","name":"vergi_dairesi","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Vergi No","name":"vergi_no","type":"text","required":false,"placeholder":"","help_text":""},{"label":"Adres","name":"adres","type":"textarea","required":false,"placeholder":"","help_text":""},{"label":"Not","name":"not","type":"textarea","required":false,"placeholder":"","help_text":""}]', 0, 1, 1, 90 FROM `form_categories` WHERE `name`='Müşteri / Dış Formlar' LIMIT 1;

/* =====================================================================
   EK PART 27 — YURTDIŞI MÜŞTERİLER / İTHALAT-İHRACAT CRM
   (idempotent CREATE TABLE IF NOT EXISTS; canlıda veri kaybı yok)
   ====================================================================== */
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

/* =========================================================================
 |  BELGE E-POSTA / GÖNDERİM SİSTEMİ
 |  Ayrıntı ve idempotent yükseltme: migrations/2026-07-document-email-system.sql
 * ====================================================================== */
CREATE TABLE IF NOT EXISTS `smtp_profiles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `host` VARCHAR(190) NOT NULL DEFAULT '',
    `port` SMALLINT UNSIGNED NOT NULL DEFAULT 465,
    `encryption` ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',
    `username` VARCHAR(190) NOT NULL DEFAULT '',
    `password_enc` TEXT DEFAULT NULL,
    `from_email` VARCHAR(190) NOT NULL DEFAULT '',
    `from_name` VARCHAR(190) NOT NULL DEFAULT '',
    `timeout` SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    `test_email` VARCHAR(190) NOT NULL DEFAULT '',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_smtp_name` (`name`),
    KEY `idx_smtp_active` (`is_active`), KEY `idx_smtp_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_key` VARCHAR(60) NOT NULL DEFAULT '',
    `name` VARCHAR(150) NOT NULL,
    `subject` VARCHAR(300) NOT NULL DEFAULT '',
    `body` MEDIUMTEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_tpl_module` (`module_key`), KEY `idx_tpl_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `department_email_accounts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_name` VARCHAR(150) NOT NULL,
    `module_key` VARCHAR(60) NOT NULL DEFAULT '',
    `from_name` VARCHAR(190) NOT NULL DEFAULT '',
    `from_email` VARCHAR(190) NOT NULL DEFAULT '',
    `reply_to_mode` ENUM('user','department','fixed') NOT NULL DEFAULT 'user',
    `reply_to_fixed` VARCHAR(190) NOT NULL DEFAULT '',
    `default_cc` VARCHAR(500) NOT NULL DEFAULT '',
    `default_bcc` VARCHAR(500) NOT NULL DEFAULT '',
    `forward_to` VARCHAR(500) NOT NULL DEFAULT '',
    `smtp_profile_id` INT UNSIGNED DEFAULT NULL,
    `template_id` INT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_dept_module` (`module_key`), KEY `idx_dept_active` (`is_active`),
    CONSTRAINT `fk_dept_smtp` FOREIGN KEY (`smtp_profile_id`) REFERENCES `smtp_profiles` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_dept_tpl` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_email_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type` VARCHAR(60) NOT NULL DEFAULT '',
    `document_id` INT UNSIGNED DEFAULT NULL,
    `document_no` VARCHAR(100) NOT NULL DEFAULT '',
    `sent_by_user_id` INT UNSIGNED DEFAULT NULL,
    `sent_by_name` VARCHAR(190) NOT NULL DEFAULT '',
    `department` VARCHAR(150) NOT NULL DEFAULT '',
    `smtp_profile_id` INT UNSIGNED DEFAULT NULL,
    `from_email` VARCHAR(190) NOT NULL DEFAULT '',
    `reply_to` VARCHAR(190) NOT NULL DEFAULT '',
    `to_email` VARCHAR(500) NOT NULL DEFAULT '',
    `cc` VARCHAR(500) NOT NULL DEFAULT '',
    `bcc` VARCHAR(500) NOT NULL DEFAULT '',
    `subject` VARCHAR(300) NOT NULL DEFAULT '',
    `body` MEDIUMTEXT DEFAULT NULL,
    `has_pdf` TINYINT(1) NOT NULL DEFAULT 0,
    `pdf_path` VARCHAR(255) NOT NULL DEFAULT '',
    `doc_link` VARCHAR(255) NOT NULL DEFAULT '',
    `status` ENUM('sent','failed') NOT NULL DEFAULT 'failed',
    `error_message` TEXT DEFAULT NULL,
    `message_id` VARCHAR(255) NOT NULL DEFAULT '',
    `resend_of_id` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_del_doc` (`document_type`,`document_id`),
    KEY `idx_del_created` (`created_at`), KEY `idx_del_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_access_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type` VARCHAR(60) NOT NULL DEFAULT '',
    `document_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `revoked_at` DATETIME DEFAULT NULL,
    `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_used_at` DATETIME DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_dat_hash` (`token_hash`),
    KEY `idx_dat_doc` (`document_type`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_approvals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type` VARCHAR(60) NOT NULL DEFAULT '',
    `document_id` INT UNSIGNED NOT NULL,
    `token_id` BIGINT UNSIGNED DEFAULT NULL,
    `decision` ENUM('agreed','disagreed') NOT NULL,
    `note` TEXT DEFAULT NULL,
    `authorized_name` VARCHAR(190) NOT NULL DEFAULT '',
    `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_dap_doc` (`document_type`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_email_preferences` (
    `user_id` INT UNSIGNED NOT NULL,
    `corporate_email` VARCHAR(190) NOT NULL DEFAULT '',
    `department` VARCHAR(150) NOT NULL DEFAULT '',
    `default_account_id` INT UNSIGNED DEFAULT NULL,
    `can_receive_replies` TINYINT(1) NOT NULL DEFAULT 1,
    `auto_cc` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_uep_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* =========================================================================
 |  FİNANS — Cari hareketler (ayrıntı: migrations/2026-07-finance-cari.sql)
 * ====================================================================== */
CREATE TABLE IF NOT EXISTS `cari_movements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED DEFAULT NULL,
    `customer_name` VARCHAR(190) NOT NULL DEFAULT '',
    `movement_date` DATE DEFAULT NULL,
    `doc_type` ENUM('collection','payment','invoice','manual') NOT NULL DEFAULT 'manual',
    `direction` ENUM('debit','credit') NOT NULL DEFAULT 'debit',
    `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'TRY',
    `method` VARCHAR(60) NOT NULL DEFAULT '',
    `reference` VARCHAR(100) NOT NULL DEFAULT '',
    `receipt_no` VARCHAR(40) NOT NULL DEFAULT '',
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_cari_customer` (`customer_id`), KEY `idx_cari_date` (`movement_date`),
    KEY `idx_cari_deleted` (`is_deleted`), KEY `idx_cari_receipt` (`receipt_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Belge dosyaları (üretilen PDF arşivi) — ayrıntı: migrations/2026-07-document-files.sql */
CREATE TABLE IF NOT EXISTS `document_files` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type` VARCHAR(60) NOT NULL DEFAULT '',
    `document_id` INT UNSIGNED DEFAULT NULL,
    `email_log_id` BIGINT UNSIGNED DEFAULT NULL,
    `file_path` VARCHAR(255) NOT NULL DEFAULT '',
    `original_name` VARCHAR(190) NOT NULL DEFAULT '',
    `mime` VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    `size` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY `idx_df_doc` (`document_type`,`document_id`), KEY `idx_df_log` (`email_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
