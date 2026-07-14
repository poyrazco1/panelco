<?php
declare(strict_types=1);

/**
 * api/notifications.php — header bildirim dropdown + anlık polling (§21).
 * POST + login + CSRF. JSON döner. Kullanıcı bazlı rate limit uygulanır.
 * action: unread | mark_read(id) | mark_all | dismiss(id) | since(after)
 */
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/lead_notifications.php';

auth_boot();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$fail = static function (string $m, int $c = 400): void { http_response_code($c); echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE); exit; };

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $fail('method_not_allowed', 405); }
if (!is_logged_in()) { $fail('unauthorized', 403); }
if (!csrf_verify($_POST['_csrf'] ?? '')) { $fail('csrf_failed', 419); }

$uid = (int) (current_user_id() ?? 0);
$action = (string) ($_POST['action'] ?? '');

/* Kullanıcı bazlı basit rate limit: 10 saniyelik pencerede en fazla 20 istek. */
if (session_status() === PHP_SESSION_ACTIVE) {
    $now = time();
    $bucket = $_SESSION['_notif_rl'] ?? ['t' => $now, 'n' => 0];
    if ($now - (int) $bucket['t'] >= 10) { $bucket = ['t' => $now, 'n' => 0]; }
    $bucket['n']++;
    $_SESSION['_notif_rl'] = $bucket;
    if ((int) $bucket['n'] > 20) { $fail('rate_limited', 429); }
}

if ($action === 'mark_read') {
    mark_notification_read((int) ($_POST['id'] ?? 0), $uid);
    echo json_encode(['ok' => true, 'unread' => get_unread_notification_count($uid)], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'mark_all') {
    mark_all_notifications_read($uid);
    echo json_encode(['ok' => true, 'unread' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'dismiss') {
    dismiss_user_notification((int) ($_POST['id'] ?? 0), $uid);
    echo json_encode(['ok' => true, 'unread' => get_unread_notification_count($uid)], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'since') {
    // Anlık polling: yalnız kullanıcının, belirtilen id'den sonraki, kapatılmamış bildirimleri.
    $after = (int) ($_POST['after'] ?? 0);
    $rows = get_notifications_since($uid, $after, 20);
    $items = array_map(static function (array $n): array {
        return [
            'id'      => (int) $n['id'],
            'type'    => (string) $n['notification_type'],
            'title'   => (string) $n['title'],
            'message' => (string) $n['message'],
            'url'     => (string) ($n['action_url'] ?? ''),
        ];
    }, $rows);
    $prefs = lead_notif_prefs($uid);
    echo json_encode([
        'ok' => true,
        'unread' => get_unread_notification_count($uid),
        'items' => $items,
        'latest' => $items ? (int) end($items)['id'] : $after,
        'sound' => !empty($prefs['sound_enabled']),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'unread') {
    echo json_encode(['ok' => true, 'unread' => get_unread_notification_count($uid)], JSON_UNESCAPED_UNICODE);
    exit;
}
$fail('unknown_action');
