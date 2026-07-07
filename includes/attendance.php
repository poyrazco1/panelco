<?php
declare(strict_types=1);

/**
 * includes/attendance.php
 * İK / Puantaj motoru: durumlar, çalışma programları, aylık puantaj tablosu,
 * hücre kaydı, toplu işlemler, aylık özet ve onaylı izin senkronizasyonu.
 * Tüm sorgular prepared; hatalar log'lanır, fatal atılmaz.
 * Tatil/hafta sonu kümesi ve onaylı izinler İzin modülünden (leave.php) alınır.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/personnel.php';
require_once __DIR__ . '/leave.php';

/**
 * Puantaj şemasını kendini onarır (idempotent).
 *
 * Puantaj güncellemesi eksik/kısmi içe aktarıldığında `attendance_statuses`
 * tablosu hiç oluşmamış ya da oluşmuş ama boş kalmış olabilir. Bu durumda
 * gün düzenleme penceresindeki "Durum" listesi boş gelir ve hiçbir şey
 * seçilemez. Bu fonksiyon eksik tabloları oluşturur ve varsayılan durum
 * kümesini (14 durum) + varsayılan çalışma programını güvenle ekler.
 *
 * Hem tablo yok hem de "tablo var ama boş" durumunu tek seferde çözer:
 * CREATE TABLE IF NOT EXISTS + INSERT IGNORE (kod alanı UNIQUE olduğundan
 * mevcut kayıtlar korunur). İstek başına en fazla bir kez çalışır.
 */
function attendance_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $stmts = [];

    $stmts[] = "CREATE TABLE IF NOT EXISTS `attendance_statuses` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $stmts[] = "CREATE TABLE IF NOT EXISTS `attendance_records` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $stmts[] = "CREATE TABLE IF NOT EXISTS `work_schedules` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Varsayılan puantaj durumları (kod UNIQUE → mevcut kayıt ezilmez, boşsa doldurulur)
    $stmts[] = "INSERT IGNORE INTO `attendance_statuses`
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
        ('Diğer',            'other',        '#f3f4f6', 1, 0, 0, 0, 14)";

    // Varsayılan çalışma programı
    $stmts[] = "INSERT IGNORE INTO `work_schedules` (`id`, `name`, `start_time`, `end_time`, `break_minutes`, `weekly_work_days`, `is_default`, `is_active`) VALUES
        (1, 'Standart Mesai', '09:00:00', '18:00:00', 60, '1,2,3,4,5', 1, 1)";

    try {
        foreach ($stmts as $sql) {
            db()->exec($sql);
        }
    } catch (Throwable $e) {
        log_error('attendance_ensure_schema: ' . $e->getMessage());
    }
}

/* =========================================================================
 |  Durumlar
 * ====================================================================== */
/** Grid kısaltmaları (varsayılan kodlar için). */
function attendance_short_labels(): array
{
    return [
        'worked' => 'Ç', 'leave' => 'İ', 'annual_leave' => 'Yİ', 'unpaid_leave' => 'Üİ',
        'sick' => 'R', 'absent' => 'G', 'half_day' => 'Y', 'week_off' => 'HT',
        'holiday' => 'RT', 'overtime' => 'FM', 'late' => 'GG', 'early_out' => 'EÇ',
        'remote' => 'UÇ', 'other' => 'D',
    ];
}
function attendance_short_label(string $code, string $name = ''): string
{
    $map = attendance_short_labels();
    if (isset($map[$code])) { return $map[$code]; }
    $name = trim($name);
    return $name !== '' ? mb_strtoupper(mb_substr($name, 0, 2, 'UTF-8'), 'UTF-8') : '?';
}

function get_attendance_statuses(bool $onlyActive = true): array
{
    attendance_ensure_schema();
    try {
        $sql = 'SELECT * FROM attendance_statuses';
        if ($onlyActive) { $sql .= ' WHERE is_active = 1'; }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = db()->query($sql)->fetchAll();
        foreach ($rows as &$r) { $r['short'] = attendance_short_label((string) $r['code'], (string) $r['name']); }
        unset($r);
        return $rows;
    } catch (Throwable $e) { log_error('get_attendance_statuses: ' . $e->getMessage()); return []; }
}
function attendance_status_by_id_map(bool $onlyActive = false): array
{
    $out = [];
    foreach (get_attendance_statuses($onlyActive) as $s) { $out[(int) $s['id']] = $s; }
    return $out;
}
function attendance_status_by_code(string $code): ?array
{
    foreach (get_attendance_statuses(false) as $s) { if ((string) $s['code'] === $code) { return $s; } }
    return null;
}
function get_attendance_status(int $id): ?array
{
    try { $st = db()->prepare('SELECT * FROM attendance_statuses WHERE id = :id LIMIT 1'); $st->execute([':id' => $id]); $r = $st->fetch(); return $r ?: null; }
    catch (Throwable $e) { log_error('get_attendance_status: ' . $e->getMessage()); return null; }
}
function attendance_status_code_exists(string $code, ?int $excludeId = null): bool
{
    try {
        $sql = 'SELECT COUNT(*) FROM attendance_statuses WHERE code = :c'; $p = [':c' => $code];
        if ($excludeId !== null) { $sql .= ' AND id <> :id'; $p[':id'] = $excludeId; }
        $st = db()->prepare($sql); $st->execute($p); return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) { log_error('attendance_status_code_exists: ' . $e->getMessage()); return false; }
}
function save_attendance_status(array $d, ?int $id = null): array
{
    $name = trim((string) ($d['name'] ?? ''));
    $code = trim((string) ($d['code'] ?? ''));
    $code = $code !== '' ? preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code)) : '';
    $color = trim((string) ($d['color'] ?? ''));
    $errors = [];
    if ($name === '') { $errors[] = 'Ad gerekli.'; }
    if ($code === '') { $errors[] = 'Kod gerekli.'; }
    if ($code !== '' && attendance_status_code_exists($code, $id)) { $errors[] = 'Bu kod zaten kullanılıyor.'; }
    if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) { $color = ''; }
    if ($errors) { return ['ok' => false, 'errors' => $errors, 'id' => (int) $id]; }
    $params = [
        ':name' => $name, ':code' => $code, ':color' => ($color !== '' ? $color : null),
        ':paid' => !empty($d['is_paid']) ? 1 : 0,
        ':wd'   => !empty($d['counts_as_workday']) ? 1 : 0,
        ':abs'  => !empty($d['counts_as_absence']) ? 1 : 0,
        ':ded'  => !empty($d['deducts_annual_leave']) ? 1 : 0,
        ':sort' => (int) ($d['sort_order'] ?? 0),
        ':act'  => !empty($d['is_active']) ? 1 : 0,
    ];
    try {
        if ($id) {
            $params[':id'] = $id;
            db()->prepare('UPDATE attendance_statuses SET name=:name, code=:code, color=:color, is_paid=:paid,
                counts_as_workday=:wd, counts_as_absence=:abs, deducts_annual_leave=:ded, sort_order=:sort, is_active=:act WHERE id=:id')->execute($params);
            return ['ok' => true, 'errors' => [], 'id' => $id];
        }
        db()->prepare('INSERT INTO attendance_statuses (name, code, color, is_paid, counts_as_workday, counts_as_absence, deducts_annual_leave, sort_order, is_active)
            VALUES (:name,:code,:color,:paid,:wd,:abs,:ded,:sort,:act)')->execute($params);
        return ['ok' => true, 'errors' => [], 'id' => (int) db()->lastInsertId()];
    } catch (Throwable $e) { log_error('save_attendance_status: ' . $e->getMessage()); return ['ok' => false, 'errors' => ['Kaydedilemedi.'], 'id' => (int) $id]; }
}
function toggle_attendance_status(int $id, bool $active): bool
{
    try { db()->prepare('UPDATE attendance_statuses SET is_active=:a WHERE id=:id')->execute([':a' => $active ? 1 : 0, ':id' => $id]); return true; }
    catch (Throwable $e) { log_error('toggle_attendance_status: ' . $e->getMessage()); return false; }
}
function delete_attendance_status(int $id): bool
{
    try {
        // Kullanımda ise silme
        $st = db()->prepare('SELECT COUNT(*) FROM attendance_records WHERE status_id = :id'); $st->execute([':id' => $id]);
        if ((int) $st->fetchColumn() > 0) { return false; }
        db()->prepare('DELETE FROM attendance_statuses WHERE id=:id')->execute([':id' => $id]); return true;
    } catch (Throwable $e) { log_error('delete_attendance_status: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  Çalışma programları (Vardiya / Mesai)
 * ====================================================================== */
function get_work_schedules(bool $onlyActive = false): array
{
    try {
        $sql = 'SELECT * FROM work_schedules';
        if ($onlyActive) { $sql .= ' WHERE is_active = 1'; }
        $sql .= ' ORDER BY is_default DESC, id ASC';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) { log_error('get_work_schedules: ' . $e->getMessage()); return []; }
}
function get_default_schedule(): ?array
{
    try {
        $r = db()->query('SELECT * FROM work_schedules WHERE is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1')->fetch();
        return $r ?: null;
    } catch (Throwable $e) { log_error('get_default_schedule: ' . $e->getMessage()); return null; }
}
function get_work_schedule(int $id): ?array
{
    try { $st = db()->prepare('SELECT * FROM work_schedules WHERE id = :id LIMIT 1'); $st->execute([':id' => $id]); $r = $st->fetch(); return $r ?: null; }
    catch (Throwable $e) { log_error('get_work_schedule: ' . $e->getMessage()); return null; }
}
function save_work_schedule(array $d, ?int $id = null): array
{
    $name = trim((string) ($d['name'] ?? ''));
    if ($name === '') { return ['ok' => false, 'errors' => ['Ad gerekli.'], 'id' => (int) $id]; }
    $days = $d['weekly_work_days'] ?? [];
    if (is_array($days)) {
        $days = array_values(array_unique(array_filter(array_map('intval', $days), static fn($v) => $v >= 0 && $v <= 6)));
        $days = implode(',', $days);
    }
    $params = [
        ':name'  => $name,
        ':st'    => (!empty($d['start_time']) ? (string) $d['start_time'] : null),
        ':et'    => (!empty($d['end_time']) ? (string) $d['end_time'] : null),
        ':brk'   => (int) ($d['break_minutes'] ?? 0),
        ':days'  => ($days !== '' ? (string) $days : '1,2,3,4,5'),
        ':def'   => !empty($d['is_default']) ? 1 : 0,
        ':act'   => !empty($d['is_active']) ? 1 : 0,
    ];
    try {
        if (!empty($d['is_default'])) { db()->query('UPDATE work_schedules SET is_default = 0'); }
        if ($id) {
            $params[':id'] = $id;
            db()->prepare('UPDATE work_schedules SET name=:name, start_time=:st, end_time=:et, break_minutes=:brk, weekly_work_days=:days, is_default=:def, is_active=:act WHERE id=:id')->execute($params);
            return ['ok' => true, 'errors' => [], 'id' => $id];
        }
        db()->prepare('INSERT INTO work_schedules (name, start_time, end_time, break_minutes, weekly_work_days, is_default, is_active) VALUES (:name,:st,:et,:brk,:days,:def,:act)')->execute($params);
        return ['ok' => true, 'errors' => [], 'id' => (int) db()->lastInsertId()];
    } catch (Throwable $e) { log_error('save_work_schedule: ' . $e->getMessage()); return ['ok' => false, 'errors' => ['Kaydedilemedi.'], 'id' => (int) $id]; }
}
function delete_work_schedule(int $id): bool
{
    try { db()->prepare('DELETE FROM work_schedules WHERE id=:id')->execute([':id' => $id]); return true; }
    catch (Throwable $e) { log_error('delete_work_schedule: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  Yardımcılar
 * ====================================================================== */
function attendance_month_bounds(int $year, int $month): array
{
    if ($month < 1) { $month = 1; }
    if ($month > 12) { $month = 12; }
    $first = sprintf('%04d-%02d-01', $year, $month);
    $days = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $last = sprintf('%04d-%02d-%02d', $year, $month, $days);
    return ['first' => $first, 'last' => $last, 'days' => $days, 'year' => $year, 'month' => $month];
}
function attendance_minutes_between(string $in, string $out): int
{
    $a = strtotime('1970-01-01 ' . $in);
    $b = strtotime('1970-01-01 ' . $out);
    if ($a === false || $b === false) { return 0; }
    $diff = (int) round(($b - $a) / 60);
    return $diff > 0 ? $diff : 0;
}
function attendance_minutes_to_hm(int $min): string
{
    $min = max(0, $min);
    $h = intdiv($min, 60); $m = $min % 60;
    if ($h > 0 && $m > 0) { return $h . 's ' . $m . 'dk'; }
    if ($h > 0) { return $h . 's'; }
    return $m . 'dk';
}
/** Filtrelere göre personel listesi (show: active|all|terminated). */
function attendance_personnel_for_filters(array $filters = []): array
{
    $pf = ['order' => 'name'];
    $show = (string) ($filters['show'] ?? 'active');
    if ($show === 'active') { $pf['active'] = 'active'; }
    if (!empty($filters['department'])) { $pf['department'] = (string) $filters['department']; }
    if (!empty($filters['search'])) { $pf['search'] = (string) $filters['search']; }
    $list = get_personnel($pf);
    if (!empty($filters['personnel_id'])) {
        $pid = (int) $filters['personnel_id'];
        $list = array_values(array_filter($list, static fn($p) => (int) $p['id'] === $pid));
    }
    if ($show === 'terminated') {
        $list = array_values(array_filter($list, static fn($p) => !empty($p['termination_date'])));
    }
    return $list;
}

/* =========================================================================
 |  Aylık puantaj tablosu
 * ====================================================================== */
function get_month_attendance(int $year, int $month, array $filters = []): array
{
    $b = attendance_month_bounds($year, $month);
    $people = attendance_personnel_for_filters($filters);
    $set = leave_settings();
    $weekend = $set['weekend_days'];
    $holidaySet = holiday_set_for_range($b['first'], $b['last']);

    $records = [];
    if (!empty($people)) {
        $ids = array_map(static fn($p) => (int) $p['id'], $people);
        $in = implode(',', array_fill(0, count($ids), '?'));
        try {
            $st = db()->prepare("SELECT * FROM attendance_records WHERE attendance_date BETWEEN ? AND ? AND personnel_id IN ($in)");
            $st->execute(array_merge([$b['first'], $b['last']], $ids));
            foreach ($st->fetchAll() as $r) {
                $day = (int) substr((string) $r['attendance_date'], 8, 2);
                $records[(int) $r['personnel_id']][$day] = $r;
            }
        } catch (Throwable $e) { log_error('get_month_attendance: ' . $e->getMessage()); }
    }

    // Gün meta (hafta sonu / tatil / bugün)
    $dayMeta = [];
    for ($d = 1; $d <= $b['days']; $d++) {
        $ymd = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $w = (int) date('w', mktime(0, 0, 0, $month, $d, $year));
        $dayMeta[$d] = [
            'ymd'      => $ymd,
            'w'        => $w,
            'weekend'  => in_array($w, $weekend, true),
            'holiday'  => isset($holidaySet[$ymd]),
            'today'    => $ymd === date('Y-m-d'),
            'wlabel'   => ['Pz', 'Pt', 'Sa', 'Ça', 'Pe', 'Cu', 'Ct'][$w] ?? '',
        ];
    }

    return ['bounds' => $b, 'personnel' => $people, 'records' => $records, 'day_meta' => $dayMeta,
            'weekend' => $weekend, 'holiday_set' => $holidaySet];
}

/** Ayı oluştur/yenile: eksik hücreleri varsayılan takvime göre doldurur (mevcutları ezmez). */
function create_month_attendance(int $year, int $month, array $filters = []): array
{
    $b = attendance_month_bounds($year, $month);
    $people = attendance_personnel_for_filters($filters);
    if (empty($people)) { return ['ok' => true, 'created' => 0]; }

    $codes = [];
    foreach (['worked', 'week_off', 'holiday'] as $c) { $s = attendance_status_by_code($c); if ($s) { $codes[$c] = (int) $s['id']; } }
    if (!isset($codes['worked'])) { return ['ok' => false, 'created' => 0, 'errors' => ['"Çalıştı" durumu bulunamadı.']]; }

    $sched = get_default_schedule();
    $schedDays = $sched ? array_map('intval', array_filter(explode(',', (string) $sched['weekly_work_days']), static fn($x) => $x !== '')) : [1, 2, 3, 4, 5];
    $ci = $sched && !empty($sched['start_time']) ? (string) $sched['start_time'] : null;
    $co = $sched && !empty($sched['end_time']) ? (string) $sched['end_time'] : null;
    $brk = $sched ? (int) $sched['break_minutes'] : 0;
    $workMin = ($ci && $co) ? max(0, attendance_minutes_between($ci, $co) - $brk) : 0;

    $set = leave_settings();
    $weekend = $set['weekend_days'];
    $holidaySet = holiday_set_for_range($b['first'], $b['last']);

    $created = 0;
    try {
        $ins = db()->prepare('INSERT IGNORE INTO attendance_records
            (personnel_id, attendance_date, status_id, check_in, check_out, break_minutes, work_minutes, created_by_user_id, updated_by_user_id)
            VALUES (:pid,:date,:sid,:ci,:co,:brk,:wm,:uid,:uid2)');
        db()->beginTransaction();
        foreach ($people as $p) {
            $pid = (int) $p['id'];
            for ($d = 1; $d <= $b['days']; $d++) {
                $ymd = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $w = (int) date('w', mktime(0, 0, 0, $month, $d, $year));
                if (isset($holidaySet[$ymd]) && isset($codes['holiday'])) {
                    $sid = $codes['holiday']; $c1 = null; $c2 = null; $bm = 0; $wm = 0;
                } elseif (in_array($w, $weekend, true) && isset($codes['week_off'])) {
                    $sid = $codes['week_off']; $c1 = null; $c2 = null; $bm = 0; $wm = 0;
                } elseif (in_array($w, $schedDays, true)) {
                    $sid = $codes['worked']; $c1 = $ci; $c2 = $co; $bm = $brk; $wm = $workMin;
                } else {
                    // çalışma programında olmayan gün → boş bırak (hafta tatili değilse bile atlama)
                    if (isset($codes['week_off'])) { $sid = $codes['week_off']; $c1 = null; $c2 = null; $bm = 0; $wm = 0; }
                    else { continue; }
                }
                $ins->execute([':pid' => $pid, ':date' => $ymd, ':sid' => $sid, ':ci' => $c1, ':co' => $c2, ':brk' => $bm, ':wm' => $wm, ':uid' => current_user_id(), ':uid2' => current_user_id()]);
                $created += $ins->rowCount() > 0 ? 1 : 0;
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('create_month_attendance: ' . $e->getMessage());
        return ['ok' => false, 'created' => $created, 'errors' => ['Ay oluşturulamadı.']];
    }
    log_activity('attendance_generate_month', 'attendance', null, sprintf('%04d-%02d', $year, $month), 'success', 'created=' . $created);
    return ['ok' => true, 'created' => $created];
}

function save_attendance_record(array $d): array
{
    $pid = (int) ($d['personnel_id'] ?? 0);
    $date = trim((string) ($d['attendance_date'] ?? ''));
    $sid = (int) ($d['status_id'] ?? 0);
    if ($pid <= 0 || $date === '' || $sid <= 0) { return ['ok' => false, 'errors' => ['Eksik veri.']]; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { return ['ok' => false, 'errors' => ['Geçersiz tarih.']]; }
    $stMap = attendance_status_by_id_map(false);
    if (!isset($stMap[$sid])) { return ['ok' => false, 'errors' => ['Geçersiz durum.']]; }

    $ci = (!empty($d['check_in'])) ? (string) $d['check_in'] : null;
    $co = (!empty($d['check_out'])) ? (string) $d['check_out'] : null;
    $break = (int) ($d['break_minutes'] ?? 0); if ($break < 0) { $break = 0; }
    $work = (isset($d['work_minutes']) && $d['work_minutes'] !== '') ? (int) $d['work_minutes'] : null;
    if ($work === null && $ci && $co) { $work = attendance_minutes_between($ci, $co) - $break; if ($work < 0) { $work = 0; } }
    if ($work === null) { $work = 0; }
    $ot = (int) ($d['overtime_minutes'] ?? 0); if ($ot < 0) { $ot = 0; }
    $miss = (int) ($d['missing_minutes'] ?? 0); if ($miss < 0) { $miss = 0; }
    $note = trim((string) ($d['note'] ?? ''));
    $lrid = (isset($d['leave_request_id']) && $d['leave_request_id'] !== '') ? (int) $d['leave_request_id'] : null;

    try {
        db()->prepare('INSERT INTO attendance_records
            (personnel_id, attendance_date, status_id, check_in, check_out, break_minutes, work_minutes, overtime_minutes, missing_minutes, leave_request_id, note, created_by_user_id, updated_by_user_id)
            VALUES (:pid,:date,:sid,:ci,:co,:brk,:wm,:ot,:miss,:lrid,:note,:uid,:uid2)
            ON DUPLICATE KEY UPDATE status_id=VALUES(status_id), check_in=VALUES(check_in), check_out=VALUES(check_out),
                break_minutes=VALUES(break_minutes), work_minutes=VALUES(work_minutes), overtime_minutes=VALUES(overtime_minutes),
                missing_minutes=VALUES(missing_minutes), leave_request_id=VALUES(leave_request_id), note=VALUES(note), updated_by_user_id=VALUES(updated_by_user_id)')
            ->execute([':pid' => $pid, ':date' => $date, ':sid' => $sid, ':ci' => $ci, ':co' => $co, ':brk' => $break,
                       ':wm' => $work, ':ot' => $ot, ':miss' => $miss, ':lrid' => $lrid, ':note' => ($note !== '' ? $note : null), ':uid' => current_user_id(), ':uid2' => current_user_id()]);
        return ['ok' => true, 'errors' => []];
    } catch (Throwable $e) { log_error('save_attendance_record: ' . $e->getMessage()); return ['ok' => false, 'errors' => ['Kaydedilemedi.']]; }
}

/**
 * Toplu güncelleme.
 * $d: personnel_ids[], status_id, mode(range|weekends|holidays), date_from, date_to, year, month.
 * Var olan hücreleri de günceller (bilinçli işlem).
 */
function bulk_update_attendance(array $d): array
{
    $pids = array_values(array_unique(array_filter(array_map('intval', (array) ($d['personnel_ids'] ?? [])), static fn($v) => $v > 0)));
    $sid = (int) ($d['status_id'] ?? 0);
    $mode = (string) ($d['mode'] ?? 'range');
    if (empty($pids)) { return ['ok' => false, 'errors' => ['Personel seçilmedi.'], 'affected' => 0]; }
    $stMap = attendance_status_by_id_map(false);
    if (!isset($stMap[$sid])) { return ['ok' => false, 'errors' => ['Geçersiz durum.'], 'affected' => 0]; }

    $year = (int) ($d['year'] ?? date('Y'));
    $month = (int) ($d['month'] ?? date('n'));
    $b = attendance_month_bounds($year, $month);

    $from = $b['first']; $to = $b['last'];
    if ($mode === 'range') {
        $from = (!empty($d['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['date_from'])) ? (string) $d['date_from'] : $b['first'];
        $to   = (!empty($d['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['date_to'])) ? (string) $d['date_to'] : $b['last'];
        if ($to < $from) { [$from, $to] = [$to, $from]; }
    }

    $set = leave_settings();
    $weekend = $set['weekend_days'];
    $holidaySet = holiday_set_for_range($b['first'], $b['last']);

    // Hedef günleri belirle (ay içi)
    $targetDays = [];
    for ($day = 1; $day <= $b['days']; $day++) {
        $ymd = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $w = (int) date('w', mktime(0, 0, 0, $month, $day, $year));
        if ($mode === 'weekends' && !in_array($w, $weekend, true)) { continue; }
        if ($mode === 'holidays' && !isset($holidaySet[$ymd])) { continue; }
        if ($mode === 'range' && ($ymd < $from || $ymd > $to)) { continue; }
        $targetDays[] = $ymd;
    }
    if (empty($targetDays)) { return ['ok' => true, 'affected' => 0]; }

    $affected = 0;
    try {
        $ins = db()->prepare('INSERT INTO attendance_records (personnel_id, attendance_date, status_id, created_by_user_id, updated_by_user_id)
            VALUES (:pid,:date,:sid,:uid,:uid2)
            ON DUPLICATE KEY UPDATE status_id=VALUES(status_id), updated_by_user_id=VALUES(updated_by_user_id)');
        db()->beginTransaction();
        foreach ($pids as $pid) {
            foreach ($targetDays as $ymd) {
                $ins->execute([':pid' => $pid, ':date' => $ymd, ':sid' => $sid, ':uid' => current_user_id(), ':uid2' => current_user_id()]);
                $affected++;
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('bulk_update_attendance: ' . $e->getMessage());
        return ['ok' => false, 'errors' => ['Toplu işlem başarısız.'], 'affected' => 0];
    }
    log_activity('attendance_bulk_update', 'attendance', null, sprintf('%04d-%02d', $year, $month), 'success', 'mode=' . $mode . ' cells=' . $affected);
    return ['ok' => true, 'affected' => $affected];
}

/* =========================================================================
 |  İzin senkronizasyonu
 * ====================================================================== */
function leave_code_to_attendance_code(string $leaveCode): string
{
    return match ($leaveCode) {
        'annual'   => 'annual_leave',
        'unpaid'   => 'unpaid_leave',
        'sick'     => 'sick',
        'half_day' => 'half_day',
        default    => 'leave',
    };
}

/** Onaylanan izinleri puantaja yansıtır. Manuel girilmiş çakışan hücreleri EZMEZ, uyarı üretir. */
function sync_approved_leaves_to_attendance(int $year, int $month): array
{
    $b = attendance_month_bounds($year, $month);
    $requests = get_leave_requests(['status' => 'approved', 'date_from' => $b['first'], 'date_to' => $b['last']]);
    $codeToId = [];
    foreach (get_attendance_statuses(false) as $s) { $codeToId[(string) $s['code']] = (int) $s['id']; }

    $synced = 0; $skipped = 0; $conflicts = [];
    try {
        $sel = db()->prepare('SELECT id, status_id, leave_request_id FROM attendance_records WHERE personnel_id = :p AND attendance_date = :d LIMIT 1');
        $insUpd = db()->prepare('INSERT INTO attendance_records (personnel_id, attendance_date, status_id, leave_request_id, created_by_user_id, updated_by_user_id)
            VALUES (:pid,:date,:sid,:lrid,:uid,:uid2)
            ON DUPLICATE KEY UPDATE status_id=VALUES(status_id), leave_request_id=VALUES(leave_request_id), updated_by_user_id=VALUES(updated_by_user_id)');

        foreach ($requests as $req) {
            $pid = (int) $req['personnel_id'];
            $lrid = (int) $req['id'];
            $attCode = leave_code_to_attendance_code((string) ($req['type_code'] ?? ''));
            $targetSid = $codeToId[$attCode] ?? ($codeToId['leave'] ?? 0);
            if ($targetSid <= 0) { continue; }

            $s = max($req['start_date'], $b['first']);
            $e = min($req['end_date'], $b['last']);
            try { $cur = new DateTimeImmutable($s); $end = new DateTimeImmutable($e); } catch (Throwable $x) { continue; }
            $guard = 0;
            while ($cur <= $end && $guard < 400) {
                $guard++;
                $ymd = $cur->format('Y-m-d');
                $cur = $cur->modify('+1 day');
                $sel->execute([':p' => $pid, ':d' => $ymd]);
                $row = $sel->fetch();
                $doUpsert = false;
                if ($row) {
                    $existingLrid = $row['leave_request_id'] !== null ? (int) $row['leave_request_id'] : null;
                    if ($existingLrid === $lrid || (int) $row['status_id'] === $targetSid) {
                        $doUpsert = true; // aynı izin ya da aynı durum → güncelle/bağla
                    } else {
                        // Manuel farklı giriş → ezme, uyar
                        $skipped++;
                        $conflicts[] = ['personnel_id' => $pid, 'personnel_name' => (string) ($req['personnel_name'] ?? ''), 'date' => $ymd];
                    }
                } else {
                    $doUpsert = true;
                }
                if ($doUpsert) {
                    $insUpd->execute([':pid' => $pid, ':date' => $ymd, ':sid' => $targetSid, ':lrid' => $lrid, ':uid' => current_user_id(), ':uid2' => current_user_id()]);
                    $synced++;
                }
            }
        }
    } catch (Throwable $e) { log_error('sync_approved_leaves_to_attendance: ' . $e->getMessage()); return ['ok' => false, 'synced' => $synced, 'skipped' => $skipped, 'conflicts' => $conflicts]; }

    log_activity('attendance_sync_leaves', 'attendance', null, sprintf('%04d-%02d', $year, $month), 'success', 'synced=' . $synced . ' skipped=' . $skipped);
    return ['ok' => true, 'synced' => $synced, 'skipped' => $skipped, 'conflicts' => $conflicts];
}

/* =========================================================================
 |  Özetler
 * ====================================================================== */
function calculate_attendance_summary(int $personnelId, int $year, int $month): array
{
    $b = attendance_month_bounds($year, $month);
    $stMap = attendance_status_by_id_map(false);
    $sum = [
        'worked' => 0.0, 'leave' => 0, 'annual_leave' => 0, 'unpaid_leave' => 0, 'sick' => 0,
        'absent' => 0, 'half_day' => 0, 'week_off' => 0, 'holiday' => 0,
        'overtime_min' => 0, 'missing_min' => 0, 'work_min' => 0,
    ];
    try {
        $st = db()->prepare('SELECT status_id, work_minutes, overtime_minutes, missing_minutes FROM attendance_records
                             WHERE personnel_id = :p AND attendance_date BETWEEN :a AND :b');
        $st->execute([':p' => $personnelId, ':a' => $b['first'], ':b' => $b['last']]);
        foreach ($st->fetchAll() as $r) {
            $s = $stMap[(int) $r['status_id']] ?? null;
            $code = $s ? (string) $s['code'] : '';
            if ($code === 'half_day') { $sum['worked'] += 0.5; }
            elseif ($s && (int) $s['counts_as_workday'] === 1) { $sum['worked'] += 1; }
            if (isset($sum[$code])) { $sum[$code] += 1; }
            $sum['overtime_min'] += (int) $r['overtime_minutes'];
            $sum['missing_min']  += (int) $r['missing_minutes'];
            $sum['work_min']     += (int) $r['work_minutes'];
        }
    } catch (Throwable $e) { log_error('calculate_attendance_summary: ' . $e->getMessage()); }
    return $sum;
}

/** Sayfa üstü genel özet (seçili filtre + ay). */
function attendance_month_overview(int $year, int $month, array $filters = []): array
{
    $people = attendance_personnel_for_filters($filters);
    $out = ['personnel' => count($people), 'worked' => 0.0, 'annual_leave' => 0, 'absent' => 0, 'overtime_min' => 0, 'missing_min' => 0];
    foreach ($people as $p) {
        $s = calculate_attendance_summary((int) $p['id'], $year, $month);
        $out['worked'] += $s['worked'];
        $out['annual_leave'] += $s['annual_leave'];
        $out['absent'] += $s['absent'];
        $out['overtime_min'] += $s['overtime_min'];
        $out['missing_min'] += $s['missing_min'];
    }
    return $out;
}

function attendance_num(float $v): string
{
    return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
}
