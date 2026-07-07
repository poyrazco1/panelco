<?php
declare(strict_types=1);

/**
 * modules/attendance/print.php
 * Aylık puantajın yazdırılabilir görünümü (bağımsız sayfa, app shell'e bağlı değil).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/attendance.php';
require_once __DIR__ . '/../../includes/service.php';

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
$statuses = get_attendance_statuses(true);
$days = $data['bounds']['days'];
$dayMeta = $data['day_meta'];
$company = service_company_info();
$logo = function_exists('pub_logo_url') ? pub_logo_url() : null;
$months = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];

function attp_row(array $recs, array $statusMap): array
{
    $s = ['worked' => 0.0, 'annual' => 0, 'sick' => 0, 'absent' => 0, 'ot' => 0, 'miss' => 0];
    foreach ($recs as $r) {
        $st = $statusMap[(int) $r['status_id']] ?? null; $code = $st ? (string) $st['code'] : '';
        if ($code === 'half_day') { $s['worked'] += 0.5; }
        elseif ($st && (int) $st['counts_as_workday'] === 1) { $s['worked'] += 1; }
        if ($code === 'annual_leave') { $s['annual']++; }
        if ($code === 'sick') { $s['sick']++; }
        if ($code === 'absent') { $s['absent']++; }
        $s['ot'] += (int) $r['overtime_minutes']; $s['miss'] += (int) $r['missing_minutes'];
    }
    return $s;
}
$num = static fn(float $v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Puantaj — <?= e($months[$month]) ?> <?= (int) $year ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: #1f2937; margin: 24px; font-size: 12px; }
    .p-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 14px; }
    .p-head h1 { font-size: 18px; margin: 0 0 4px; }
    .p-head .sub { color: #6b7280; font-size: 12px; }
    .p-logo img { max-height: 54px; max-width: 200px; }
    .p-company { font-weight: 600; font-size: 14px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #d1d5db; padding: 3px 4px; text-align: center; }
    th { background: #f3f4f6; font-weight: 600; }
    td.name, th.name { text-align: left; white-space: nowrap; font-weight: 600; }
    td.we, th.we { background: #f3f4f6; }
    td.ho, th.ho { background: #fef9e7; }
    .sum { background: #fafafa; font-weight: 600; }
    .legend { margin-top: 12px; font-size: 11px; color: #374151; }
    .legend span { display: inline-block; margin-right: 12px; }
    .chip { display: inline-block; min-width: 20px; padding: 1px 4px; border: 1px solid #d1d5db; border-radius: 3px; text-align: center; }
    .sign { margin-top: 40px; display: flex; gap: 60px; }
    .sign div { flex: 1; border-top: 1px solid #9ca3af; padding-top: 6px; text-align: center; color: #374151; }
    .toolbar-print { margin-bottom: 14px; }
    .btn { display: inline-block; padding: 8px 14px; background: #111827; color: #fff; border: 0; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 13px; }
    @media print { .toolbar-print { display: none; } body { margin: 8mm; } @page { size: landscape; } }
</style>
</head>
<body>
<div class="toolbar-print">
    <button class="btn" onclick="window.print()">Yazdır</button>
</div>

<div class="p-head">
    <div>
        <?php if (!empty($company['company_name'])): ?><div class="p-company"><?= e((string) $company['company_name']) ?></div><?php endif; ?>
        <h1>Aylık Puantaj Cetveli</h1>
        <div class="sub"><?= e($months[$month]) ?> <?= (int) $year ?><?= $filters['department'] !== '' ? ' · ' . e($filters['department']) : '' ?></div>
    </div>
    <?php if ($logo): ?><div class="p-logo"><img src="<?= e($logo) ?>" alt=""></div><?php endif; ?>
</div>

<?php if (empty($data['personnel'])): ?>
    <p>Kayıt bulunamadı.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th class="name">Personel</th>
            <?php for ($d = 1; $d <= $days; $d++): $m = $dayMeta[$d]; ?>
                <th class="<?= $m['weekend'] ? 'we' : '' ?><?= $m['holiday'] ? 'ho' : '' ?>"><?= $d ?></th>
            <?php endfor; ?>
            <th>Çal.</th><th>Yİ</th><th>R</th><th>G</th><th>FM</th><th>Eks.</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($data['personnel'] as $p): $pid = (int) $p['id']; $recs = $data['records'][$pid] ?? []; $s = attp_row($recs, $statusMap); ?>
        <tr>
            <td class="name"><?= e((string) $p['full_name']) ?></td>
            <?php for ($d = 1; $d <= $days; $d++):
                $m = $dayMeta[$d]; $rec = $recs[$d] ?? null;
                $st = $rec ? ($statusMap[(int) $rec['status_id']] ?? null) : null;
                $cls = $m['weekend'] ? 'we' : ''; if ($m['holiday']) { $cls = 'ho'; }
            ?>
                <td class="<?= $cls ?>"><?= $st ? e((string) $st['short']) : '' ?></td>
            <?php endfor; ?>
            <td class="sum"><?= e($num($s['worked'])) ?></td>
            <td class="sum"><?= (int) $s['annual'] ?></td>
            <td class="sum"><?= (int) $s['sick'] ?></td>
            <td class="sum"><?= (int) $s['absent'] ?></td>
            <td class="sum"><?= $s['ot'] > 0 ? e(attendance_minutes_to_hm((int) $s['ot'])) : '—' ?></td>
            <td class="sum"><?= $s['miss'] > 0 ? e(attendance_minutes_to_hm((int) $s['miss'])) : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="legend">
    <?php foreach ($statuses as $s): ?>
        <span><span class="chip" style="background:<?= e((string) ($s['color'] ?? '#eef2f7')) ?>"><?= e((string) $s['short']) ?></span> <?= e((string) $s['name']) ?></span>
    <?php endforeach; ?>
</div>

<div class="sign">
    <div>Hazırlayan</div>
    <div>Onaylayan</div>
</div>
<?php endif; ?>

</body>
</html>
