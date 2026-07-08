-- =========================================================================
-- FORM MERKEZİ — iç/dış formlar, gönderimler, onay, dış token, dosya ekleri.
-- Fresh kurulum için aynı tanımlar install.sql içindedir. Bu migration mevcut
-- kurulumları güvenle yükseltir (idempotent).
-- =========================================================================
SET NAMES utf8mb4;

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
