<?php
declare(strict_types=1);

/**
 * includes/service_maintenance.php
 * Teknik Servis — 6 Aylık (periyodik) Bakım Hatırlatma Sistemi veri katmanı.
 *
 * Kapanan / teslim edilen servis kayıtları için otomatik bakım takip planı
 * üretir; hatırlatma, iletişim (e-posta / WhatsApp), randevu ve servise
 * dönüştürme yaşam döngüsünü yönetir.
 *
 * İLKE: TOPLU/KONTROLSÜZ MESAJ YOK. WhatsApp yalnızca kullanıcı kontrollü
 * wa.me bağlantısı ile; ekranın açılması "gönderildi" anlamına gelmez.
 * İletişim izni olmayan müşterilere otomatik mesaj gönderilmez.
 *
 * Şema, kod tabanının "ensure_schema" öz-onarım kalıbını izler: tablolar
 * çalışma anında CREATE TABLE IF NOT EXISTS ile garanti edilir (migration
 * elle çalıştırılmasa da sistem çalışır). Aynı tanımlar
 * database/migrations/2026-07-service-maintenance.sql ve install.sql içinde
 * de bulunur.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/service.php';
require_once __DIR__ . '/leave.php'; // app_setting_get / app_setting_set

/* =========================================================================
 * 1) ŞEMA (öz-onarım)
 * ====================================================================== */

/**
 * Bakım takip tablolarını (ve müşteri izin kolonlarını) garanti eder.
 * İstek başına en fazla bir kez çalışır.
 */
function smaint_ensure_schema(): void
{
    static $done = false;
    if ($done) { return; }
    $done = true;

    try {
        $pdo = db();

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `service_maintenance_reminders` (
                `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `service_id`          INT UNSIGNED NOT NULL,
                `service_product_id`  INT UNSIGNED NOT NULL DEFAULT 0,
                `customer_id`         INT UNSIGNED NULL,
                `assigned_user_id`    INT UNSIGNED NULL,
                `source_delivery_date` DATE NOT NULL,
                `delivery_date`       DATE NULL,
                `maintenance_due_date` DATE NOT NULL,
                `period_key`          VARCHAR(10)  NOT NULL DEFAULT '6m',
                `period_days`         INT UNSIGNED NULL,
                `reminder_stage`      VARCHAR(10)  NOT NULL DEFAULT '',
                `status`              VARCHAR(30)  NOT NULL DEFAULT 'planned',
                `preferred_channel`   VARCHAR(20)  NULL,
                `email_consent`       TINYINT(1)   NOT NULL DEFAULT 0,
                `whatsapp_consent`    TINYINT(1)   NOT NULL DEFAULT 0,
                `phone_consent`       TINYINT(1)   NOT NULL DEFAULT 0,
                -- Servis kaydından anlık kopyalanan görünüm alanları (§2)
                `customer_name`       VARCHAR(190) NULL,
                `company_name`        VARCHAR(190) NULL,
                `contact_name`        VARCHAR(150) NULL,
                `phone`               VARCHAR(40)  NULL,
                `whatsapp`            VARCHAR(40)  NULL,
                `email`               VARCHAR(190) NULL,
                `brand_name`          VARCHAR(120) NULL,
                `device_model`        VARCHAR(150) NULL,
                `serial_no`           VARCHAR(120) NULL,
                `work_done`           TEXT         NULL,
                `description`         TEXT         NULL,
                -- İletişim / takip durumu
                `last_contact_at`     DATETIME NULL,
                `next_contact_at`     DATETIME NULL,
                `last_template_key`   VARCHAR(60) NULL,
                `last_message_at`     DATETIME NULL,
                `reminder_count`      INT UNSIGNED NOT NULL DEFAULT 0,
                `postpone_count`      INT UNSIGNED NOT NULL DEFAULT 0,
                -- Müşteri cevabı / işlem sonucu (§10)
                `response_result`     VARCHAR(40) NULL,
                `response_note`       VARCHAR(500) NULL,
                `response_at`         DATETIME NULL,
                -- Randevu (§10)
                `appointment_at`      DATETIME NULL,
                `appointment_service_type` VARCHAR(60) NULL,
                `appointment_location` VARCHAR(20) NULL,
                `appointment_technician_id` INT UNSIGNED NULL,
                `appointment_note`    VARCHAR(500) NULL,
                -- Servise dönüştürme (§11)
                `converted_service_id` INT UNSIGNED NULL,
                -- Yaşam döngüsü
                `completed_at`        DATETIME NULL,
                `completed_by`        INT UNSIGNED NULL,
                `cancelled_at`        DATETIME NULL,
                `cancelled_by`        INT UNSIGNED NULL,
                `cancel_reason`       VARCHAR(500) NULL,
                `created_by`          INT UNSIGNED NULL,
                `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`          DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                `deleted_at`          DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_smr_period` (`service_id`, `service_product_id`, `source_delivery_date`),
                KEY `idx_smr_service` (`service_id`),
                KEY `idx_smr_customer` (`customer_id`),
                KEY `idx_smr_assigned` (`assigned_user_id`),
                KEY `idx_smr_due` (`maintenance_due_date`),
                KEY `idx_smr_status` (`status`),
                KEY `idx_smr_stage` (`reminder_stage`),
                KEY `idx_smr_next` (`next_contact_at`),
                KEY `idx_smr_deleted` (`deleted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `service_maintenance_communications` (
                `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `reminder_id`    BIGINT UNSIGNED NOT NULL,
                `service_id`     INT UNSIGNED NULL,
                `channel`        VARCHAR(20)  NOT NULL DEFAULT 'email',
                `direction`      VARCHAR(10)  NOT NULL DEFAULT 'out',
                `to_phone`       VARCHAR(40)  NULL,
                `to_email`       VARCHAR(190) NULL,
                `subject`        VARCHAR(300) NULL,
                `body`           MEDIUMTEXT   NULL,
                `template_key`   VARCHAR(60)  NULL,
                `template_name`  VARCHAR(150) NULL,
                `from_account`   VARCHAR(190) NULL,
                `sent_by_user_id` INT UNSIGNED NULL,
                `sent_by_name`   VARCHAR(190) NULL,
                `wa_opened_at`   DATETIME NULL,
                `marked_sent_at` DATETIME NULL,
                `status`         VARCHAR(20)  NOT NULL DEFAULT 'pending',
                `customer_reply` VARCHAR(30)  NULL,
                `error_message`  TEXT         NULL,
                `note`           VARCHAR(500) NULL,
                `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_smc_reminder` (`reminder_id`),
                KEY `idx_smc_channel` (`channel`),
                KEY `idx_smc_status` (`status`),
                KEY `idx_smc_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `service_maintenance_status_history` (
                `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `reminder_id` BIGINT UNSIGNED NOT NULL,
                `old_status`  VARCHAR(30) NOT NULL DEFAULT '',
                `new_status`  VARCHAR(30) NOT NULL DEFAULT '',
                `note`        VARCHAR(500) NOT NULL DEFAULT '',
                `changed_by`  INT UNSIGNED NULL,
                `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_smsh_reminder` (`reminder_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `service_maintenance_postponements` (
                `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `reminder_id`    BIGINT UNSIGNED NOT NULL,
                `old_due_date`   DATE NULL,
                `new_due_date`   DATE NULL,
                `old_contact_at` DATETIME NULL,
                `new_contact_at` DATETIME NULL,
                `reason`         VARCHAR(500) NOT NULL DEFAULT '',
                `postponed_by`   INT UNSIGNED NULL,
                `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_smp_reminder` (`reminder_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `service_maintenance_settings` (
                `id`                    TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `is_active`             TINYINT(1) NOT NULL DEFAULT 1,
                `default_period_key`    VARCHAR(10) NOT NULL DEFAULT '6m',
                `default_period_days`   INT UNSIGNED NOT NULL DEFAULT 180,
                `dashboard_enabled`     TINYINT(1) NOT NULL DEFAULT 1,
                `email_enabled`         TINYINT(1) NOT NULL DEFAULT 1,
                `whatsapp_enabled`      TINYINT(1) NOT NULL DEFAULT 1,
                `auto_email_enabled`    TINYINT(1) NOT NULL DEFAULT 0,
                `manual_email_approval` TINYINT(1) NOT NULL DEFAULT 1,
                `stage_d30`             TINYINT(1) NOT NULL DEFAULT 1,
                `stage_d15`             TINYINT(1) NOT NULL DEFAULT 1,
                `stage_d7`              TINYINT(1) NOT NULL DEFAULT 1,
                `stage_due`             TINYINT(1) NOT NULL DEFAULT 1,
                `stage_o7`              TINYINT(1) NOT NULL DEFAULT 1,
                `stage_o30`             TINYINT(1) NOT NULL DEFAULT 1,
                `overdue_reremind`      TINYINT(1) NOT NULL DEFAULT 1,
                `max_reminders`         INT UNSIGNED NOT NULL DEFAULT 3,
                `default_assigned_user_id` INT UNSIGNED NULL,
                `email_template_id`     INT UNSIGNED NULL,
                `whatsapp_template_id`  INT UNSIGNED NULL,
                `consent_required`      TINYINT(1) NOT NULL DEFAULT 1,
                `sound_enabled`         TINYINT(1) NOT NULL DEFAULT 1,
                `whatsapp_api_enabled`  TINYINT(1) NOT NULL DEFAULT 0,
                `updated_by`            INT UNSIGNED NULL,
                `updated_at`            DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // Tekil ayar satırını garanti et.
        $pdo->exec("INSERT IGNORE INTO `service_maintenance_settings` (`id`) VALUES (1)");

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `service_maintenance_templates` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `channel`    VARCHAR(20)  NOT NULL DEFAULT 'email',
                `name`       VARCHAR(150) NOT NULL,
                `subject`    VARCHAR(300) NULL,
                `body`       MEDIUMTEXT   NOT NULL,
                `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
                `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_smt_channel` (`channel`),
                KEY `idx_smt_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `service_maintenance_cron_logs` (
                `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `run_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `upcoming_found` INT NOT NULL DEFAULT 0,
                `due_marked`     INT NOT NULL DEFAULT 0,
                `overdue_marked` INT NOT NULL DEFAULT 0,
                `notifications_created` INT NOT NULL DEFAULT 0,
                `emails_sent`    INT NOT NULL DEFAULT 0,
                `errors`         INT NOT NULL DEFAULT 0,
                `duration_ms`    INT NOT NULL DEFAULT 0,
                `detail`         TEXT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_smcl_run` (`run_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Müşteri kartına iletişim izni kolonları (§8) — idempotent.
        smaint_ensure_customer_consent_columns($pdo);
    } catch (Throwable $e) {
        log_error('smaint_ensure_schema: ' . $e->getMessage());
    }
}

/**
 * customers tablosuna bakım izin kolonlarını (yoksa) ekler.
 * MySQL 8 + MariaDB uyumlu: information_schema kontrolü ile.
 */
function smaint_ensure_customer_consent_columns(PDO $pdo): void
{
    $cols = [
        'maintenance_opt_in'   => "TINYINT(1) NOT NULL DEFAULT 0",
        'maint_email_consent'  => "TINYINT(1) NOT NULL DEFAULT 0",
        'maint_whatsapp_consent' => "TINYINT(1) NOT NULL DEFAULT 0",
        'maint_phone_consent'  => "TINYINT(1) NOT NULL DEFAULT 0",
        'maint_consent_at'     => "DATETIME NULL",
        'maint_consent_source' => "VARCHAR(60) NULL",
        'maint_consent_by_user_id' => "INT UNSIGNED NULL",
        'maint_consent_revoked_at' => "DATETIME NULL",
    ];
    try {
        $st = $pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'"
        );
        $st->execute();
        $have = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $have = array_flip($have);
        foreach ($cols as $name => $ddl) {
            if (isset($have[$name])) { continue; }
            $pdo->exec("ALTER TABLE `customers` ADD COLUMN `$name` $ddl");
        }
    } catch (Throwable $e) {
        // customers tablosu henüz yoksa (kurulum öncesi) sessizce geç.
        log_error('smaint_ensure_customer_consent_columns: ' . $e->getMessage());
    }
}

/* =========================================================================
 * 2) SABİTLER — durum / aşama / kanal / sonuç etiketleri (§3, §9, §10)
 * ====================================================================== */

/** Bakım takip durumları (§9). */
function smaint_statuses(): array
{
    return [
        'planned'           => 'Planlandı',
        'upcoming'          => 'Yaklaşıyor',
        'today'             => 'Bugün',
        'overdue'           => 'Gecikti',
        'to_call'           => 'Aranacak',
        'called'            => 'Arandı',
        'unreachable'       => 'Ulaşılamadı',
        'wa_prepared'       => 'WhatsApp Hazırlandı',
        'wa_sent'           => 'WhatsApp Gönderildi',
        'email_sent'        => 'E-posta Gönderildi',
        'awaiting_customer' => 'Müşteri Dönüşü Bekleniyor',
        'appointment_set'   => 'Randevu Oluşturuldu',
        'service_opened'    => 'Bakım Servisi Açıldı',
        'not_interested'    => 'İlgilenmiyor',
        'remind_later'      => 'Daha Sonra Hatırlat',
        'completed'         => 'Tamamlandı',
        'cancelled'         => 'İptal Edildi',
    ];
}

function smaint_status_label(string $key): string
{
    return smaint_statuses()[$key] ?? $key;
}

function smaint_status_class(string $key): string
{
    return match ($key) {
        'overdue'                             => 'badge-danger',
        'today', 'unreachable', 'remind_later' => 'badge-warning',
        'upcoming', 'to_call', 'called', 'awaiting_customer' => 'badge-info',
        'wa_sent', 'email_sent', 'appointment_set', 'service_opened', 'completed' => 'badge-success',
        default                               => 'badge-muted', // planned, wa_prepared, not_interested, cancelled
    };
}

/** Terminal (kapanmış) durumlar — aktif hatırlatma sayılmaz. */
function smaint_terminal_statuses(): array
{
    return ['completed', 'cancelled'];
}

/** Aşağıdaki durumlar aktif takip döngüsündedir (mükerrer engelleme için). */
function smaint_is_active_status(string $status): bool
{
    return !in_array($status, smaint_terminal_statuses(), true);
}

/**
 * Hatırlatma aşamaları (§3) — kronolojik sıralı.
 * key => ['label', gün ofseti, ayar anahtarı]. Negatif = bakımdan önce.
 */
function smaint_stages(): array
{
    return [
        'd30' => ['30 gün önce', -30, 'stage_d30'],
        'd15' => ['15 gün önce', -15, 'stage_d15'],
        'd7'  => ['7 gün önce',   -7, 'stage_d7'],
        'due' => ['Bakım günü',    0, 'stage_due'],
        'o7'  => ['7 gün gecikti', 7, 'stage_o7'],
        'o30' => ['30 gün gecikti', 30, 'stage_o30'],
    ];
}

/** Aşama sırası indeksi (ilerletme için). */
function smaint_stage_order(): array
{
    return array_keys(smaint_stages());
}

/** İletişim kanalları. */
function smaint_channels(): array
{
    return ['email' => 'E-posta', 'whatsapp' => 'WhatsApp', 'phone' => 'Telefon'];
}

/** Müşteri cevabı / işlem sonuçları (§10). */
function smaint_response_results(): array
{
    return [
        'wants_appointment' => 'Bakım randevusu istiyor',
        'call_later'        => 'Daha sonra aranmak istiyor',
        'wa_callback'       => 'WhatsApp\'tan dönüş yapacak',
        'wants_price'       => 'Fiyat bilgisi istiyor',
        'device_inactive'   => 'Cihaz aktif kullanılmıyor',
        'device_disposed'   => 'Cihaz elden çıkarıldı',
        'other_company'     => 'Başka firmayla çalışıyor',
        'not_interested'    => 'İlgilenmiyor',
        'unreachable'       => 'Ulaşılamadı',
        'wrong_number'      => 'Yanlış numara',
    ];
}

function smaint_response_label(string $key): string
{
    return smaint_response_results()[$key] ?? $key;
}

/** Randevu servis türleri. */
function smaint_appointment_service_types(): array
{
    return [
        'periodic' => 'Periyodik Bakım',
        'repair'   => 'Onarım',
        'check'    => 'Kontrol / Teşhis',
    ];
}

/** Randevu yeri. */
function smaint_appointment_locations(): array
{
    return ['onsite' => 'Yerinde Servis', 'store' => 'Mağaza Teslimi'];
}

/** Bakım periyodu seçenekleri (§1, §13). */
function smaint_period_options(): array
{
    return [
        '3m'     => '3 ay',
        '6m'     => '6 ay',
        '9m'     => '9 ay',
        '12m'    => '12 ay',
        'custom' => 'Özel gün sayısı',
    ];
}

/* =========================================================================
 * 3) AYARLAR (§13) — tekil satır tablosu
 * ====================================================================== */

/** Ayar varsayılanları. */
function smaint_settings_defaults(): array
{
    return [
        'is_active'             => 1,
        'default_period_key'    => '6m',
        'default_period_days'   => 180,
        'dashboard_enabled'     => 1,
        'email_enabled'         => 1,
        'whatsapp_enabled'      => 1,
        'auto_email_enabled'    => 0,
        'manual_email_approval' => 1,
        'stage_d30'             => 1,
        'stage_d15'             => 1,
        'stage_d7'              => 1,
        'stage_due'             => 1,
        'stage_o7'              => 1,
        'stage_o30'             => 1,
        'overdue_reremind'      => 1,
        'max_reminders'         => 3,
        'default_assigned_user_id' => null,
        'email_template_id'     => null,
        'whatsapp_template_id'  => null,
        'consent_required'      => 1,
        'sound_enabled'         => 1,
        'whatsapp_api_enabled'  => 0,
    ];
}

/** Ayarları okur (varsayılanlarla birleştirir). */
function smaint_settings(): array
{
    smaint_ensure_schema();
    $def = smaint_settings_defaults();
    try {
        $row = db()->query('SELECT * FROM service_maintenance_settings WHERE id = 1 LIMIT 1')->fetch();
        if (!$row) { return $def; }
        $out = $def;
        foreach ($def as $k => $v) {
            if (!array_key_exists($k, $row)) { continue; }
            if ($row[$k] === null) { $out[$k] = null; continue; }
            $out[$k] = is_int($v) ? (int) $row[$k] : (is_string($v) ? (string) $row[$k] : $row[$k]);
        }
        // Nullable tamsayı alanlar
        foreach (['default_assigned_user_id', 'email_template_id', 'whatsapp_template_id'] as $k) {
            $out[$k] = ($row[$k] ?? null) === null ? null : (int) $row[$k];
        }
        $out['default_period_days'] = (int) ($row['default_period_days'] ?? 180);
        $out['max_reminders']       = (int) ($row['max_reminders'] ?? 3);
        return $out;
    } catch (Throwable $e) {
        log_error('smaint_settings: ' . $e->getMessage());
        return $def;
    }
}

/** Ayarları kaydeder. */
function smaint_settings_save(array $in, ?int $userId): bool
{
    smaint_ensure_schema();
    $def = smaint_settings_defaults();

    $periodKey = (string) ($in['default_period_key'] ?? '6m');
    if (!isset(smaint_period_options()[$periodKey])) { $periodKey = '6m'; }
    $periodDays = max(1, (int) ($in['default_period_days'] ?? 180));
    $maxRem     = max(0, (int) ($in['max_reminders'] ?? 3));

    $boolKeys = [
        'is_active', 'dashboard_enabled', 'email_enabled', 'whatsapp_enabled',
        'auto_email_enabled', 'manual_email_approval', 'stage_d30', 'stage_d15',
        'stage_d7', 'stage_due', 'stage_o7', 'stage_o30', 'overdue_reremind',
        'consent_required', 'sound_enabled', 'whatsapp_api_enabled',
    ];
    $set = [];
    foreach ($boolKeys as $k) { $set[$k] = !empty($in[$k]) ? 1 : 0; }

    // Standart wa.me sisteminde otomatik WhatsApp gönderimi yapılamaz;
    // yalnızca resmi WhatsApp Business API tanımlıysa (whatsapp_api_enabled)
    // otomatik gönderim etkin olabilir (§7, §13). Burada saklanan sadece izin.
    $assigned = (int) ($in['default_assigned_user_id'] ?? 0);
    $emailTpl = (int) ($in['email_template_id'] ?? 0);
    $waTpl    = (int) ($in['whatsapp_template_id'] ?? 0);

    try {
        $sql = 'UPDATE service_maintenance_settings SET
                    is_active=:is_active, default_period_key=:pkey, default_period_days=:pdays,
                    dashboard_enabled=:dashboard_enabled, email_enabled=:email_enabled,
                    whatsapp_enabled=:whatsapp_enabled, auto_email_enabled=:auto_email_enabled,
                    manual_email_approval=:manual_email_approval,
                    stage_d30=:stage_d30, stage_d15=:stage_d15, stage_d7=:stage_d7,
                    stage_due=:stage_due, stage_o7=:stage_o7, stage_o30=:stage_o30,
                    overdue_reremind=:overdue_reremind, max_reminders=:maxrem,
                    default_assigned_user_id=:assigned, email_template_id=:etpl,
                    whatsapp_template_id=:wtpl, consent_required=:consent_required,
                    sound_enabled=:sound_enabled, whatsapp_api_enabled=:whatsapp_api_enabled,
                    updated_by=:uby
                WHERE id = 1';
        $params = [
            ':pkey' => $periodKey, ':pdays' => $periodDays, ':maxrem' => $maxRem,
            ':assigned' => $assigned > 0 ? $assigned : null,
            ':etpl' => $emailTpl > 0 ? $emailTpl : null,
            ':wtpl' => $waTpl > 0 ? $waTpl : null,
            ':uby' => $userId,
        ];
        foreach ($set as $k => $v) { $params[':' . $k] = $v; }
        db()->prepare($sql)->execute($params);
        return true;
    } catch (Throwable $e) {
        log_error('smaint_settings_save: ' . $e->getMessage());
        return false;
    }
}

/** Sistem aktif mi? (ayar) */
function smaint_is_enabled(): bool
{
    return (int) (smaint_settings()['is_active'] ?? 1) === 1;
}

/* =========================================================================
 * 4) BAKIM TARİHİ HESABI (§1)
 * ====================================================================== */

/**
 * Teslim/kapanış tarihinden bakım tarihini hesaplar.
 * $baseDate: 'Y-m-d' veya 'Y-m-d H:i:s'. Ayar periyoduna göre +ay / +gün.
 */
function smaint_calc_due_date(string $baseDate, ?array $s = null): string
{
    $s = $s ?? smaint_settings();
    $base = substr(trim($baseDate), 0, 10);
    try {
        $dt = new DateTime($base !== '' ? $base : 'today');
    } catch (Throwable $e) {
        $dt = new DateTime('today');
    }
    $key = (string) ($s['default_period_key'] ?? '6m');
    if ($key === 'custom') {
        $days = max(1, (int) ($s['default_period_days'] ?? 180));
        $dt->modify('+' . $days . ' days');
    } else {
        $months = ['3m' => 3, '6m' => 6, '9m' => 9, '12m' => 12][$key] ?? 6;
        $dt->modify('+' . $months . ' months');
    }
    return $dt->format('Y-m-d');
}

/* =========================================================================
 * 5) YETKİ SARMALAYICILARI (§17)
 * ====================================================================== */

function can_maint_view(): bool          { return can('maintenance.view') || can('maintenance.view_own') || can('maintenance.view_all') || can('maintenance') || can('service'); }
function can_maint_view_all(): bool       { return can('maintenance.view_all') || can('service'); }
function can_maint_create(): bool         { return can('maintenance.create') || can('service.create') || can('service'); }
function can_maint_edit(): bool           { return can('maintenance.edit') || can('service.edit') || can('service'); }
function can_maint_cancel(): bool         { return can('maintenance.cancel') || can('service'); }
function can_maint_message(): bool        { return can('maintenance.message') || can('service'); }
function can_maint_email(): bool          { return can('maintenance.email') || can('service.mail') || can('service'); }
function can_maint_whatsapp(): bool       { return can('maintenance.whatsapp') || can('service.whatsapp') || can('service'); }
function can_maint_appointment(): bool    { return can('maintenance.appointment') || can('service'); }
function can_maint_convert(): bool        { return can('maintenance.convert') || can('service.create') || can('service'); }
function can_maint_templates(): bool      { return can('maintenance.templates') || can('settings'); }
function can_maint_settings(): bool       { return can('maintenance.settings') || can('settings'); }
function can_maint_reports(): bool        { return can('maintenance.reports') || can('service') || can('reports'); }
function can_maint_assign(): bool         { return can('maintenance.assign') || can('service'); }

/* =========================================================================
 * 6) HATIRLATMA KAYITLARI — okuma / durum / geçmiş
 * ====================================================================== */

/** Tek bakım hatırlatması. */
function smaint_reminder_get(int $id): ?array
{
    if ($id <= 0) { return null; }
    smaint_ensure_schema();
    try {
        $st = db()->prepare(
            'SELECT r.*, s.reference_code, s.status AS service_status,
                    u.full_name AS assignee_name
             FROM service_maintenance_reminders r
             LEFT JOIN service_records s ON s.id = r.service_id
             LEFT JOIN users u ON u.id = r.assigned_user_id
             WHERE r.id = :id AND r.deleted_at IS NULL LIMIT 1'
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('smaint_reminder_get: ' . $e->getMessage());
        return null;
    }
}

/** Bir servis kaydına ait bakım hatırlatmaları. */
function smaint_reminders_for_service(int $serviceId): array
{
    if ($serviceId <= 0) { return []; }
    smaint_ensure_schema();
    try {
        $st = db()->prepare(
            'SELECT * FROM service_maintenance_reminders
             WHERE service_id = :s AND deleted_at IS NULL
             ORDER BY id DESC'
        );
        $st->execute([':s' => $serviceId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('smaint_reminders_for_service: ' . $e->getMessage());
        return [];
    }
}

/** Servise ait aktif (kapanmamış) bakım hatırlatması var mı? */
function smaint_active_reminder_for_service(int $serviceId): ?array
{
    if ($serviceId <= 0) { return null; }
    smaint_ensure_schema();
    try {
        $st = db()->prepare(
            "SELECT * FROM service_maintenance_reminders
             WHERE service_id = :s AND deleted_at IS NULL
               AND status NOT IN ('completed','cancelled','service_opened')
             ORDER BY id DESC LIMIT 1"
        );
        $st->execute([':s' => $serviceId]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        log_error('smaint_active_reminder_for_service: ' . $e->getMessage());
        return null;
    }
}

/** Durum geçmişine kayıt ekler (§9). */
function smaint_status_history_add(int $reminderId, string $old, string $new, string $note, ?int $userId): void
{
    try {
        db()->prepare(
            'INSERT INTO service_maintenance_status_history (reminder_id, old_status, new_status, note, changed_by)
             VALUES (:r,:o,:n,:note,:by)'
        )->execute([
            ':r' => $reminderId, ':o' => $old, ':n' => $new,
            ':note' => mb_substr($note, 0, 500), ':by' => $userId,
        ]);
    } catch (Throwable $e) {
        log_error('smaint_status_history_add: ' . $e->getMessage());
    }
}

/** Durum geçmişini (kullanıcı adıyla) getirir. */
function smaint_status_history(int $reminderId, int $limit = 100): array
{
    smaint_ensure_schema();
    try {
        $st = db()->prepare(
            'SELECT h.*, u.full_name AS by_name
             FROM service_maintenance_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.reminder_id = :r ORDER BY h.id DESC LIMIT ' . max(1, min(500, $limit))
        );
        $st->execute([':r' => $reminderId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        log_error('smaint_status_history: ' . $e->getMessage());
        return [];
    }
}

/**
 * Genel durum değiştirici (tarihçe kaydeder). Terminal alanları (tamamlandı/iptal)
 * özel fonksiyonlar (smaint_complete_reminder / smaint_cancel_reminder) damgalar.
 * Bildirimin okunması bu fonksiyonu tetiklemez (§18: okuma ≠ tamamlama).
 */
function smaint_set_status(int $id, string $newStatus, ?int $userId, string $note = ''): bool
{
    if (!isset(smaint_statuses()[$newStatus])) { return false; }
    $r = smaint_reminder_get($id);
    if (!$r) { return false; }
    $old = (string) $r['status'];
    if ($old === $newStatus && $note === '') { return true; }
    try {
        db()->prepare('UPDATE service_maintenance_reminders SET status = :s WHERE id = :id AND deleted_at IS NULL')
            ->execute([':s' => $newStatus, ':id' => $id]);
        if ($old !== $newStatus) {
            smaint_status_history_add($id, $old, $newStatus, $note, $userId);
            log_activity('maintenance_status', 'maintenance', $id, (string) ($r['reference_code'] ?? ''), 'success',
                smaint_status_label($old) . ' → ' . smaint_status_label($newStatus) . ($note !== '' ? ' · ' . $note : ''));
        }
        return true;
    } catch (Throwable $e) {
        log_error('smaint_set_status: ' . $e->getMessage());
        return false;
    }
}

/** Hatırlatmayı iptal eder (pasife alır) — tarihçe + audit. */
function smaint_cancel_reminder(int $id, ?int $userId, string $reason = ''): bool
{
    $r = smaint_reminder_get($id);
    if (!$r) { return false; }
    if (in_array((string) $r['status'], ['completed', 'cancelled'], true)) { return true; }
    try {
        db()->prepare(
            'UPDATE service_maintenance_reminders
             SET status = \'cancelled\', cancelled_at = NOW(), cancelled_by = :by, cancel_reason = :reason
             WHERE id = :id AND deleted_at IS NULL'
        )->execute([':by' => $userId, ':reason' => mb_substr($reason, 0, 500), ':id' => $id]);
        smaint_status_history_add($id, (string) $r['status'], 'cancelled', $reason, $userId);
        log_activity('maintenance_cancel', 'maintenance', $id, (string) ($r['reference_code'] ?? ''), 'success', $reason);
        if (function_exists('smaint_dismiss_notifications')) { smaint_dismiss_notifications($id); }
        return true;
    } catch (Throwable $e) {
        log_error('smaint_cancel_reminder: ' . $e->getMessage());
        return false;
    }
}

/* =========================================================================
 * 7) SERVİS OLAYLARINDAN BAKIM PLANI ÜRETİMİ (§1, §18)
 * ====================================================================== */

/** Servis kaydından görünüm alanı anlık kopyası (§2). */
function smaint_build_snapshot_from_service(array $rec): array
{
    $brand = trim((string) ($rec['brand_name'] ?? ''));
    $phone = trim((string) ($rec['customer_phone'] ?? ''));
    $email = trim((string) ($rec['customer_email'] ?? ''));
    $work  = trim((string) ($rec['final_note'] ?? ''));
    if ($work === '') { $work = trim((string) ($rec['problem_description'] ?? '')); }
    return [
        'customer_name' => (string) ($rec['customer_name'] ?? ''),
        'company_name'  => (string) ($rec['customer_name'] ?? ''),
        'contact_name'  => null,
        'phone'         => $phone !== '' ? $phone : null,
        'whatsapp'      => $phone !== '' ? $phone : null,
        'email'         => $email !== '' ? $email : null,
        'brand_name'    => $brand !== '' ? $brand : null,
        'device_model'  => ($rec['device_model'] ?? '') !== '' ? (string) $rec['device_model'] : null,
        'serial_no'     => ($rec['serial_no'] ?? '') !== '' ? (string) $rec['serial_no'] : null,
        'work_done'     => $work !== '' ? $work : null,
        'description'   => null,
    ];
}

/**
 * Servis kaydı iletişim bilgisinden CRM müşterisini ve izin durumunu çözer.
 * @return array{id:?int,email_consent:int,whatsapp_consent:int,phone_consent:int}
 */
function smaint_resolve_customer(array $rec): array
{
    $out = ['id' => null, 'email_consent' => 0, 'whatsapp_consent' => 0, 'phone_consent' => 0];
    $email = trim((string) ($rec['customer_email'] ?? ''));
    $phone = trim((string) ($rec['customer_phone'] ?? ''));
    if ($email === '' && $phone === '') { return $out; }
    try { smaint_ensure_customer_consent_columns(db()); } catch (Throwable $e) { /* yoksay */ }
    try {
        $c = null;
        if ($email !== '') {
            $st = db()->prepare('SELECT * FROM customers WHERE is_deleted = 0 AND email = :e ORDER BY id ASC LIMIT 1');
            $st->execute([':e' => $email]);
            $c = $st->fetch() ?: null;
        }
        if (!$c && $phone !== '') {
            $st = db()->prepare('SELECT * FROM customers WHERE is_deleted = 0 AND (phone = :p OR whatsapp = :p) ORDER BY id ASC LIMIT 1');
            $st->execute([':p' => $phone]);
            $c = $st->fetch() ?: null;
        }
        if ($c) {
            $out['id'] = (int) $c['id'];
            $revoked  = !empty($c['maint_consent_revoked_at']);
            $out['email_consent']    = (!$revoked && (int) ($c['maint_email_consent'] ?? 0) === 1) ? 1 : 0;
            $out['whatsapp_consent'] = (!$revoked && (int) ($c['maint_whatsapp_consent'] ?? 0) === 1) ? 1 : 0;
            $out['phone_consent']    = (!$revoked && (int) ($c['maint_phone_consent'] ?? 0) === 1) ? 1 : 0;
        }
    } catch (Throwable $e) {
        log_error('smaint_resolve_customer: ' . $e->getMessage());
    }
    return $out;
}

/**
 * Servis durumu değiştiğinde bakım planını senkronlar.
 * - delivered → bakım planı oluştur/yenile
 * - closed (teslim tarihi varsa) → oluştur/yenile; teslim edilmemişse başlatma (§18)
 * - teslim/kapalı iken geri açılırsa → aktif plan pasife alınır (§1)
 * Servis akışını asla kırmamalı; çağrı yeri try/catch ile sarılıdır.
 */
function smaint_sync_service_reminder(int $serviceId, string $oldStatus, string $newStatus, ?int $userId = null): void
{
    smaint_ensure_schema();
    if (!smaint_is_enabled()) { return; }

    $rec = get_service_record($serviceId);
    if (!$rec || (int) ($rec['is_deleted'] ?? 0) === 1) { return; }

    $handover = ['delivered', 'closed'];
    $isHandover  = in_array($newStatus, $handover, true);
    $wasHandover = in_array($oldStatus, $handover, true);

    if ($isHandover) {
        $delivered = trim((string) ($rec['delivered_to_customer_at'] ?? ''));
        $closed    = trim((string) ($rec['closed_at'] ?? ''));
        // §18: yalnızca kapatılmış ancak teslim edilmemişse bakım başlamaz.
        if ($newStatus === 'closed' && $delivered === '') { return; }
        $baseRaw = $delivered !== '' ? $delivered : ($closed !== '' ? $closed : date('Y-m-d'));
        smaint_create_or_refresh_for_service($rec, $baseRaw, $userId);
        return;
    }

    if ($wasHandover && !$isHandover) {
        smaint_passivate_active_for_service($serviceId, $userId,
            'Servis kaydı yeniden açıldı; bakım planı pasife alındı.');
    }
}

/** Aktif bakım planlarını pasife (iptal) alır — opsiyonel dönem hariç tutma. */
function smaint_passivate_active_for_service(int $serviceId, ?int $userId, string $reason, ?string $exceptDate = null): int
{
    try {
        $sql = "SELECT id FROM service_maintenance_reminders
                WHERE service_id = :s AND deleted_at IS NULL
                  AND status NOT IN ('completed','cancelled','service_opened')";
        $params = [':s' => $serviceId];
        if ($exceptDate !== null) { $sql .= ' AND source_delivery_date <> :d'; $params[':d'] = $exceptDate; }
        $st = db()->prepare($sql);
        $st->execute($params);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        foreach ($ids as $rid) { smaint_cancel_reminder($rid, $userId, $reason); }
        return count($ids);
    } catch (Throwable $e) {
        log_error('smaint_passivate_active_for_service: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Bir servis + teslim dönemi için bakım planı oluşturur ya da (aynı dönem varsa)
 * yeniden hesaplar. Aynı anda tek aktif plan kuralını uygular.
 * @return int reminder id (0 = hata)
 */
function smaint_create_or_refresh_for_service(array $rec, string $baseRaw, ?int $userId): int
{
    $serviceId = (int) ($rec['id'] ?? 0);
    if ($serviceId <= 0) { return 0; }
    $base = substr(trim($baseRaw), 0, 10);
    if ($base === '') { $base = date('Y-m-d'); }

    $s    = smaint_settings();
    $due  = smaint_calc_due_date($base, $s);
    $snap = smaint_build_snapshot_from_service($rec);
    $cust = smaint_resolve_customer($rec);

    $assigned = (int) ($s['default_assigned_user_id'] ?? 0);
    if ($assigned <= 0) { $assigned = (int) ($rec['received_by_user_id'] ?? 0); }
    $assigned = $assigned > 0 ? $assigned : null;

    $periodDays = ($s['default_period_key'] === 'custom') ? (int) $s['default_period_days'] : null;

    try {
        // Aynı servisin başka dönemdeki aktif planlarını pasife al (§1: tek aktif plan).
        smaint_passivate_active_for_service($serviceId, $userId,
            'Yeni teslim tarihine göre bakım planı yenilendi.', $base);

        // Bu dönem için mevcut kayıt?
        $st = db()->prepare(
            'SELECT id, status FROM service_maintenance_reminders
             WHERE service_id = :s AND service_product_id = 0 AND source_delivery_date = :d LIMIT 1'
        );
        $st->execute([':s' => $serviceId, ':d' => $base]);
        $ex = $st->fetch();

        if ($ex) {
            $exId = (int) $ex['id'];
            // Bitmiş döngüleri diriltme.
            if (in_array((string) $ex['status'], ['completed', 'service_opened'], true)) {
                return $exId;
            }
            db()->prepare(
                'UPDATE service_maintenance_reminders SET
                    customer_id=:cid, assigned_user_id=:au, delivery_date=:dd, maintenance_due_date=:due,
                    period_key=:pk, period_days=:pd, status=\'planned\', reminder_stage=\'\',
                    cancelled_at=NULL, cancelled_by=NULL, cancel_reason=NULL,
                    email_consent=:ec, whatsapp_consent=:wc, phone_consent=:pc,
                    customer_name=:cn, company_name=:comp, contact_name=:ctn, phone=:ph, whatsapp=:wa,
                    email=:em, brand_name=:bn, device_model=:dm, serial_no=:sn, work_done=:wd, description=:desc
                 WHERE id=:id AND deleted_at IS NULL'
            )->execute([
                ':cid' => $cust['id'], ':au' => $assigned, ':dd' => $base, ':due' => $due,
                ':pk' => $s['default_period_key'], ':pd' => $periodDays,
                ':ec' => $cust['email_consent'], ':wc' => $cust['whatsapp_consent'], ':pc' => $cust['phone_consent'],
                ':cn' => $snap['customer_name'], ':comp' => $snap['company_name'], ':ctn' => $snap['contact_name'],
                ':ph' => $snap['phone'], ':wa' => $snap['whatsapp'], ':em' => $snap['email'],
                ':bn' => $snap['brand_name'], ':dm' => $snap['device_model'], ':sn' => $snap['serial_no'],
                ':wd' => $snap['work_done'], ':desc' => $snap['description'], ':id' => $exId,
            ]);
            smaint_status_history_add($exId, (string) $ex['status'], 'planned', 'Yeni teslim tarihine göre yeniden hesaplandı.', $userId);
            log_activity('maintenance_refresh', 'maintenance', $exId, (string) ($rec['reference_code'] ?? ''), 'success',
                'Bakım tarihi: ' . $due);
            return $exId;
        }

        db()->prepare(
            'INSERT INTO service_maintenance_reminders
                (service_id, service_product_id, customer_id, assigned_user_id, source_delivery_date,
                 delivery_date, maintenance_due_date, period_key, period_days, status,
                 email_consent, whatsapp_consent, phone_consent,
                 customer_name, company_name, contact_name, phone, whatsapp, email,
                 brand_name, device_model, serial_no, work_done, description, created_by)
             VALUES
                (:s,0,:cid,:au,:src,:dd,:due,:pk,:pd,\'planned\',
                 :ec,:wc,:pc,:cn,:comp,:ctn,:ph,:wa,:em,:bn,:dm,:sn,:wd,:desc,:by)'
        )->execute([
            ':s' => $serviceId, ':cid' => $cust['id'], ':au' => $assigned, ':src' => $base,
            ':dd' => $base, ':due' => $due, ':pk' => $s['default_period_key'], ':pd' => $periodDays,
            ':ec' => $cust['email_consent'], ':wc' => $cust['whatsapp_consent'], ':pc' => $cust['phone_consent'],
            ':cn' => $snap['customer_name'], ':comp' => $snap['company_name'], ':ctn' => $snap['contact_name'],
            ':ph' => $snap['phone'], ':wa' => $snap['whatsapp'], ':em' => $snap['email'],
            ':bn' => $snap['brand_name'], ':dm' => $snap['device_model'], ':sn' => $snap['serial_no'],
            ':wd' => $snap['work_done'], ':desc' => $snap['description'], ':by' => $userId,
        ]);
        $id = (int) db()->lastInsertId();
        smaint_status_history_add($id, '', 'planned', 'Bakım planı oluşturuldu (teslim: ' . $base . ', bakım: ' . $due . ').', $userId);
        log_activity('maintenance_create', 'maintenance', $id, (string) ($rec['reference_code'] ?? ''), 'success',
            'Bakım tarihi: ' . $due);
        return $id;
    } catch (Throwable $e) {
        log_error('smaint_create_or_refresh_for_service: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Servis kaydı silindiğinde bakım planlarını pasife alır (§18: silinen servis
 * için yeni bildirim üretilmez). service.php delete_service_record içinden
 * güvenli şekilde çağrılır.
 */
function smaint_on_service_deleted(int $serviceId, ?int $userId): void
{
    smaint_ensure_schema();
    smaint_passivate_active_for_service($serviceId, $userId, 'Servis kaydı silindi; bakım planı pasife alındı.');
}

/* =========================================================================
 * 8) CRON — durum bakımı + aşamalı bildirim üretimi (§3, §12)
 * ====================================================================== */

/** Bakım tarihi geçmiş (sistem durumundaki) kayıtları "Gecikti" işaretler. */
function smaint_mark_overdue(): int
{
    try {
        $st = db()->prepare(
            "UPDATE service_maintenance_reminders SET status = 'overdue'
             WHERE deleted_at IS NULL AND status IN ('planned','upcoming','today')
               AND maintenance_due_date < CURDATE()"
        );
        $st->execute();
        return $st->rowCount();
    } catch (Throwable $e) { log_error('smaint_mark_overdue: ' . $e->getMessage()); return 0; }
}

/** Bakım tarihi bugün olan kayıtları "Bugün" işaretler. */
function smaint_mark_today(): int
{
    try {
        $st = db()->prepare(
            "UPDATE service_maintenance_reminders SET status = 'today'
             WHERE deleted_at IS NULL AND status IN ('planned','upcoming')
               AND maintenance_due_date = CURDATE()"
        );
        $st->execute();
        return $st->rowCount();
    } catch (Throwable $e) { log_error('smaint_mark_today: ' . $e->getMessage()); return 0; }
}

/** Bakım tarihi 30 gün içinde olan planlı kayıtları "Yaklaşıyor" işaretler. */
function smaint_mark_upcoming(int $windowDays = 30): int
{
    try {
        $st = db()->prepare(
            "UPDATE service_maintenance_reminders SET status = 'upcoming'
             WHERE deleted_at IS NULL AND status = 'planned'
               AND maintenance_due_date > CURDATE()
               AND maintenance_due_date <= (CURDATE() + INTERVAL :w DAY)"
        );
        $st->bindValue(':w', max(1, $windowDays), PDO::PARAM_INT);
        $st->execute();
        return $st->rowCount();
    } catch (Throwable $e) { log_error('smaint_mark_upcoming: ' . $e->getMessage()); return 0; }
}

/** Bildirim üretmeye aday (terminal olmayan, servisi silinmemiş) kayıtlar. */
function smaint_reminders_for_stage_notice(int $limit = 2000): array
{
    smaint_ensure_schema();
    try {
        $st = db()->prepare(
            "SELECT r.id, r.assigned_user_id, r.maintenance_due_date, r.reminder_stage, r.status,
                    r.customer_name, r.company_name, r.brand_name, r.device_model, r.serial_no,
                    u.is_active AS assignee_active
             FROM service_maintenance_reminders r
             INNER JOIN service_records s ON s.id = r.service_id AND s.is_deleted = 0
             LEFT JOIN users u ON u.id = r.assigned_user_id
             WHERE r.deleted_at IS NULL
               AND r.status NOT IN ('completed','cancelled','service_opened','not_interested')
               AND r.maintenance_due_date <= (CURDATE() + INTERVAL 30 DAY)
             ORDER BY r.maintenance_due_date ASC
             LIMIT " . max(1, min(5000, $limit))
        );
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('smaint_reminders_for_stage_notice: ' . $e->getMessage()); return []; }
}

/** Bir kaydın bugüne göre ulaştığı en son ETKİN aşamayı döndürür (yoksa null). */
function smaint_applicable_stage(string $dueDate, array $s, ?string $today = null): ?string
{
    $due = substr(trim($dueDate), 0, 10);
    if ($due === '') { return null; }
    try {
        $d = new DateTime($due);
        $t = new DateTime($today ?? date('Y-m-d'));
    } catch (Throwable $e) { return null; }
    $applicable = null;
    foreach (smaint_stages() as $key => [$label, $offset, $flag]) {
        if ((int) ($s[$flag] ?? 1) !== 1) { continue; }
        $trigger = (clone $d)->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
        if ($t >= $trigger) { $applicable = $key; }
    }
    return $applicable;
}

/** reminder_stage ilerletir (mükerrer bildirim engeli). */
function smaint_set_reminder_stage(int $id, string $stage): void
{
    try {
        db()->prepare('UPDATE service_maintenance_reminders SET reminder_stage = :s WHERE id = :id')
            ->execute([':s' => $stage, ':id' => $id]);
    } catch (Throwable $e) { log_error('smaint_set_reminder_stage: ' . $e->getMessage()); }
}

/** Aşamaya göre bildirim başlık/mesaj/tür üretir. @return array{0:string,1:string,2:string} */
function smaint_stage_message(array $r, string $stage): array
{
    $who = trim((string) ($r['company_name'] ?? '')) !== ''
        ? (string) $r['company_name'] : (string) ($r['customer_name'] ?? 'Müşteri');
    $device = trim(((string) ($r['brand_name'] ?? '')) . ' ' . ((string) ($r['device_model'] ?? '')));
    if ($device === '') { $device = 'cihaz'; }
    $due = substr((string) ($r['maintenance_due_date'] ?? ''), 0, 10);

    if (in_array($stage, ['o7', 'o30'], true)) {
        $days = 0;
        try { $days = (int) (new DateTime('today'))->diff(new DateTime($due))->format('%r%a'); } catch (Throwable $e) {}
        $late = abs($days);
        return [
            'Geciken bakım',
            $who . ' — ' . $device . ' cihazının bakım hatırlatması ' . $late . ' gündür gecikmiş.',
            'service_maintenance_overdue',
        ];
    }
    if ($stage === 'due') {
        return [
            'Bakım zamanı geldi',
            $who . ' firmasının ' . $device . ' cihazı için bakım zamanı geldi (' . $due . ').',
            'service_maintenance_due',
        ];
    }
    // d30 / d15 / d7
    return [
        'Yaklaşan bakım',
        $who . ' — ' . $device . ' cihazının periyodik bakım tarihi yaklaşıyor (' . $due . ').',
        'service_maintenance_soon',
    ];
}

/** Yarın bakımı olan kayıtları kullanıcıya göre gruplar (günlük özet). */
function smaint_due_tomorrow_by_user(): array
{
    smaint_ensure_schema();
    try {
        $st = db()->query(
            "SELECT r.assigned_user_id AS uid, COUNT(*) AS c
             FROM service_maintenance_reminders r
             INNER JOIN service_records s ON s.id = r.service_id AND s.is_deleted = 0
             WHERE r.deleted_at IS NULL AND r.assigned_user_id IS NOT NULL
               AND r.status NOT IN ('completed','cancelled','service_opened','not_interested')
               AND r.maintenance_due_date = (CURDATE() + INTERVAL 1 DAY)
             GROUP BY r.assigned_user_id"
        );
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('smaint_due_tomorrow_by_user: ' . $e->getMessage()); return []; }
}

/** Bakım kaydı görüntüleme URL'i (panel içi). */
function smaint_view_url(int $reminderId): string
{
    return 'modules/maintenance/view.php?id=' . $reminderId;
}

/** Cron çalışma özetini service_maintenance_cron_logs tablosuna yazar. */
function smaint_cron_log_write(array $d): void
{
    try {
        db()->prepare(
            'INSERT INTO service_maintenance_cron_logs
                (upcoming_found, due_marked, overdue_marked, notifications_created, emails_sent, errors, duration_ms, detail)
             VALUES (:uf,:dm,:om,:nc,:es,:er,:du,:de)'
        )->execute([
            ':uf' => (int) ($d['upcoming_found'] ?? 0),
            ':dm' => (int) ($d['due_marked'] ?? 0),
            ':om' => (int) ($d['overdue_marked'] ?? 0),
            ':nc' => (int) ($d['notifications_created'] ?? 0),
            ':es' => (int) ($d['emails_sent'] ?? 0),
            ':er' => (int) ($d['errors'] ?? 0),
            ':du' => (int) ($d['duration_ms'] ?? 0),
            ':de' => isset($d['detail']) ? mb_substr((string) $d['detail'], 0, 2000) : null,
        ]);
    } catch (Throwable $e) {
        log_error('smaint_cron_log_write: ' . $e->getMessage());
    }
}
