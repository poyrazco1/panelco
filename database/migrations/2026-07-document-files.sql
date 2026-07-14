-- =========================================================================
-- BELGE DOSYALARI (üretilen PDF arşivi). Fresh kurulum install.sql içinde de var.
-- Dosyalar uploads/documents altında (web'den erişimi .htaccess ile engelli);
-- yalnızca yetki denetimli modules/documents/file.php ile sunulur.
-- =========================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `document_files` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type`  VARCHAR(60)  NOT NULL DEFAULT '',
    `document_id`    INT UNSIGNED DEFAULT NULL,
    `email_log_id`   BIGINT UNSIGNED DEFAULT NULL,
    `file_path`      VARCHAR(255) NOT NULL DEFAULT '',
    `original_name`  VARCHAR(190) NOT NULL DEFAULT '',
    `mime`           VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    `size`           INT UNSIGNED NOT NULL DEFAULT 0,
    `status`         VARCHAR(20)  NOT NULL DEFAULT 'active',
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_df_doc` (`document_type`, `document_id`),
    KEY `idx_df_log` (`email_log_id`),
    CONSTRAINT `fk_df_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
