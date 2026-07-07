<?php
declare(strict_types=1);

/**
 * includes/notifications.php
 * İK Bildirimleri / Personel Hatırlatmaları motoru.
 * İzin, doğum günü, çalışma yıl dönümü bildirimleri + panel/mail/WhatsApp kutlama + log.
 * KVKK: izin açıklamaları/özel notlar yalnızca yetkili (İK/Yönetici) kullanıcıya gösterilir.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/personnel.php';
require_once __DIR__ . '/leave.php';
require_once __DIR__ . '/service.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/mail.php';

/**
 * Bildirim şemasını kendini onarır (idempotent).
 *
 * İK/Bildirim güncellemesi eksik içe aktarıldığında notification_settings,
 * notification_templates, user_notifications ve notification_logs tabloları
 * eksik olabilir. Bu durumda Bildirim Merkezi boş kalır, doğum günü/yıl
 * dönümü bildirimleri üretilemez ve mail/WhatsApp kutlama şablonları
 * bulunamaz. Bu fonksiyon eksik tabloları oluşturur ve varsayılan ayarlar
 * ile kutlama şablonlarını güvenle ekler (CREATE TABLE IF NOT EXISTS +
 * INSERT IGNORE). İstek başına en fazla bir kez çalışır.
 */
function notif_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $stmts = [];

    $stmts[] = "CREATE TABLE IF NOT EXISTS `notification_settings` (
        `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `setting_key`   VARCHAR(120) NOT NULL,
        `setting_value` TEXT         DEFAULT NULL,
        `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_notification_setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $stmts[] = "CREATE TABLE IF NOT EXISTS `user_notifications` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $stmts[] = "CREATE TABLE IF NOT EXISTS `notification_templates` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $stmts[] = "CREATE TABLE IF NOT EXISTS `notification_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $stmts[] = "INSERT IGNORE INTO `notification_settings` (`setting_key`, `setting_value`) VALUES
        ('birthday_enabled',        '1'),
        ('birthday_days_before',    '7'),
        ('anniversary_enabled',     '1'),
        ('anniversary_days_before', '7'),
        ('leave_enabled',           '1'),
        ('leave_days_before',       '3'),
        ('mail_auto',               '0'),
        ('whatsapp_auto',           '0'),
        ('show_age',                '0')";

    $stmts[] = "INSERT IGNORE INTO `notification_templates` (`template_key`, `title`, `channel`, `subject`, `body`) VALUES
        ('birthday', 'Doğum Günü — WhatsApp', 'whatsapp', NULL,
         'Merhaba {personnel_name}, doğum gününüzü kutlar; sağlıklı, mutlu ve başarılı bir yaş dileriz.\n\n{company_name}'),
        ('birthday', 'Doğum Günü — E-posta', 'email', 'Doğum Gününüz Kutlu Olsun',
         'Merhaba {personnel_name},\n\nDoğum gününüzü kutlar; sağlıklı, mutlu ve başarılı bir yaş dileriz.\n\n{company_name}'),
        ('anniversary', 'Çalışma Yıl Dönümü — WhatsApp', 'whatsapp', NULL,
         'Merhaba {personnel_name}, şirketimizdeki {year}. yılınızı kutlarız. Emekleriniz ve katkılarınız için teşekkür ederiz.\n\n{company_name}'),
        ('anniversary', 'Çalışma Yıl Dönümü — E-posta', 'email', 'Çalışma Yıl Dönümünüz Kutlu Olsun',
         'Merhaba {personnel_name},\n\nŞirketimizdeki {year}. yılınızı kutlarız. Emekleriniz ve katkılarınız için teşekkür ederiz.\n\n{company_name}')";

    try {
        foreach ($stmts as $sql) {
            db()->exec($sql);
        }
    } catch (Throwable $e) {
        log_error('notif_ensure_schema: ' . $e->getMessage());
    }
}

/* =========================================================================
 |  Ayarlar (notification_settings)
 * ====================================================================== */
function notif_setting_get(string $key, ?string $default = null): ?string
{
    notif_ensure_schema();
    try {
        $st = db()->prepare('SELECT setting_value FROM notification_settings WHERE setting_key = :k LIMIT 1');
        $st->execute([':k' => $key]);
        $v = $st->fetchColumn();
        return $v === false ? $default : (string) $v;
    } catch (Throwable $e) { log_error('notif_setting_get: ' . $e->getMessage()); return $default; }
}
function notif_setting_set(string $key, string $value): bool
{
    try {
        db()->prepare('INSERT INTO notification_settings (setting_key, setting_value) VALUES (:k, :v)
                       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute([':k' => $key, ':v' => $value]);
        return true;
    } catch (Throwable $e) { log_error('notif_setting_set: ' . $e->getMessage()); return false; }
}
function notif_settings(): array
{
    return [
        'birthday_enabled'        => notif_setting_get('birthday_enabled', '1') === '1',
        'birthday_days_before'    => max(0, (int) notif_setting_get('birthday_days_before', '7')),
        'anniversary_enabled'     => notif_setting_get('anniversary_enabled', '1') === '1',
        'anniversary_days_before' => max(0, (int) notif_setting_get('anniversary_days_before', '7')),
        'leave_enabled'           => notif_setting_get('leave_enabled', '1') === '1',
        'leave_days_before'       => max(0, (int) notif_setting_get('leave_days_before', '3')),
        'mail_auto'               => notif_setting_get('mail_auto', '0') === '1',
        'whatsapp_auto'           => notif_setting_get('whatsapp_auto', '0') === '1',
        'show_age'                => notif_setting_get('show_age', '0') === '1',
    ];
}
function notif_settings_save(array $d): bool
{
    $ok = true;
    $ok = notif_setting_set('birthday_enabled', !empty($d['birthday_enabled']) ? '1' : '0') && $ok;
    $ok = notif_setting_set('birthday_days_before', (string) max(0, (int) ($d['birthday_days_before'] ?? 7))) && $ok;
    $ok = notif_setting_set('anniversary_enabled', !empty($d['anniversary_enabled']) ? '1' : '0') && $ok;
    $ok = notif_setting_set('anniversary_days_before', (string) max(0, (int) ($d['anniversary_days_before'] ?? 7))) && $ok;
    $ok = notif_setting_set('leave_enabled', !empty($d['leave_enabled']) ? '1' : '0') && $ok;
    $ok = notif_setting_set('leave_days_before', (string) max(0, (int) ($d['leave_days_before'] ?? 3))) && $ok;
    $ok = notif_setting_set('mail_auto', !empty($d['mail_auto']) ? '1' : '0') && $ok;
    $ok = notif_setting_set('whatsapp_auto', !empty($d['whatsapp_auto']) ? '1' : '0') && $ok;
    $ok = notif_setting_set('show_age', !empty($d['show_age']) ? '1' : '0') && $ok;
    return $ok;
}

/** İzin detaylarını (açıklama/not) görebilir mi? (KVKK) */
function notif_can_see_details(): bool
{
    return function_exists('can') && (can('leave') || can('settings'));
}

/* =========================================================================
 |  Şablonlar
 * ====================================================================== */
function notif_types(): array
{
    return ['leave' => 'İzin', 'birthday' => 'Doğum Günü', 'anniversary' => 'Çalışma Yıl Dönümü', 'system' => 'Sistem'];
}
function get_notification_templates(): array
{
    notif_ensure_schema();
    try { return db()->query('SELECT * FROM notification_templates ORDER BY template_key, channel')->fetchAll(); }
    catch (Throwable $e) { log_error('get_notification_templates: ' . $e->getMessage()); return []; }
}
function get_notification_template(string $key, string $channel): ?array
{
    notif_ensure_schema();
    try {
        $st = db()->prepare('SELECT * FROM notification_templates WHERE template_key = :k AND channel = :c LIMIT 1');
        $st->execute([':k' => $key, ':c' => $channel]);
        $r = $st->fetch();
        return $r ?: null;
    } catch (Throwable $e) { log_error('get_notification_template: ' . $e->getMessage()); return null; }
}
function save_notification_template(int $id, string $subject, string $body, bool $active): bool
{
    try {
        db()->prepare('UPDATE notification_templates SET subject = :s, body = :b, is_active = :a WHERE id = :id')
            ->execute([':s' => ($subject !== '' ? $subject : null), ':b' => $body, ':a' => $active ? 1 : 0, ':id' => $id]);
        return true;
    } catch (Throwable $e) { log_error('save_notification_template: ' . $e->getMessage()); return false; }
}
/** {personnel_name},{year},{company_name},{date} yerleştirme. */
function notif_render_template(string $body, array $vars): string
{
    $repl = [];
    foreach ($vars as $k => $v) { $repl['{' . $k . '}'] = (string) $v; }
    return strtr($body, $repl);
}
function notif_company_name(): string
{
    $c = service_company_info();
    $n = trim((string) ($c['company_name'] ?? ''));
    return $n !== '' ? $n : 'Şirket';
}

/* =========================================================================
 |  Tarih yardımcıları
 * ====================================================================== */
/** Ay/gün için bugünden itibaren sonraki oluşum tarihini ve kalan gün sayısını verir. */
function notif_next_occurrence(int $month, int $day, ?string $from = null): array
{
    $today = $from !== null ? new DateTimeImmutable($from) : new DateTimeImmutable('today');
    $year = (int) $today->format('Y');
    // 29 Şubat güvenliği
    $mk = static function (int $y, int $m, int $d) {
        $dd = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
        if ($d > $dd) { $d = $dd; }
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $d));
    };
    $occ = $mk($year, $month, $day);
    if ($occ < $today->setTime(0, 0)) { $occ = $mk($year + 1, $month, $day); }
    $days = (int) $today->setTime(0, 0)->diff($occ)->days;
    return ['date' => $occ->format('Y-m-d'), 'days' => $days, 'year' => (int) $occ->format('Y')];
}

/* =========================================================================
 |  Kaynak veriler (doğum günü / yıl dönümü / izin)
 * ====================================================================== */
/** Bildirime uygun aktif personel (pasif/ayrılmış hariç). */
function notif_eligible_personnel(): array
{
    try {
        return db()->query("SELECT id, full_name, department, phone, whatsapp, email, birth_date, hire_date, show_birthday_notifications
                            FROM personnel
                            WHERE is_active = 1 AND termination_date IS NULL
                            ORDER BY full_name ASC")->fetchAll();
    } catch (Throwable $e) { log_error('notif_eligible_personnel: ' . $e->getMessage()); return []; }
}

function get_today_birthdays(): array
{
    $out = [];
    foreach (notif_eligible_personnel() as $p) {
        if ((int) $p['show_birthday_notifications'] !== 1) { continue; }
        if (empty($p['birth_date'])) { continue; }
        $m = (int) substr((string) $p['birth_date'], 5, 2);
        $d = (int) substr((string) $p['birth_date'], 8, 2);
        $occ = notif_next_occurrence($m, $d);
        if ($occ['days'] === 0) { $p['days'] = 0; $out[] = $p; }
    }
    return $out;
}
function get_upcoming_birthdays(int $days = 7): array
{
    $out = [];
    foreach (notif_eligible_personnel() as $p) {
        if ((int) $p['show_birthday_notifications'] !== 1) { continue; }
        if (empty($p['birth_date'])) { continue; }
        $m = (int) substr((string) $p['birth_date'], 5, 2);
        $d = (int) substr((string) $p['birth_date'], 8, 2);
        $occ = notif_next_occurrence($m, $d);
        if ($occ['days'] >= 1 && $occ['days'] <= $days) { $p['days'] = $occ['days']; $p['occ_date'] = $occ['date']; $out[] = $p; }
    }
    usort($out, static fn($a, $b) => $a['days'] <=> $b['days']);
    return $out;
}
function get_today_work_anniversaries(): array
{
    $out = [];
    foreach (notif_eligible_personnel() as $p) {
        if (empty($p['hire_date'])) { continue; }
        $m = (int) substr((string) $p['hire_date'], 5, 2);
        $d = (int) substr((string) $p['hire_date'], 8, 2);
        $hy = (int) substr((string) $p['hire_date'], 0, 4);
        $occ = notif_next_occurrence($m, $d);
        $years = $occ['year'] - $hy;
        if ($occ['days'] === 0 && $years >= 1) { $p['days'] = 0; $p['years'] = $years; $out[] = $p; }
    }
    return $out;
}
function get_upcoming_work_anniversaries(int $days = 7): array
{
    $out = [];
    foreach (notif_eligible_personnel() as $p) {
        if (empty($p['hire_date'])) { continue; }
        $m = (int) substr((string) $p['hire_date'], 5, 2);
        $d = (int) substr((string) $p['hire_date'], 8, 2);
        $hy = (int) substr((string) $p['hire_date'], 0, 4);
        $occ = notif_next_occurrence($m, $d);
        $years = $occ['year'] - $hy;
        if ($occ['days'] >= 1 && $occ['days'] <= $days && $years >= 1) { $p['days'] = $occ['days']; $p['years'] = $years; $p['occ_date'] = $occ['date']; $out[] = $p; }
    }
    usort($out, static fn($a, $b) => $a['days'] <=> $b['days']);
    return $out;
}

function get_today_leave_notifications(): array
{
    try {
        $st = db()->prepare("SELECT lr.id, lr.personnel_id, lr.start_date, lr.end_date, lr.description,
                                    p.full_name AS personnel_name, p.department, lt.name AS type_name
                             FROM leave_requests lr
                             JOIN personnel p ON p.id = lr.personnel_id
                             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                             WHERE lr.is_deleted = 0 AND lr.status = 'approved'
                               AND CURDATE() BETWEEN lr.start_date AND lr.end_date
                             ORDER BY p.full_name ASC");
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_today_leave_notifications: ' . $e->getMessage()); return []; }
}
function get_upcoming_leave_notifications(int $days = 3): array
{
    try {
        $st = db()->prepare("SELECT lr.id, lr.personnel_id, lr.start_date, lr.end_date, lr.description,
                                    p.full_name AS personnel_name, p.department, lt.name AS type_name,
                                    DATEDIFF(lr.start_date, CURDATE()) AS days_left
                             FROM leave_requests lr
                             JOIN personnel p ON p.id = lr.personnel_id
                             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                             WHERE lr.is_deleted = 0 AND lr.status = 'approved'
                               AND lr.start_date > CURDATE() AND lr.start_date <= DATE_ADD(CURDATE(), INTERVAL :d DAY)
                             ORDER BY lr.start_date ASC");
        $st->bindValue(':d', $days, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_upcoming_leave_notifications: ' . $e->getMessage()); return []; }
}
function get_pending_leave_notifications(): array
{
    try {
        $st = db()->prepare("SELECT lr.id, lr.personnel_id, lr.start_date, lr.end_date,
                                    p.full_name AS personnel_name, lt.name AS type_name
                             FROM leave_requests lr
                             JOIN personnel p ON p.id = lr.personnel_id
                             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                             WHERE lr.is_deleted = 0 AND lr.status = 'pending'
                             ORDER BY lr.start_date ASC");
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_pending_leave_notifications: ' . $e->getMessage()); return []; }
}

/* =========================================================================
 |  Günün özeti (hoş geldin kartı) — DB'ye yazmaz
 * ====================================================================== */
function get_daily_summary(): array
{
    $s = notif_settings();
    return [
        'today_leaves'        => $s['leave_enabled'] ? count(get_today_leave_notifications()) : 0,
        'today_birthdays'     => $s['birthday_enabled'] ? count(get_today_birthdays()) : 0,
        'today_anniversaries' => $s['anniversary_enabled'] ? count(get_today_work_anniversaries()) : 0,
        'upcoming_anniv'      => $s['anniversary_enabled'] ? count(get_upcoming_work_anniversaries($s['anniversary_days_before'])) : 0,
        'upcoming_birthdays'  => $s['birthday_enabled'] ? count(get_upcoming_birthdays($s['birthday_days_before'])) : 0,
        'pending_leaves'      => ($s['leave_enabled'] && notif_can_see_details()) ? count(get_pending_leave_notifications()) : 0,
    ];
}

/* =========================================================================
 |  Bildirim üretimi (user_notifications)
 * ====================================================================== */
function notif_active_user_ids(): array
{
    try { return array_map('intval', db()->query('SELECT id FROM users WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN)); }
    catch (Throwable $e) { log_error('notif_active_user_ids: ' . $e->getMessage()); return []; }
}

/** Verilen kullanıcı(lar) için günlük İK bildirimlerini üretir (dedupe: UNIQUE anahtar). */
function generate_daily_hr_notifications(?string $date = null, ?int $userId = null): int
{
    $s = notif_settings();
    $userIds = $userId !== null ? [$userId] : notif_active_user_ids();
    if (empty($userIds)) { return 0; }

    // Ortak bildirim öğeleri (tüm kullanıcılara aynı)
    $items = []; // her biri: [type, related_type, related_id, date, title, message]

    if ($s['birthday_enabled']) {
        foreach (get_today_birthdays() as $p) {
            $items[] = ['birthday', 'personnel', (int) $p['id'], date('Y-m-d'),
                'Doğum günü', 'Bugün ' . $p['full_name'] . ' doğum gününü kutluyor. Tebrik etmeyi unutma 🎉'];
        }
        foreach (get_upcoming_birthdays($s['birthday_days_before']) as $p) {
            $items[] = ['birthday', 'personnel', (int) $p['id'], (string) $p['occ_date'],
                'Yaklaşan doğum günü', $p['full_name'] . ' — doğum gününe ' . (int) $p['days'] . ' gün kaldı.'];
        }
    }
    if ($s['anniversary_enabled']) {
        foreach (get_today_work_anniversaries() as $p) {
            $items[] = ['anniversary', 'personnel', (int) $p['id'], date('Y-m-d'),
                'Çalışma yıl dönümü', $p['full_name'] . ' bugün şirkette ' . (int) $p['years'] . '. yılını doldurdu. Tebrikler!'];
        }
        foreach (get_upcoming_work_anniversaries($s['anniversary_days_before']) as $p) {
            $items[] = ['anniversary', 'personnel', (int) $p['id'], (string) $p['occ_date'],
                'Yaklaşan yıl dönümü', $p['full_name'] . ' — şirketteki ' . (int) $p['years'] . '. yıl dönümüne ' . (int) $p['days'] . ' gün kaldı.'];
        }
    }
    if ($s['leave_enabled']) {
        foreach (get_today_leave_notifications() as $l) {
            $items[] = ['leave', 'leave_request', (int) $l['id'], date('Y-m-d'),
                'Bugün izinde', $l['personnel_name'] . ' — ' . ($l['type_name'] ?? 'İzin') . ' — ' . fmt_date((string) $l['start_date']) . ' / ' . fmt_date((string) $l['end_date'])];
        }
        foreach (get_upcoming_leave_notifications($s['leave_days_before']) as $l) {
            $items[] = ['leave', 'leave_request', (int) $l['id'], (string) $l['start_date'],
                'Yaklaşan izin', $l['personnel_name'] . ' — ' . fmt_date((string) $l['start_date']) . ' tarihinde ' . ($l['type_name'] ?? 'izne') . ' çıkıyor.'];
        }
    }
    if (empty($items)) { return 0; }

    $count = 0;
    try {
        $ins = db()->prepare('INSERT IGNORE INTO user_notifications
            (user_id, notification_type, title, message, related_type, related_id, notification_date)
            VALUES (:uid,:type,:title,:msg,:rtype,:rid,:ndate)');
        foreach ($userIds as $uid) {
            foreach ($items as $it) {
                $ins->execute([':uid' => $uid, ':type' => $it[0], ':rtype' => $it[1], ':rid' => $it[2], ':ndate' => $it[3], ':title' => $it[4], ':msg' => $it[5]]);
                $count += $ins->rowCount() > 0 ? 1 : 0;
            }
        }
    } catch (Throwable $e) { log_error('generate_daily_hr_notifications: ' . $e->getMessage()); }
    return $count;
}

/* =========================================================================
 |  Bildirim listesi / okundu
 * ====================================================================== */
function get_user_notifications(int $userId, int $limit = 20, array $filters = []): array
{
    notif_ensure_schema();
    $limit = max(1, min(200, $limit));
    $sql = 'SELECT n.*, p.full_name AS personnel_name, p.phone AS personnel_phone, p.whatsapp AS personnel_whatsapp, p.email AS personnel_email
            FROM user_notifications n
            LEFT JOIN personnel p ON (n.related_type = "personnel" AND p.id = n.related_id)
            WHERE n.user_id = :uid AND n.is_deleted = 0';
    $p = [':uid' => $userId];
    if (!empty($filters['type']) && array_key_exists($filters['type'], notif_types())) { $sql .= ' AND n.notification_type = :t'; $p[':t'] = (string) $filters['type']; }
    if (isset($filters['is_read']) && $filters['is_read'] !== '') { $sql .= ' AND n.is_read = :r'; $p[':r'] = (int) $filters['is_read']; }
    if (!empty($filters['date'])) { $sql .= ' AND n.notification_date = :d'; $p[':d'] = (string) $filters['date']; }
    if (!empty($filters['personnel_id'])) { $sql .= ' AND n.related_type = "personnel" AND n.related_id = :pid'; $p[':pid'] = (int) $filters['personnel_id']; }
    $sql .= ' ORDER BY n.is_read ASC, n.created_at DESC, n.id DESC LIMIT ' . $limit;
    try { $st = db()->prepare($sql); $st->execute($p); return $st->fetchAll(); }
    catch (Throwable $e) { log_error('get_user_notifications: ' . $e->getMessage()); return []; }
}
function get_unread_notification_count(int $userId): int
{
    notif_ensure_schema();
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM user_notifications WHERE user_id = :uid AND is_read = 0 AND is_deleted = 0');
        $st->execute([':uid' => $userId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { log_error('get_unread_notification_count: ' . $e->getMessage()); return 0; }
}
function mark_notification_read(int $notificationId, int $userId): bool
{
    try {
        db()->prepare('UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :uid')
            ->execute([':id' => $notificationId, ':uid' => $userId]);
        return true;
    } catch (Throwable $e) { log_error('mark_notification_read: ' . $e->getMessage()); return false; }
}
function mark_all_notifications_read(int $userId): bool
{
    try {
        db()->prepare('UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = :uid AND is_read = 0')->execute([':uid' => $userId]);
        return true;
    } catch (Throwable $e) { log_error('mark_all_notifications_read: ' . $e->getMessage()); return false; }
}
function delete_user_notification(int $notificationId, int $userId): bool
{
    try {
        db()->prepare('UPDATE user_notifications SET is_deleted = 1 WHERE id = :id AND user_id = :uid')->execute([':id' => $notificationId, ':uid' => $userId]);
        return true;
    } catch (Throwable $e) { log_error('delete_user_notification: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  Kutlama: WhatsApp linki / Mail / Log
 * ====================================================================== */
function build_whatsapp_message_link(?string $phone, string $message): ?string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '' || $digits === null) { return null; }
    if (strlen($digits) === 10 && $digits[0] === '5') { $digits = '90' . $digits; }
    elseif (strlen($digits) === 11 && $digits[0] === '0') { $digits = '90' . substr($digits, 1); }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

function log_notification(array $d): void
{
    try {
        db()->prepare('INSERT INTO notification_logs (notification_type, channel, personnel_id, user_id, recipient, subject, message, status, error_message, sent_at)
            VALUES (:type,:ch,:pid,:uid,:rcpt,:subj,:msg,:status,:err,:sent)')
            ->execute([
                ':type' => (string) ($d['notification_type'] ?? 'system'),
                ':ch'   => (string) ($d['channel'] ?? 'panel'),
                ':pid'  => isset($d['personnel_id']) ? (int) $d['personnel_id'] : null,
                ':uid'  => current_user_id(),
                ':rcpt' => $d['recipient'] ?? null,
                ':subj' => $d['subject'] ?? null,
                ':msg'  => $d['message'] ?? null,
                ':status' => (string) ($d['status'] ?? 'pending'),
                ':err'  => $d['error_message'] ?? null,
                ':sent' => ($d['status'] ?? '') === 'sent' ? date('Y-m-d H:i:s') : null,
            ]);
    } catch (Throwable $e) { log_error('log_notification: ' . $e->getMessage()); }
}

/**
 * Kutlama maili gönderimi — merkezî mail servisine (PHPMailer/SMTP) delege eder.
 * PHP mail() KULLANILMAZ. Yalnızca gerçek SMTP send() true dönerse başarı döner.
 * @return array{ok:bool, msg:string, error:?string}
 */
function notif_send_mail(string $to, string $toName, string $subject, string $body, string $type = 'celebration'): array
{
    return mail_send($to, $toName, $subject, $body, ['type' => $type]);
}

function notif_celebration_vars(array $personnel, string $type): array
{
    $vars = [
        'personnel_name' => (string) $personnel['full_name'],
        'company_name'   => notif_company_name(),
        'date'           => fmt_date(date('Y-m-d')),
        'year'           => '',
    ];
    if ($type === 'anniversary' && !empty($personnel['hire_date'])) {
        $hy = (int) substr((string) $personnel['hire_date'], 0, 4);
        $vars['year'] = (string) max(1, (int) date('Y') - $hy);
    }
    return $vars;
}

/** Kutlama e-postası gönder + logla. $type: birthday|anniversary */
function send_celebration_email(int $personnelId, string $type): array
{
    $p = get_personnel_by_id($personnelId);
    if (!$p) { return ['ok' => false, 'msg' => 'Personel bulunamadı.']; }

    // 1) Kişinin e-posta adresi yoksa mail gönderme.
    $to = trim((string) ($p['email'] ?? ''));
    if ($to === '') { return ['ok' => false, 'msg' => 'Bu kişi için kayıtlı e-posta adresi bulunmuyor.']; }

    // 2) SMTP yapılandırması yoksa mail gönderme (sahte başarı yok).
    $cfg = mail_config_status();
    if (!$cfg['ok']) { return ['ok' => false, 'msg' => $cfg['msg']]; }

    // 3) Şablon
    $tpl = get_notification_template($type, 'email');
    if (!$tpl || (int) $tpl['is_active'] !== 1) { return ['ok' => false, 'msg' => 'E-posta şablonu bulunamadı/pasif.']; }
    $vars = notif_celebration_vars($p, $type);
    $subject = notif_render_template((string) ($tpl['subject'] ?? ''), $vars);
    $body = notif_render_template((string) $tpl['body'], $vars);

    // 4) Gerçek SMTP gönderimi — başarı yalnızca send() true dönerse.
    $res = notif_send_mail($to, (string) ($p['full_name'] ?? ''), $subject, $body, $type);

    log_notification([
        'notification_type' => $type, 'channel' => 'email', 'personnel_id' => $personnelId,
        'recipient' => $to, 'subject' => $subject, 'message' => $body,
        'status' => $res['ok'] ? 'sent' : 'failed', 'error_message' => $res['ok'] ? null : ($res['error'] ?? 'SMTP gönderimi başarısız'),
    ]);
    return ['ok' => $res['ok'], 'msg' => $res['msg']];
}
function send_birthday_email(int $personnelId): array { return send_celebration_email($personnelId, 'birthday'); }
function send_anniversary_email(int $personnelId): array { return send_celebration_email($personnelId, 'anniversary'); }

/** WhatsApp kutlama linki üret + manuel açılışı logla. */
function whatsapp_celebration_link(int $personnelId, string $type): ?string
{
    $p = get_personnel_by_id($personnelId);
    if (!$p) { return null; }
    $phone = (string) ($p['whatsapp'] ?? '');
    if ($phone === '') { $phone = (string) ($p['phone'] ?? ''); }
    if ($phone === '') { return null; }
    $tpl = get_notification_template($type, 'whatsapp');
    $body = $tpl ? (string) $tpl['body'] : 'Merhaba {personnel_name}';
    $msg = notif_render_template($body, notif_celebration_vars($p, $type));
    return build_whatsapp_message_link($phone, $msg);
}
