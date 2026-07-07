<?php
declare(strict_types=1);

/**
 * modules/attendance/export.php
 * Aylık puantaj tablosunu CSV olarak dışa aktarır (Excel uyumlu, UTF-8 BOM).
 * 1–31 gün kolonları + toplamlar. Salt-okunur.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/attendance.php';

auth_boot();
require_permission('attendance');

$year  = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? date('n'));
if ($month < 1 || $month > 12) { $month = (int) date('n'); }
$filters = [
    'department'   => (string) ($_GET['department'] ?? ''),
    'personnel_id' => (int) ($_GET['personnel_id'] ?? 0),
    'show'         => in_array(($_GET['show'] ?? 'active'), ['active', 'all', 'terminated'], true) ? (string) $_GET['show'] : 'active',
];

$data = get_month_attendance($year, $month, $filters);
$statusMap = attendance_status_by_id_map(false);
$days = $data['bounds']['days'];

$filename = sprintf('puantaj-%04d-%02d.csv', $year, $month);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

$header = ['Personel', 'Departman', 'Ay/Yıl'];
for ($d = 1; $d <= $days; $d++) { $header[] = (string) $d; }
array_push($header, 'Çalışılan Gün', 'Yıllık İzin', 'Rapor', 'Gelmedi', 'Fazla Mesai (saat)', 'Eksik (saat)');
fputcsv($out, $header);

$hours = static fn(int $min) => number_format($min / 60, 2, '.', '');

foreach ($data['personnel'] as $p) {
    $pid = (int) $p['id'];
    $recs = $data['records'][$pid] ?? [];
    $worked = 0.0; $annual = 0; $sick = 0; $absent = 0; $ot = 0; $miss = 0;
    $row = [(string) $p['full_name'], (string) ($p['department'] ?? ''), sprintf('%02d/%04d', $month, $year)];
    for ($d = 1; $d <= $days; $d++) {
        $rec = $recs[$d] ?? null;
        if ($rec) {
            $st = $statusMap[(int) $rec['status_id']] ?? null;
            $code = $st ? (string) $st['code'] : '';
            $row[] = $st ? (string) $st['short'] : '';
            if ($code === 'half_day') { $worked += 0.5; }
            elseif ($st && (int) $st['counts_as_workday'] === 1) { $worked += 1; }
            if ($code === 'annual_leave') { $annual++; }
            if ($code === 'sick') { $sick++; }
            if ($code === 'absent') { $absent++; }
            $ot += (int) $rec['overtime_minutes'];
            $miss += (int) $rec['missing_minutes'];
        } else {
            $row[] = '';
        }
    }
    $row[] = rtrim(rtrim(number_format($worked, 2, '.', ''), '0'), '.');
    $row[] = (string) $annual;
    $row[] = (string) $sick;
    $row[] = (string) $absent;
    $row[] = $hours($ot);
    $row[] = $hours($miss);
    fputcsv($out, $row);
}
fclose($out);
exit;
