<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/attendance.php';

auth_boot();
require_permission('attendance');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/attendance/index.php'); }
csrf_check();

$res = bulk_update_attendance([
    'personnel_ids' => (array) ($_POST['personnel_ids'] ?? []),
    'status_id'     => (int) ($_POST['status_id'] ?? 0),
    'mode'          => (string) ($_POST['mode'] ?? 'range'),
    'date_from'     => (string) ($_POST['date_from'] ?? ''),
    'date_to'       => (string) ($_POST['date_to'] ?? ''),
    'year'          => (int) ($_POST['year'] ?? date('Y')),
    'month'         => (int) ($_POST['month'] ?? date('n')),
]);
$res['ok'] ? flash('success', ($res['affected'] ?? 0) . ' hücre güncellendi.') : flash('error', implode(' ', $res['errors'] ?? ['Başarısız.']));

$redirect = (string) ($_POST['redirect'] ?? '');
http_response_code(303);
redirect('modules/attendance/index.php' . ($redirect !== '' ? '?' . $redirect : ''));
