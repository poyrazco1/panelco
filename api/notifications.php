<?php
declare(strict_types=1);

/**
 * api/notifications.php — header bildirim dropdown için.
 * POST + login + CSRF. action: mark_read(id) | mark_all | unread. JSON döner.
 */
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notifications.php';

auth_boot();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$fail = static function (string $m, int $c = 400): void { http_response_code($c); echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE); exit; };

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $fail('method_not_allowed', 405); }
if (!is_logged_in()) { $fail('unauthorized', 403); }
if (!csrf_verify($_POST['_csrf'] ?? '')) { $fail('csrf_failed', 419); }

$uid = (int) (current_user_id() ?? 0);
$action = (string) ($_POST['action'] ?? '');

if ($action === 'mark_read') { mark_notification_read((int) ($_POST['id'] ?? 0), $uid); }
elseif ($action === 'mark_all') { mark_all_notifications_read($uid); }
elseif ($action !== 'unread') { $fail('unknown_action'); }

echo json_encode(['ok' => true, 'unread' => get_unread_notification_count($uid)], JSON_UNESCAPED_UNICODE);
