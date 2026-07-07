<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/attendance.php';

auth_boot();
require_permission('attendance');

$year  = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? date('n'));
if ($month < 1 || $month > 12) { $month = (int) date('n'); }
if ($year < 2000 || $year > 2100) { $year = (int) date('Y'); }
$filters = [
    'department'   => (string) ($_GET['department'] ?? ''),
    'personnel_id' => (int) ($_GET['personnel_id'] ?? 0),
    'show'         => in_array(($_GET['show'] ?? 'active'), ['active', 'all', 'terminated'], true) ? (string) $_GET['show'] : 'active',
];

$data = get_month_attendance($year, $month, $filters);
$statusMap = attendance_status_by_id_map(false);
$overview = attendance_month_overview($year, $month, $filters);
$departments = personnel_departments();

$months = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
$qs = http_build_query(array_filter(['year' => $year, 'month' => $month, 'department' => $filters['department'], 'personnel_id' => $filters['personnel_id'] ?: null, 'show' => $filters['show']], static fn($v) => $v !== null && $v !== ''));

function att_full_summary(array $recs, array $statusMap): array
{
    $s = ['worked' => 0.0, 'annual_leave' => 0, 'unpaid_leave' => 0, 'sick' => 0, 'absent' => 0, 'week_off' => 0, 'holiday' => 0, 'ot' => 0, 'miss' => 0, 'work' => 0];
    foreach ($recs as $r) {
        $st = $statusMap[(int) $r['status_id']] ?? null;
        $code = $st ? (string) $st['code'] : '';
        if ($code === 'half_day') { $s['worked'] += 0.5; }
        elseif ($st && (int) $st['counts_as_workday'] === 1) { $s['worked'] += 1; }
        if (array_key_exists($code, $s) && is_int($s[$code])) { $s[$code] += 1; }
        $s['ot'] += (int) $r['overtime_minutes'];
        $s['miss'] += (int) $r['missing_minutes'];
        $s['work'] += (int) $r['work_minutes'];
    }
    return $s;
}

layout_top('Puantaj Raporları', 'attendance');
?>

<div class="page-head">
    <h1 class="page-title">Puantaj Raporları — <?= e($months[$month]) ?> <?= (int) $year ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/attendance/print.php?' . $qs)) ?>" target="_blank" rel="noopener"><?= icon('printer') ?>Yazdır</a>
        <a class="btn btn-sm" href="<?= e(url('modules/attendance/export.php?' . $qs)) ?>"><?= icon('download') ?>CSV</a>
        <a class="btn btn-sm" href="<?= e(url('modules/attendance/index.php?' . $qs)) ?>"><?= icon('calendar-days') ?>Puantaj Tablosu</a>
    </div>
</div>

<?= render_flashes() ?>

<div class="stat-grid">
    <div class="stat"><span class="stat-label">Toplam Personel</span><span class="stat-value"><?= (int) $overview['personnel'] ?></span></div>
    <div class="stat"><span class="stat-label">Bu Ay Çalışılan Gün</span><span class="stat-value"><?= e(attendance_num($overview['worked'])) ?></span></div>
    <div class="stat"><span class="stat-label">Toplam Yıllık İzin</span><span class="stat-value"><?= (int) $overview['annual_leave'] ?></span></div>
    <div class="stat"><span class="stat-label">Toplam Eksik Gün</span><span class="stat-value"><?= (int) $overview['absent'] ?></span></div>
    <div class="stat"><span class="stat-label">Toplam Fazla Mesai</span><span class="stat-value"><?= e(attendance_minutes_to_hm((int) $overview['overtime_min'])) ?></span></div>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="<?= e(url('modules/attendance/reports.php')) ?>" class="toolbar">
            <div class="form-group">
                <label for="fl-month">Ay</label>
                <select id="fl-month" name="month"><?php foreach ($months as $mn => $ml): ?><option value="<?= $mn ?>"<?= $month === $mn ? ' selected' : '' ?>><?= e($ml) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group">
                <label for="fl-year">Yıl</label>
                <select id="fl-year" name="year"><?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 5; $y--): ?><option value="<?= $y ?>"<?= $year === $y ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
            </div>
            <div class="form-group">
                <label for="fl-dep">Departman</label>
                <select id="fl-dep" name="department"><option value="">Tümü</option><?php foreach ($departments as $dep): ?><option value="<?= e($dep) ?>"<?= $filters['department'] === $dep ? ' selected' : '' ?>><?= e($dep) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group">
                <label for="fl-show">Göster</label>
                <select id="fl-show" name="show">
                    <option value="active"<?= $filters['show'] === 'active' ? ' selected' : '' ?>>Aktif personeller</option>
                    <option value="all"<?= $filters['show'] === 'all' ? ' selected' : '' ?>>Tüm personeller</option>
                    <option value="terminated"<?= $filters['show'] === 'terminated' ? ' selected' : '' ?>>Ayrılanlar dahil</option>
                </select>
            </div>
            <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Göster</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($data['personnel'])): ?>
            <p class="muted">Kayıt bulunamadı.</p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Personel</th><th>Departman</th>
                        <th class="num">Çalışılan</th><th class="num">Yıllık İzin</th><th class="num">Ücretsiz</th>
                        <th class="num">Rapor</th><th class="num">Gelmedi</th><th class="num">Hafta T.</th><th class="num">Resmi T.</th>
                        <th class="num">Fazla Mesai</th><th class="num">Eksik</th><th class="num">Toplam Çalışma</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['personnel'] as $p): $pid = (int) $p['id']; $s = att_full_summary($data['records'][$pid] ?? [], $statusMap); ?>
                    <tr>
                        <td><strong><?= e((string) $p['full_name']) ?></strong></td>
                        <td><?= e((string) ($p['department'] ?? '')) ?></td>
                        <td class="num"><?= e(attendance_num($s['worked'])) ?></td>
                        <td class="num"><?= (int) $s['annual_leave'] ?></td>
                        <td class="num"><?= (int) $s['unpaid_leave'] ?></td>
                        <td class="num"><?= (int) $s['sick'] ?></td>
                        <td class="num"><?= (int) $s['absent'] ?></td>
                        <td class="num"><?= (int) $s['week_off'] ?></td>
                        <td class="num"><?= (int) $s['holiday'] ?></td>
                        <td class="num"><?= $s['ot'] > 0 ? e(attendance_minutes_to_hm((int) $s['ot'])) : '—' ?></td>
                        <td class="num"><?= $s['miss'] > 0 ? e(attendance_minutes_to_hm((int) $s['miss'])) : '—' ?></td>
                        <td class="num"><strong><?= e(attendance_minutes_to_hm((int) $s['work'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
layout_bottom();
