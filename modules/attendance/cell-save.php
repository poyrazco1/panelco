<?php
declare(strict_types=1);

/**
 * modules/attendance/cell-save.php
 * Tek gün puantaj hücresini kaydeder (AJAX). POST + CSRF + login + yetki. JSON döner.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/attendance.php';

auth_boot();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$fail = static function (string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $fail('method_not_allowed', 405); }
if (!is_logged_in()) { $fail('unauthorized', 403); }
if (!can('attendance')) { $fail('forbidden', 403); }
if (!csrf_verify($_POST['_csrf'] ?? '')) { $fail('csrf_failed', 419); }

$res = save_attendance_record([
    'personnel_id'     => (int) ($_POST['personnel_id'] ?? 0),
    'attendance_date'  => (string) ($_POST['attendance_date'] ?? ''),
    'status_id'        => (int) ($_POST['status_id'] ?? 0),
    'check_in'         => (string) ($_POST['check_in'] ?? ''),
    'check_out'        => (string) ($_POST['check_out'] ?? ''),
    'break_minutes'    => (string) ($_POST['break_minutes'] ?? ''),
    'overtime_minutes' => (string) ($_POST['overtime_minutes'] ?? ''),
    'missing_minutes'  => (string) ($_POST['missing_minutes'] ?? ''),
    'note'             => (string) ($_POST['note'] ?? ''),
]);

if (!$res['ok']) { $fail(implode(' ', $res['errors']), 422); }

$sid = (int) ($_POST['status_id'] ?? 0);
$st = get_attendance_status($sid);
echo json_encode([
    'ok' => true,
    'status' => $st ? [
        'id'    => (int) $st['id'],
        'code'  => (string) $st['code'],
        'short' => attendance_short_label((string) $st['code'], (string) $st['name']),
        'color' => (string) ($st['color'] ?? ''),
        'name'  => (string) $st['name'],
    ] : null,
], JSON_UNESCAPED_UNICODE);
