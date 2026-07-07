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
$res = sync_approved_leaves_to_attendance($year, $month);

if (!empty($res['ok'])) {
    $msg = ($res['synced'] ?? 0) . ' gün izinden işlendi.';
    if (!empty($res['skipped'])) { $msg .= ' ' . (int) $res['skipped'] . ' gün manuel giriş nedeniyle atlandı (uyarı).'; }
    flash(!empty($res['skipped']) ? 'info' : 'success', $msg);
} else {
    flash('error', 'İzin senkronizasyonu başarısız.');
}

$redirect = (string) ($_POST['redirect'] ?? '');
http_response_code(303);
redirect('modules/attendance/index.php' . ($redirect !== '' ? '?' . $redirect : ''));
