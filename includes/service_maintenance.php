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
