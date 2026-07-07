<?php
declare(strict_types=1);

/**
 * api/dashboard-layout.php
 * Kullanıcının dashboard düzenini kaydeder/sıfırlar/getirir.
 * POST + CSRF + login zorunlu. Yalnızca kendi user_id. Widget ID + size whitelist
 * normalize_dashboard_layout() içinde uygulanır. JSON döner.
 */
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dashboard.php';

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
if (!csrf_verify($_POST['_csrf'] ?? '')) { $fail('csrf_failed', 419); }

$uid = current_user_id();
if (!$uid) { $fail('unauthorized', 403); }

$action = (string) ($_POST['action'] ?? '');

if ($action === 'save_layout') {
    $raw = (string) ($_POST['layout'] ?? '');
    $dec = json_decode($raw, true);
    if (!is_array($dec)) { $fail('invalid_json'); }
    if (!save_dashboard_layout($uid, $dec)) { $fail('save_failed', 500); }
    echo json_encode(['ok' => true, 'layout' => ['widgets' => get_dashboard_layout($uid)]], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'reset_layout') {
    reset_dashboard_layout($uid);
    echo json_encode(['ok' => true, 'layout' => ['widgets' => get_dashboard_layout($uid)]], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_layout') {
    echo json_encode(['ok' => true, 'layout' => ['widgets' => get_dashboard_layout($uid)]], JSON_UNESCAPED_UNICODE);
    exit;
}

$fail('unknown_action');
