<?php
declare(strict_types=1);

/**
 * includes/leave.php
 * İK / Yıllık İzin Takibi motoru: izin türleri, çalışma takvimi/tatil ayarları,
 * gün hesabı, izin talepleri (onay/red/iptal), bakiye ve manuel düzeltmeler.
 * Tüm sorgular prepared; hatalar log'lanır, fatal atılmaz.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/personnel.php';

/* =========================================================================
 |  app_settings genel erişim (izin/çalışma takvimi ayarları)
 * ====================================================================== */
if (!function_exists('app_setting_get')) {
    function app_setting_get(string $key, ?string $default = null): ?string
    {
        try {
            $st = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :k LIMIT 1');
            $st->execute([':k' => $key]);
            $v = $st->fetchColumn();
            return $v === false ? $default : (string) $v;
        } catch (Throwable $e) { log_error('app_setting_get: ' . $e->getMessage()); return $default; }
    }
}
if (!function_exists('app_setting_set')) {
    function app_setting_set(string $key, string $value): bool
    {
        try {
            db()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
                ->execute([':k' => $key, ':v' => $value]);
            return true;
        } catch (Throwable $e) { log_error('app_setting_set: ' . $e->getMessage()); return false; }
    }
}

/** İzin / çalışma takvimi ayarları (ayarlardan düzenlenebilir; kod içine gömülü değil). */
function leave_settings(): array
{
    $wd = (string) app_setting_get('leave_weekend_days', '6,0');
    $weekend = array_values(array_filter(array_map('intval', array_filter(explode(',', $wd), static fn($x) => $x !== '')), static fn($v) => $v >= 0 && $v <= 6));
    return [
        'annual_default_days' => (float) app_setting_get('leave_annual_default_days', '14'),
        'exclude_weekends'    => app_setting_get('leave_exclude_weekends', '1') === '1',
        'exclude_holidays'    => app_setting_get('leave_exclude_holidays', '1') === '1',
        'weekend_days'        => $weekend,
    ];
}

function leave_settings_save(array $d): bool
{
    $days = (float) ($d['annual_default_days'] ?? 14);
    if ($days < 0) { $days = 0; }
    $weekend = $d['weekend_days'] ?? [];
    if (!is_array($weekend)) { $weekend = []; }
    $weekend = array_values(array_unique(array_filter(array_map('intval', $weekend), static fn($v) => $v >= 0 && $v <= 6)));
    $ok = true;
    $ok = app_setting_set('leave_annual_default_days', (string) $days) && $ok;
    $ok = app_setting_set('leave_exclude_weekends', !empty($d['exclude_weekends']) ? '1' : '0') && $ok;
    $ok = app_setting_set('leave_exclude_holidays', !empty($d['exclude_holidays']) ? '1' : '0') && $ok;
    $ok = app_setting_set('leave_weekend_days', implode(',', $weekend)) && $ok;
    return $ok;
}

function leave_weekday_labels(): array
{
    // PHP date('w'): 0=Pazar ... 6=Cumartesi
    return [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 0 => 'Pazar'];
}

/* =========================================================================
 |  Durumlar / etiketler
 * ====================================================================== */
function leave_statuses(): array
{
    return [
        'draft'     => 'Taslak',
        'pending'   => 'Onay Bekliyor',
        'approved'  => 'Onaylandı',
        'rejected'  => 'Reddedildi',
        'cancelled' => 'İptal Edildi',
    ];
}
function leave_status_label(string $k): string { return leave_statuses()[$k] ?? $k; }
function leave_status_class(string $k): string
{
    return match ($k) {
        'approved'  => 'badge-success',
        'rejected'  => 'badge-danger',
        'cancelled' => 'badge-muted',
        'pending'   => 'badge-leave',
        default     => 'badge-info',
    };
}
function leave_adjustment_labels(): array
{
    return ['add' => 'Ek izin', 'subtract' => 'İzin düş', 'carry_over' => 'Devreden izin', 'correction' => 'Devir düzeltmesi'];
}

/* =========================================================================
 |  İzin türleri
 * ====================================================================== */
function get_leave_types(bool $onlyActive = true): array
{
    try {
        $sql = 'SELECT * FROM leave_types';
        if ($onlyActive) { $sql .= ' WHERE is_active = 1'; }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return db()->query($sql)->fetchAll();
    } catch (Throwable $e) { log_error('get_leave_types: ' . $e->getMessage()); return []; }
}
function get_leave_type(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM leave_types WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    } catch (Throwable $e) { log_error('get_leave_type: ' . $e->getMessage()); return null; }
}
function leave_type_options(bool $onlyActive = true): array
{
    $out = [];
    foreach (get_leave_types($onlyActive) as $t) { $out[(int) $t['id']] = (string) $t['name']; }
    return $out;
}
function leave_type_code_exists(string $code, ?int $excludeId = null): bool
{
    try {
        $sql = 'SELECT COUNT(*) FROM leave_types WHERE code = :c';
        $p = [':c' => $code];
        if ($excludeId !== null) { $sql .= ' AND id <> :id'; $p[':id'] = $excludeId; }
        $st = db()->prepare($sql); $st->execute($p);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) { log_error('leave_type_code_exists: ' . $e->getMessage()); return false; }
}
function save_leave_type(array $d, ?int $id = null): array
{
    $name = trim((string) ($d['name'] ?? ''));
    $code = trim((string) ($d['code'] ?? ''));
    $code = $code !== '' ? preg_replace('/[^a-z0-9_]+/i', '_', strtolower($code)) : '';
    $errors = [];
    if ($name === '') { $errors[] = 'İzin türü adı gerekli.'; }
    if ($code === '') { $errors[] = 'Kod gerekli.'; }
    if ($code !== '' && leave_type_code_exists($code, $id)) { $errors[] = 'Bu kod zaten kullanılıyor.'; }
    if ($errors) { return ['ok' => false, 'errors' => $errors, 'id' => (int) $id]; }

    $params = [
        ':name'  => $name,
        ':code'  => $code,
        ':paid'  => !empty($d['is_paid']) ? 1 : 0,
        ':ded'   => !empty($d['deducts_annual_balance']) ? 1 : 0,
        ':hr'    => !empty($d['allow_hourly']) ? 1 : 0,
        ':half'  => !empty($d['allow_half_day']) ? 1 : 0,
        ':sort'  => (int) ($d['sort_order'] ?? 0),
        ':act'   => !empty($d['is_active']) ? 1 : 0,
    ];
    try {
        if ($id) {
            $params[':id'] = $id;
            db()->prepare('UPDATE leave_types SET name=:name, code=:code, is_paid=:paid, deducts_annual_balance=:ded,
                           allow_hourly=:hr, allow_half_day=:half, sort_order=:sort, is_active=:act WHERE id=:id')->execute($params);
            return ['ok' => true, 'errors' => [], 'id' => $id];
        }
        db()->prepare('INSERT INTO leave_types (name, code, is_paid, deducts_annual_balance, allow_hourly, allow_half_day, sort_order, is_active)
                       VALUES (:name,:code,:paid,:ded,:hr,:half,:sort,:act)')->execute($params);
        return ['ok' => true, 'errors' => [], 'id' => (int) db()->lastInsertId()];
    } catch (Throwable $e) {
        log_error('save_leave_type: ' . $e->getMessage());
        return ['ok' => false, 'errors' => ['Kaydedilemedi.'], 'id' => (int) $id];
    }
}
function toggle_leave_type(int $id, bool $active): bool
{
    try { db()->prepare('UPDATE leave_types SET is_active=:a WHERE id=:id')->execute([':a' => $active ? 1 : 0, ':id' => $id]); return true; }
    catch (Throwable $e) { log_error('toggle_leave_type: ' . $e->getMessage()); return false; }
}
function delete_leave_type(int $id): bool
{
    try { db()->prepare('DELETE FROM leave_types WHERE id=:id')->execute([':id' => $id]); return true; }
    catch (Throwable $e) { log_error('delete_leave_type: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  Resmi tatiller
 * ====================================================================== */
function get_company_holidays(?int $year = null, bool $onlyActive = false): array
{
    try {
        $sql = 'SELECT * FROM company_holidays';
        $cond = [];
        $p = [];
        if ($onlyActive) { $cond[] = 'is_active = 1'; }
        if ($year !== null) { $cond[] = '(is_recurring = 1 OR YEAR(holiday_date) = :y)'; $p[':y'] = $year; }
        if ($cond) { $sql .= ' WHERE ' . implode(' AND ', $cond); }
        $sql .= ' ORDER BY holiday_date ASC';
        $st = db()->prepare($sql); $st->execute($p);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_company_holidays: ' . $e->getMessage()); return []; }
}
/** Belirli aralıkta tatil günleri kümesi ['Y-m-d'=>true] (tekrarlayanlar genişletilir). */
function holiday_set_for_range(string $start, string $end): array
{
    $out = [];
    try {
        $rows = db()->query('SELECT holiday_date, is_recurring FROM company_holidays WHERE is_active = 1')->fetchAll();
    } catch (Throwable $e) { log_error('holiday_set_for_range: ' . $e->getMessage()); return []; }
    try { $s = new DateTimeImmutable($start); $e2 = new DateTimeImmutable($end); }
    catch (Throwable $x) { return []; }
    if ($e2 < $s) { return []; }
    $years = range((int) $s->format('Y'), (int) $e2->format('Y'));
    foreach ($rows as $r) {
        $d = (string) $r['holiday_date'];
        if (!empty($r['is_recurring'])) {
            $md = substr($d, 5); // MM-DD
            foreach ($years as $y) { $out[sprintf('%04d-%s', $y, $md)] = true; }
        } else {
            $out[$d] = true;
        }
    }
    return $out;
}
function save_holiday(array $d, ?int $id = null): array
{
    $date = trim((string) ($d['holiday_date'] ?? ''));
    $title = trim((string) ($d['title'] ?? ''));
    $errors = [];
    if ($date === '') { $errors[] = 'Tarih gerekli.'; }
    if ($title === '') { $errors[] = 'Başlık gerekli.'; }
    if ($errors) { return ['ok' => false, 'errors' => $errors]; }
    $p = [
        ':d'   => $date,
        ':t'   => $title,
        ':rec' => !empty($d['is_recurring']) ? 1 : 0,
        ':act' => !empty($d['is_active']) ? 1 : 0,
    ];
    try {
        if ($id) {
            $p[':id'] = $id;
            db()->prepare('UPDATE company_holidays SET holiday_date=:d, title=:t, is_recurring=:rec, is_active=:act WHERE id=:id')->execute($p);
        } else {
            db()->prepare('INSERT INTO company_holidays (holiday_date, title, is_recurring, is_active) VALUES (:d,:t,:rec,:act)
                           ON DUPLICATE KEY UPDATE title=VALUES(title), is_recurring=VALUES(is_recurring), is_active=VALUES(is_active)')->execute($p);
        }
        return ['ok' => true, 'errors' => []];
    } catch (Throwable $e) { log_error('save_holiday: ' . $e->getMessage()); return ['ok' => false, 'errors' => ['Kaydedilemedi.']]; }
}
function delete_holiday(int $id): bool
{
    try { db()->prepare('DELETE FROM company_holidays WHERE id=:id')->execute([':id' => $id]); return true; }
    catch (Throwable $e) { log_error('delete_holiday: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  Gün hesabı
 * ====================================================================== */
function calculate_leave_days(string $startDate, string $endDate, array $options = []): float
{
    $manual = $options['manual_days'] ?? null;
    if ($manual !== null && $manual !== '' && is_numeric($manual)) {
        $m = (float) $manual;
        return $m < 0 ? 0.0 : round($m, 2);
    }
    try { $s = new DateTimeImmutable($startDate); $e = new DateTimeImmutable($endDate); }
    catch (Throwable $x) { return 0.0; }
    if ($e < $s) { return 0.0; }

    $excludeWeekend = !empty($options['exclude_weekends']);
    $excludeHoliday = !empty($options['exclude_holidays']);
    $weekendDays    = $options['weekend_days'] ?? [];
    $isHalf         = !empty($options['half_day']);
    $holidaySet     = $excludeHoliday ? ($options['holiday_set'] ?? holiday_set_for_range($startDate, $endDate)) : [];

    $count = 0;
    $cur = $s;
    $guard = 0;
    while ($cur <= $e && $guard < 3660) {
        $guard++;
        $w = (int) $cur->format('w');
        $ymd = $cur->format('Y-m-d');
        $skip = false;
        if ($excludeWeekend && in_array($w, $weekendDays, true)) { $skip = true; }
        if (!$skip && $excludeHoliday && isset($holidaySet[$ymd])) { $skip = true; }
        if (!$skip) { $count++; }
        $cur = $cur->modify('+1 day');
    }
    $days = (float) $count;
    if ($isHalf && $days >= 1) { $days -= 0.5; }
    return round($days, 2);
}

/** Hesap detayı (kayıtta gösterim için): toplam gün, hafta sonu, tatil sayısı. */
function leave_days_breakdown(string $startDate, string $endDate, array $options = []): array
{
    try { $s = new DateTimeImmutable($startDate); $e = new DateTimeImmutable($endDate); }
    catch (Throwable $x) { return ['span' => 0, 'weekend' => 0, 'holiday' => 0, 'counted' => 0.0]; }
    if ($e < $s) { return ['span' => 0, 'weekend' => 0, 'holiday' => 0, 'counted' => 0.0]; }
    $weekendDays = $options['weekend_days'] ?? [];
    $holidaySet  = $options['holiday_set'] ?? holiday_set_for_range($startDate, $endDate);
    $span = 0; $we = 0; $ho = 0;
    $cur = $s; $guard = 0;
    while ($cur <= $e && $guard < 3660) {
        $guard++; $span++;
        $w = (int) $cur->format('w'); $ymd = $cur->format('Y-m-d');
        $isWe = in_array($w, $weekendDays, true);
        if ($isWe) { $we++; }
        if (!$isWe && isset($holidaySet[$ymd])) { $ho++; }
        $cur = $cur->modify('+1 day');
    }
    return ['span' => $span, 'weekend' => $we, 'holiday' => $ho, 'counted' => calculate_leave_days($startDate, $endDate, $options)];
}

/* =========================================================================
 |  Talep numarası
 * ====================================================================== */
function leave_generate_request_no(): string
{
    $y = date('Y');
    for ($i = 0; $i < 6; $i++) {
        $no = 'IZN-' . $y . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        try {
            $st = db()->prepare('SELECT COUNT(*) FROM leave_requests WHERE request_no = :n');
            $st->execute([':n' => $no]);
            if ((int) $st->fetchColumn() === 0) { return $no; }
        } catch (Throwable $e) { log_error('leave_generate_request_no: ' . $e->getMessage()); break; }
    }
    return 'IZN-' . $y . '-' . str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
}

/* =========================================================================
 |  Talepler (liste / tekil)
 * ====================================================================== */
function get_leave_requests(array $f = []): array
{
    $sql = "SELECT lr.*, p.full_name AS personnel_name, p.department AS department, p.personnel_code AS personnel_code,
                   lt.name AS type_name, lt.code AS type_code, lt.deducts_annual_balance AS type_deducts,
                   ru.username AS requester_name, au.username AS approver_name
            FROM leave_requests lr
            LEFT JOIN personnel p  ON p.id  = lr.personnel_id
            LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
            LEFT JOIN users ru ON ru.id = lr.requested_by_user_id
            LEFT JOIN users au ON au.id = lr.approved_by_user_id
            WHERE lr.is_deleted = 0";
    $p = [];
    if (!empty($f['personnel_id']))  { $sql .= ' AND lr.personnel_id = :pid'; $p[':pid'] = (int) $f['personnel_id']; }
    if (!empty($f['department']))    { $sql .= ' AND p.department = :dep';    $p[':dep'] = (string) $f['department']; }
    if (!empty($f['leave_type_id'])) { $sql .= ' AND lr.leave_type_id = :lt'; $p[':lt']  = (int) $f['leave_type_id']; }
    if (!empty($f['status']) && array_key_exists($f['status'], leave_statuses())) { $sql .= ' AND lr.status = :st'; $p[':st'] = (string) $f['status']; }
    if (!empty($f['approver_id']))   { $sql .= ' AND lr.approved_by_user_id = :apid'; $p[':apid'] = (int) $f['approver_id']; }
    if (!empty($f['date_from']))     { $sql .= ' AND lr.end_date >= :df';   $p[':df'] = (string) $f['date_from']; }
    if (!empty($f['date_to']))       { $sql .= ' AND lr.start_date <= :dt';  $p[':dt'] = (string) $f['date_to']; }
    if (!empty($f['this_month']))    { $sql .= ' AND YEAR(lr.start_date) = YEAR(CURDATE()) AND MONTH(lr.start_date) = MONTH(CURDATE())'; }
    if (!empty($f['this_year']))     { $sql .= ' AND YEAR(lr.start_date) = YEAR(CURDATE())'; }
    $search = trim((string) ($f['search'] ?? ''));
    if ($search !== '') {
        $sql .= ' AND (p.full_name LIKE :q1 OR p.personnel_code LIKE :q2 OR p.department LIKE :q3 OR lr.description LIKE :q4 OR lr.request_no LIKE :q5)';
        $like = '%' . $search . '%';
        $p[':q1'] = $like; $p[':q2'] = $like; $p[':q3'] = $like; $p[':q4'] = $like; $p[':q5'] = $like;
    }
    $sql .= ' ORDER BY lr.id DESC';
    try { $st = db()->prepare($sql); $st->execute($p); return $st->fetchAll(); }
    catch (Throwable $e) { log_error('get_leave_requests: ' . $e->getMessage()); return []; }
}
function get_personnel_leave_requests(int $personnelId, array $filters = []): array
{
    $filters['personnel_id'] = $personnelId;
    return get_leave_requests($filters);
}
function get_leave_request(int $id): ?array
{
    try {
        $st = db()->prepare("SELECT lr.*, p.full_name AS personnel_name, p.department AS department, p.personnel_code AS personnel_code,
                                    lt.name AS type_name, lt.code AS type_code, lt.allow_hourly, lt.allow_half_day, lt.deducts_annual_balance AS type_deducts,
                                    ru.username AS requester_name, au.username AS approver_name, rj.username AS rejecter_name
                             FROM leave_requests lr
                             LEFT JOIN personnel p  ON p.id  = lr.personnel_id
                             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                             LEFT JOIN users ru ON ru.id = lr.requested_by_user_id
                             LEFT JOIN users au ON au.id = lr.approved_by_user_id
                             LEFT JOIN users rj ON rj.id = lr.rejected_by_user_id
                             WHERE lr.id = :id AND lr.is_deleted = 0 LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch();
        return $r ?: null;
    } catch (Throwable $e) { log_error('get_leave_request: ' . $e->getMessage()); return null; }
}

/* =========================================================================
 |  Talep oluştur / güncelle
 * ====================================================================== */
function leave_prepare(array $d, ?array $existing = null): array
{
    $errors = [];
    $pid   = (int) ($d['personnel_id'] ?? 0);
    $ltid  = (int) ($d['leave_type_id'] ?? 0);
    $start = trim((string) ($d['start_date'] ?? ''));
    $end   = trim((string) ($d['end_date'] ?? ''));
    if ($pid <= 0)   { $errors[] = 'Personel seçilmedi.'; }
    if ($ltid <= 0)  { $errors[] = 'İzin türü seçilmedi.'; }
    if ($start === '') { $errors[] = 'Başlangıç tarihi gerekli.'; }
    if ($end === '')   { $errors[] = 'Bitiş tarihi gerekli.'; }
    if ($start !== '' && $end !== '' && $end < $start) { $errors[] = 'Bitiş tarihi başlangıçtan önce olamaz.'; }
    $type = $ltid > 0 ? get_leave_type($ltid) : null;
    if ($ltid > 0 && !$type) { $errors[] = 'İzin türü bulunamadı.'; }
    if ($errors) { return ['ok' => false, 'errors' => $errors, 'fields' => []]; }

    $set = leave_settings();
    $isHalf = !empty($d['is_half_day']) && !empty($type['allow_half_day']);
    $opts = [
        'exclude_weekends' => $set['exclude_weekends'],
        'exclude_holidays' => $set['exclude_holidays'],
        'weekend_days'     => $set['weekend_days'],
        'half_day'         => $isHalf,
        'manual_days'      => (isset($d['manual_days']) && $d['manual_days'] !== '') ? $d['manual_days'] : null,
    ];
    $days  = calculate_leave_days($start, $end, $opts);
    $hours = (isset($d['calculated_hours']) && $d['calculated_hours'] !== '' && is_numeric($d['calculated_hours'])) ? round((float) $d['calculated_hours'], 2) : null;
    $status = (string) ($d['status'] ?? 'pending');
    if (!array_key_exists($status, leave_statuses())) { $status = 'pending'; }
    $startTime = (!empty($type['allow_hourly']) && !empty($d['start_time'])) ? (string) $d['start_time'] : null;
    $endTime   = (!empty($type['allow_hourly']) && !empty($d['end_time']))   ? (string) $d['end_time']   : null;
    $desc = trim((string) ($d['description'] ?? ''));

    return ['ok' => true, 'errors' => [], 'fields' => [
        'personnel_id'   => $pid,
        'leave_type_id'  => $ltid,
        'start_date'     => $start,
        'end_date'       => $end,
        'start_time'     => $startTime,
        'end_time'       => $endTime,
        'calculated_days'=> $days,
        'calculated_hours'=> $hours,
        'is_half_day'    => $isHalf ? 1 : 0,
        'description'    => $desc !== '' ? $desc : null,
        'status'         => $status,
    ]];
}

function create_leave_request(array $d): array
{
    $prep = leave_prepare($d);
    if (!$prep['ok']) { return ['ok' => false, 'errors' => $prep['errors'], 'id' => 0]; }
    $f = $prep['fields'];
    try {
        $no = leave_generate_request_no();
        db()->prepare('INSERT INTO leave_requests
            (request_no, personnel_id, leave_type_id, start_date, end_date, start_time, end_time,
             calculated_days, calculated_hours, is_half_day, description, status, requested_by_user_id)
            VALUES (:no,:pid,:lt,:sd,:ed,:sti,:eti,:days,:hrs,:half,:desc,:st,:req)')
            ->execute([
                ':no' => $no, ':pid' => $f['personnel_id'], ':lt' => $f['leave_type_id'],
                ':sd' => $f['start_date'], ':ed' => $f['end_date'], ':sti' => $f['start_time'], ':eti' => $f['end_time'],
                ':days' => $f['calculated_days'], ':hrs' => $f['calculated_hours'], ':half' => $f['is_half_day'],
                ':desc' => $f['description'], ':st' => $f['status'], ':req' => current_user_id(),
            ]);
        $id = (int) db()->lastInsertId();
        log_activity('leave_request_create', 'leave_request', $id, $no);
        return ['ok' => true, 'errors' => [], 'id' => $id];
    } catch (Throwable $e) {
        log_error('create_leave_request: ' . $e->getMessage());
        return ['ok' => false, 'errors' => ['Kayıt oluşturulamadı.'], 'id' => 0];
    }
}

function update_leave_request(int $id, array $d): array
{
    $existing = get_leave_request($id);
    if (!$existing) { return ['ok' => false, 'errors' => ['Kayıt bulunamadı.'], 'id' => $id]; }
    $prep = leave_prepare($d, $existing);
    if (!$prep['ok']) { return ['ok' => false, 'errors' => $prep['errors'], 'id' => $id]; }
    $f = $prep['fields'];
    try {
        $f['id'] = $id;
        db()->prepare('UPDATE leave_requests SET personnel_id=:personnel_id, leave_type_id=:leave_type_id,
            start_date=:start_date, end_date=:end_date, start_time=:start_time, end_time=:end_time,
            calculated_days=:calculated_days, calculated_hours=:calculated_hours, is_half_day=:is_half_day,
            description=:description, status=:status WHERE id=:id')
            ->execute([
                ':personnel_id' => $f['personnel_id'], ':leave_type_id' => $f['leave_type_id'],
                ':start_date' => $f['start_date'], ':end_date' => $f['end_date'],
                ':start_time' => $f['start_time'], ':end_time' => $f['end_time'],
                ':calculated_days' => $f['calculated_days'], ':calculated_hours' => $f['calculated_hours'],
                ':is_half_day' => $f['is_half_day'], ':description' => $f['description'], ':status' => $f['status'], ':id' => $id,
            ]);
        // Onaylanmış izinde değişiklik yapıldıysa logla
        if (($existing['status'] ?? '') === 'approved') {
            log_activity('leave_request_edit_after_approve', 'leave_request', $id, (string) ($existing['request_no'] ?? ''));
        } else {
            log_activity('leave_request_update', 'leave_request', $id, (string) ($existing['request_no'] ?? ''));
        }
        return ['ok' => true, 'errors' => [], 'id' => $id];
    } catch (Throwable $e) {
        log_error('update_leave_request: ' . $e->getMessage());
        return ['ok' => false, 'errors' => ['Güncellenemedi.'], 'id' => $id];
    }
}

function approve_leave_request(int $requestId, int $userId): bool
{
    try {
        db()->prepare("UPDATE leave_requests SET status='approved', approved_by_user_id=:u, approved_at=NOW(),
                       rejected_by_user_id=NULL, rejected_at=NULL, reject_reason=NULL
                       WHERE id=:id AND is_deleted=0")->execute([':u' => $userId, ':id' => $requestId]);
        log_activity('leave_request_approved', 'leave_request', $requestId);
        return true;
    } catch (Throwable $e) { log_error('approve_leave_request: ' . $e->getMessage()); return false; }
}
function reject_leave_request(int $requestId, int $userId, string $reason): bool
{
    try {
        db()->prepare("UPDATE leave_requests SET status='rejected', rejected_by_user_id=:u, rejected_at=NOW(), reject_reason=:r
                       WHERE id=:id AND is_deleted=0")->execute([':u' => $userId, ':r' => ($reason !== '' ? $reason : null), ':id' => $requestId]);
        log_activity('leave_request_rejected', 'leave_request', $requestId);
        return true;
    } catch (Throwable $e) { log_error('reject_leave_request: ' . $e->getMessage()); return false; }
}
function cancel_leave_request(int $requestId, int $userId): bool
{
    try {
        db()->prepare("UPDATE leave_requests SET status='cancelled' WHERE id=:id AND is_deleted=0")->execute([':id' => $requestId]);
        log_activity('leave_request_cancelled', 'leave_request', $requestId);
        return true;
    } catch (Throwable $e) { log_error('cancel_leave_request: ' . $e->getMessage()); return false; }
}
function delete_leave_request(int $requestId, ?int $userId = null): bool
{
    try {
        db()->prepare('UPDATE leave_requests SET is_deleted=1 WHERE id=:id')->execute([':id' => $requestId]);
        log_activity('leave_request_delete', 'leave_request', $requestId, null, 'success');
        return true;
    } catch (Throwable $e) { log_error('delete_leave_request: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  Bakiye düzeltmeleri
 * ====================================================================== */
function create_leave_balance_adjustment(array $d): array
{
    $pid  = (int) ($d['personnel_id'] ?? 0);
    $type = (string) ($d['adjustment_type'] ?? '');
    $days = (float) ($d['days'] ?? 0);
    $errors = [];
    if ($pid <= 0) { $errors[] = 'Personel seçilmedi.'; }
    if (!array_key_exists($type, leave_adjustment_labels())) { $errors[] = 'Geçersiz düzeltme tipi.'; }
    if ($days == 0.0) { $errors[] = 'Gün sayısı 0 olamaz.'; }
    if ($errors) { return ['ok' => false, 'errors' => $errors]; }
    try {
        db()->prepare('INSERT INTO leave_balance_adjustments (personnel_id, adjustment_type, days, year, description, created_by_user_id)
                       VALUES (:p,:t,:d,:y,:desc,:u)')
            ->execute([
                ':p' => $pid, ':t' => $type, ':d' => round($days, 2), ':y' => (int) date('Y'),
                ':desc' => (trim((string) ($d['description'] ?? '')) !== '') ? trim((string) $d['description']) : null,
                ':u' => current_user_id(),
            ]);
        log_activity('leave_balance_adjustment', 'personnel', $pid, null, 'success', $type . ' ' . $days);
        return ['ok' => true, 'errors' => []];
    } catch (Throwable $e) { log_error('create_leave_balance_adjustment: ' . $e->getMessage()); return ['ok' => false, 'errors' => ['Düzeltme kaydedilemedi.']]; }
}
function get_personnel_adjustments(int $personnelId): array
{
    try {
        $st = db()->prepare('SELECT a.*, u.username AS created_by_name FROM leave_balance_adjustments a
                             LEFT JOIN users u ON u.id = a.created_by_user_id
                             WHERE a.personnel_id = :p ORDER BY a.id DESC');
        $st->execute([':p' => $personnelId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_personnel_adjustments: ' . $e->getMessage()); return []; }
}

/* =========================================================================
 |  Bakiye özeti
 * ====================================================================== */
function get_personnel_leave_summary(int $personnelId, ?int $year = null): array
{
    $year = $year ?? (int) date('Y');
    $set = leave_settings();
    $entitled = $set['annual_default_days'];
    $carry = 0.0; $manual = 0.0;

    try {
        $st = db()->prepare('SELECT adjustment_type, COALESCE(SUM(days),0) s FROM leave_balance_adjustments WHERE personnel_id = :p GROUP BY adjustment_type');
        $st->execute([':p' => $personnelId]);
        foreach ($st->fetchAll() as $r) {
            $t = (string) $r['adjustment_type']; $v = (float) $r['s'];
            if ($t === 'carry_over')       { $carry  += $v; }
            elseif ($t === 'add')          { $manual += $v; }
            elseif ($t === 'subtract')     { $manual -= $v; }
            elseif ($t === 'correction')   { $manual += $v; }
        }
    } catch (Throwable $e) { log_error('leave_summary adj: ' . $e->getMessage()); }

    $used = 0.0; $pending = 0.0; $last = null;
    try {
        $st = db()->prepare("SELECT lr.status, COALESCE(SUM(lr.calculated_days),0) s
                             FROM leave_requests lr JOIN leave_types lt ON lt.id = lr.leave_type_id
                             WHERE lr.is_deleted = 0 AND lr.personnel_id = :p AND lt.deducts_annual_balance = 1
                               AND YEAR(lr.start_date) = :y AND lr.status IN ('approved','pending')
                             GROUP BY lr.status");
        $st->execute([':p' => $personnelId, ':y' => $year]);
        foreach ($st->fetchAll() as $r) {
            if ($r['status'] === 'approved')     { $used = (float) $r['s']; }
            elseif ($r['status'] === 'pending')  { $pending = (float) $r['s']; }
        }
        $st2 = db()->prepare("SELECT MAX(end_date) FROM leave_requests WHERE is_deleted = 0 AND personnel_id = :p AND status = 'approved'");
        $st2->execute([':p' => $personnelId]);
        $mx = $st2->fetchColumn();
        $last = $mx ?: null;
    } catch (Throwable $e) { log_error('leave_summary req: ' . $e->getMessage()); }

    $remaining = $entitled + $carry + $manual - $used;
    return [
        'entitled'        => round($entitled, 2),
        'carry_over'      => round($carry, 2),
        'manual'          => round($manual, 2),
        'used'            => round($used, 2),
        'pending'         => round($pending, 2),
        'remaining'       => round($remaining, 2),
        'last_leave_date' => $last,
        'year'            => $year,
    ];
}

/** Personel belirli dönemde izin kullanmış mı? ('month' | 'year') */
function leave_personnel_used_in_period(int $personnelId, string $period): bool
{
    try {
        $cond = $period === 'month'
            ? 'YEAR(start_date)=YEAR(CURDATE()) AND MONTH(start_date)=MONTH(CURDATE())'
            : 'YEAR(start_date)=YEAR(CURDATE())';
        $st = db()->prepare("SELECT COUNT(*) FROM leave_requests WHERE is_deleted=0 AND personnel_id=:p AND status IN ('approved','pending') AND $cond");
        $st->execute([':p' => $personnelId]);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) { log_error('leave_used_in_period: ' . $e->getMessage()); return false; }
}

/** Yıllık izin takibi listesi: her (aktif) personel için özet. */
function get_all_personnel_leave_summaries(array $f = []): array
{
    $pf = ['active' => 'active', 'order' => 'name'];
    if (!empty($f['department'])) { $pf['department'] = (string) $f['department']; }
    if (!empty($f['search']))     { $pf['search'] = (string) $f['search']; }
    $people = get_personnel($pf);

    $year = (int) date('Y');
    $out = [];
    foreach ($people as $p) {
        $pid = (int) $p['id'];
        $sum = get_personnel_leave_summary($pid, $year);
        if (!empty($f['low_balance']) && $sum['remaining'] > 5) { continue; }
        if (!empty($f['this_month']) && !leave_personnel_used_in_period($pid, 'month')) { continue; }
        if (!empty($f['this_year']) && !leave_personnel_used_in_period($pid, 'year')) { continue; }
        $out[] = ['personnel' => $p, 'summary' => $sum];
    }
    return $out;
}
