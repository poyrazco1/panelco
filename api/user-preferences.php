<?php
declare(strict_types=1);

/**
 * api/user-preferences.php
 * Kullanıcı arayüz tercihlerini kaydeder. POST + CSRF + login zorunlu.
 * Yalnızca oturum açan kullanıcının kendi user_id'si için çalışır.
 * Action: set_theme | set_sidebar_state. JSON döner.
 */
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/preferences.php';

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
$value  = (string) ($_POST['value'] ?? '');

if ($action === 'set_theme') {
    if (!pref_is_valid_theme($value)) { $fail('invalid_value'); }
    if (!set_user_preference($uid, 'theme', $value)) { $fail('save_failed', 500); }
    echo json_encode(['ok' => true, 'theme' => $value], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'set_sidebar_state') {
    if (!pref_is_valid_sidebar($value)) { $fail('invalid_value'); }
    if (!set_user_preference($uid, 'sidebar_state', $value)) { $fail('save_failed', 500); }
    echo json_encode(['ok' => true, 'sidebar_state' => $value], JSON_UNESCAPED_UNICODE);
    exit;
}

$fail('unknown_action');
