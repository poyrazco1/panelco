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
$statusFilter = (int) ($_GET['status'] ?? 0);

$data = get_month_attendance($year, $month, $filters);
$statuses = get_attendance_statuses(true);
$statusMap = attendance_status_by_id_map(false);
$overview = attendance_month_overview($year, $month, $filters);
$departments = personnel_departments();
$personnelOpts = get_personnel_options(false);

$months = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
           7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
$days = $data['bounds']['days'];
$dayMeta = $data['day_meta'];

$qsBase = ['year' => $year, 'month' => $month, 'department' => $filters['department'], 'personnel_id' => $filters['personnel_id'] ?: null, 'show' => $filters['show']];
$qs = http_build_query(array_filter($qsBase, static fn($v) => $v !== null && $v !== ''));

/** Satır özeti (mevcut kayıtlardan; ek sorgu yok). */
function att_row_summary(array $recs, array $statusMap): array
{
    $s = ['worked' => 0.0, 'annual_leave' => 0, 'sick' => 0, 'absent' => 0, 'ot' => 0, 'miss' => 0];
    foreach ($recs as $r) {
        $st = $statusMap[(int) $r['status_id']] ?? null;
        $code = $st ? (string) $st['code'] : '';
        if ($code === 'half_day') { $s['worked'] += 0.5; }
        elseif ($st && (int) $st['counts_as_workday'] === 1) { $s['worked'] += 1; }
        if ($code === 'annual_leave') { $s['annual_leave']++; }
        if ($code === 'sick') { $s['sick']++; }
        if ($code === 'absent') { $s['absent']++; }
        $s['ot'] += (int) $r['overtime_minutes'];
        $s['miss'] += (int) $r['missing_minutes'];
    }
    return $s;
}

layout_top('Puantaj', 'attendance');
?>

<div class="page-head">
    <h1 class="page-title">Puantaj — <?= e($months[$month]) ?> <?= (int) $year ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/attendance/print.php?' . $qs)) ?>" target="_blank" rel="noopener"><?= icon('printer') ?>Yazdır</a>
        <a class="btn btn-sm" href="<?= e(url('modules/attendance/export.php?' . $qs)) ?>"><?= icon('download') ?>CSV</a>
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
        <form method="get" action="<?= e(url('modules/attendance/index.php')) ?>" class="toolbar">
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
                <label for="fl-pers">Personel</label>
                <select id="fl-pers" name="personnel_id"><option value="0">Tümü</option><?php foreach ($personnelOpts as $pid => $pname): ?><option value="<?= (int) $pid ?>"<?= $filters['personnel_id'] === (int) $pid ? ' selected' : '' ?>><?= e($pname) ?></option><?php endforeach; ?></select>
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

        <div class="att-actions">
            <form method="post" action="<?= e(url('modules/attendance/generate.php')) ?>" style="display:inline" data-confirm="Bu ay için eksik günler varsayılan takvime göre oluşturulsun mu? (Mevcut kayıtlar korunur)">
                <?= csrf_field() ?>
                <input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $month ?>">
                <input type="hidden" name="department" value="<?= e($filters['department']) ?>"><input type="hidden" name="personnel_id" value="<?= (int) $filters['personnel_id'] ?>"><input type="hidden" name="show" value="<?= e($filters['show']) ?>">
                <button type="submit" class="btn btn-sm"><?= icon('refresh-cw') ?>Ayı Oluştur / Yenile</button>
            </form>
            <form method="post" action="<?= e(url('modules/attendance/sync-leaves.php')) ?>" style="display:inline" data-confirm="Onaylanan izinler bu aya işlensin mi? (Manuel girilen çakışan günler korunur)">
                <?= csrf_field() ?>
                <input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $month ?>">
                <input type="hidden" name="redirect" value="<?= e($qs) ?>">
                <button type="submit" class="btn btn-sm"><?= icon('calendar-check') ?>İzinleri Senkronize Et</button>
            </form>
            <button type="button" class="btn btn-sm" id="attBulkToggle"><?= icon('list-checks') ?>Toplu Durum Ata</button>
        </div>
    </div>
</div>

<?php if (empty($data['personnel'])): ?>
    <div class="card"><div class="card-body"><p class="muted">Personel bulunamadı. Filtreleri değiştirin veya Ayarlar → Personeller'den personel ekleyin.</p></div></div>
<?php else: ?>

<!-- Toplu işlem paneli -->
<form method="post" action="<?= e(url('modules/attendance/bulk.php')) ?>" id="attBulkForm" class="card att-bulk" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $month ?>">
    <input type="hidden" name="redirect" value="<?= e($qs) ?>">
    <div class="card-body">
        <div class="toolbar">
            <div class="form-group">
                <label for="bk-status">Durum</label>
                <select id="bk-status" name="status_id" required><?php foreach ($statuses as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e((string) $s['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group">
                <label for="bk-mode">Kapsam</label>
                <select id="bk-mode" name="mode">
                    <option value="range">Tarih aralığı</option>
                    <option value="weekends">Hafta sonları</option>
                    <option value="holidays">Resmi tatiller</option>
                </select>
            </div>
            <div class="form-group bk-range"><label for="bk-from">Başlangıç</label><input type="date" id="bk-from" name="date_from" value="<?= e($data['bounds']['first']) ?>"></div>
            <div class="form-group bk-range"><label for="bk-to">Bitiş</label><input type="date" id="bk-to" name="date_to" value="<?= e($data['bounds']['last']) ?>"></div>
            <div class="form-group"><button type="submit" class="btn btn-primary btn-sm">Seçili Personellere Uygula</button></div>
        </div>
        <p class="field-hint">Aşağıdaki tablodan personelleri işaretleyin. Seçili personellere, seçilen kapsamdaki günlere durum atanır (mevcut günler güncellenir).</p>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <div class="att-scroll">
            <table class="att-table">
                <thead>
                    <tr>
                        <th class="att-sticky att-th-name">
                            <label class="chk att-selall"><input type="checkbox" id="attSelectAll"> Personel</label>
                        </th>
                        <?php for ($d = 1; $d <= $days; $d++): $m = $dayMeta[$d]; ?>
                            <th class="att-day<?= $m['weekend'] ? ' is-weekend' : '' ?><?= $m['holiday'] ? ' is-holiday' : '' ?><?= $m['today'] ? ' is-today' : '' ?>" title="<?= e($m['ymd']) ?>">
                                <span class="att-dnum"><?= $d ?></span><span class="att-dw"><?= e($m['wlabel']) ?></span>
                            </th>
                        <?php endfor; ?>
                        <th class="att-sum">Çalışılan</th>
                        <th class="att-sum">Yıl.İzin</th>
                        <th class="att-sum">Rapor</th>
                        <th class="att-sum">Gelmedi</th>
                        <th class="att-sum">FM</th>
                        <th class="att-sum">Eksik</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['personnel'] as $p): $pid = (int) $p['id']; $recs = $data['records'][$pid] ?? []; $sum = att_row_summary($recs, $statusMap); ?>
                    <tr>
                        <td class="att-sticky att-td-name">
                            <label class="chk"><input type="checkbox" class="att-row-check" name="personnel_ids[]" value="<?= $pid ?>" form="attBulkForm"> <span><?= e((string) $p['full_name']) ?></span></label>
                            <?php if (!empty($p['department'])): ?><span class="att-dep"><?= e((string) $p['department']) ?></span><?php endif; ?>
                        </td>
                        <?php for ($d = 1; $d <= $days; $d++):
                            $m = $dayMeta[$d];
                            $rec = $recs[$d] ?? null;
                            $st = $rec ? ($statusMap[(int) $rec['status_id']] ?? null) : null;
                            $cls = 'att-cell';
                            if ($m['weekend']) { $cls .= ' is-weekend'; }
                            if ($m['holiday']) { $cls .= ' is-holiday'; }
                            if ($m['today']) { $cls .= ' is-today'; }
                            $style = ($st && !empty($st['color'])) ? ' style="background:' . e((string) $st['color']) . '"' : '';
                            $short = $st ? (string) $st['short'] : '';
                            $tip = $st ? (string) $st['name'] : 'Boş';
                        ?>
                            <td class="<?= $cls ?>"<?= $style ?>
                                data-pid="<?= $pid ?>" data-pname="<?= e((string) $p['full_name']) ?>" data-date="<?= e($m['ymd']) ?>" data-day="<?= $d ?>"
                                data-status="<?= $rec ? (int) $rec['status_id'] : '' ?>"
                                data-ci="<?= $rec && $rec['check_in'] ? e(substr((string) $rec['check_in'], 0, 5)) : '' ?>"
                                data-co="<?= $rec && $rec['check_out'] ? e(substr((string) $rec['check_out'], 0, 5)) : '' ?>"
                                data-break="<?= $rec ? (int) $rec['break_minutes'] : '' ?>"
                                data-ot="<?= $rec ? (int) $rec['overtime_minutes'] : '' ?>"
                                data-miss="<?= $rec ? (int) $rec['missing_minutes'] : '' ?>"
                                data-note="<?= $rec ? e((string) ($rec['note'] ?? '')) : '' ?>"
                                title="<?= e($tip) ?>"><?= e($short) ?></td>
                        <?php endfor; ?>
                        <td class="att-sum"><strong><?= e(attendance_num($sum['worked'])) ?></strong></td>
                        <td class="att-sum"><?= (int) $sum['annual_leave'] ?></td>
                        <td class="att-sum"><?= (int) $sum['sick'] ?></td>
                        <td class="att-sum"><?= (int) $sum['absent'] ?></td>
                        <td class="att-sum"><?= $sum['ot'] > 0 ? e(attendance_minutes_to_hm((int) $sum['ot'])) : '—' ?></td>
                        <td class="att-sum"><?= $sum['miss'] > 0 ? e(attendance_minutes_to_hm((int) $sum['miss'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="att-legend">
            <?php foreach ($statuses as $s): ?>
                <span class="att-leg"><span class="att-chip" style="background:<?= e((string) ($s['color'] ?? '#eef2f7')) ?>"><?= e((string) $s['short']) ?></span> <?= e((string) $s['name']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Hücre düzenleme modalı -->
<div class="att-modal-backdrop" id="attModal" hidden>
    <div class="att-modal" role="dialog" aria-modal="true" aria-labelledby="attModalTitle">
        <div class="att-modal-head">
            <h3 id="attModalTitle">Gün Düzenle</h3>
            <button type="button" class="att-modal-x" id="attModalClose" aria-label="Kapat"><?= icon('x') ?></button>
        </div>
        <form id="attCellForm">
            <input type="hidden" name="personnel_id" id="cf-pid">
            <input type="hidden" name="attendance_date" id="cf-date">
            <p class="att-modal-sub" id="attModalSub"></p>
            <div class="form-group">
                <label for="cf-status">Durum</label>
                <select id="cf-status" name="status_id" required><?php foreach ($statuses as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e((string) $s['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="cf-ci">Giriş</label><input type="time" id="cf-ci" name="check_in"></div>
                <div class="form-group"><label for="cf-co">Çıkış</label><input type="time" id="cf-co" name="check_out"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="cf-break">Mola (dk)</label><input type="number" id="cf-break" name="break_minutes" min="0"></div>
                <div class="form-group"><label for="cf-ot">Fazla Mesai (dk)</label><input type="number" id="cf-ot" name="overtime_minutes" min="0"></div>
                <div class="form-group"><label for="cf-miss">Eksik (dk)</label><input type="number" id="cf-miss" name="missing_minutes" min="0"></div>
            </div>
            <div class="form-group"><label for="cf-note">Açıklama</label><input type="text" id="cf-note" name="note"></div>
            <div class="att-modal-actions">
                <span class="att-modal-msg" id="attModalMsg"></span>
                <button type="button" class="btn btn-sm" id="attModalCancel">Vazgeç</button>
                <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
    window.ATT = {
        saveUrl: <?= json_encode(url('modules/attendance/cell-save.php'), JSON_UNESCAPED_UNICODE) ?>,
        csrf: window.CSRF_TOKEN || <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>,
        statuses: <?= json_encode(array_map(static fn($s) => ['id' => (int) $s['id'], 'code' => $s['code'], 'short' => $s['short'], 'color' => $s['color']], $statuses), JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<script src="<?= e(asset('js/attendance.js')) ?>"></script>

<?php endif; ?>

<?php
layout_bottom();
