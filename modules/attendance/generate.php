<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/attendance.php';

auth_boot();
require_permission('attendance');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/attendance/index.php'); }
csrf_check();

$year  = (int) ($_POST['year'] ?? date('Y'));
$month = (int) ($_POST['month'] ?? date('n'));
$filters = [
    'department'   => (string) ($_POST['department'] ?? ''),
    'personnel_id' => (int) ($_POST['personnel_id'] ?? 0),
    'show'         => in_array(($_POST['show'] ?? 'active'), ['active', 'all', 'terminated'], true) ? (string) $_POST['show'] : 'active',
];
$res = create_month_attendance($year, $month, $filters);
$res['ok'] ? flash('success', ($res['created'] ?? 0) . ' gün oluşturuldu (mevcut kayıtlar korundu).') : flash('error', implode(' ', $res['errors'] ?? ['Oluşturulamadı.']));

$qs = http_build_query(array_filter(['year' => $year, 'month' => $month, 'department' => $filters['department'], 'personnel_id' => $filters['personnel_id'] ?: null, 'show' => $filters['show']], static fn($v) => $v !== null && $v !== ''));
http_response_code(303);
redirect('modules/attendance/index.php?' . $qs);
