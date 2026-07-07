<?php
declare(strict_types=1);

/**
 * modules/notifications/celebrate.php
 * Kutlama işlemleri: mail (gönder+logla), mark (kutlandı işaretle+logla).
 * WhatsApp doğrudan wa.me linkiyle (bu sayfa üzerinden değil) açılır.
 * POST + CSRF + login zorunlu.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/notifications.php';

auth_boot();
require_permission('dashboard');

// Kutlama sonrası dönülecek güvenli hedef (yalnızca whitelist).
$returnTargets = [
    'dashboard'     => 'dashboard.php',
    'notifications' => 'modules/notifications/index.php',
];
$returnTo = $returnTargets[(string) ($_POST['return'] ?? '')] ?? 'modules/notifications/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect($returnTo); }
csrf_check();

$action = (string) ($_POST['action'] ?? '');
$type   = (string) ($_POST['type'] ?? '');
$pid    = (int) ($_POST['personnel_id'] ?? 0);
if (!in_array($type, ['birthday', 'anniversary'], true) || $pid <= 0) {
    flash('error', 'Geçersiz istek.');
    http_response_code(303);
    redirect($returnTo);
}

if ($action === 'mail') {
    $res = ($type === 'birthday') ? send_birthday_email($pid) : send_anniversary_email($pid);
    $res['ok'] ? flash('success', $res['msg']) : flash('error', $res['msg']);
} elseif ($action === 'mark') {
    $p = get_personnel_by_id($pid);
    log_notification([
        'notification_type' => $type, 'channel' => 'panel', 'personnel_id' => $pid,
        'recipient' => $p['full_name'] ?? null, 'subject' => 'Kutlandı olarak işaretlendi',
        'message' => null, 'status' => 'manual_opened',
    ]);
    flash('success', 'Kutlandı olarak işaretlendi.');
} else {
    flash('error', 'Bilinmeyen işlem.');
}

http_response_code(303);
redirect($returnTo);
