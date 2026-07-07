<?php
declare(strict_types=1);

/**
 * cron/daily-notifications.php
 * Günlük İK bildirimlerini (doğum günü, yaklaşan doğum günü, yıl dönümü,
 * yaklaşan yıl dönümü, bugünkü/yaklaşan izinler) TÜM aktif kullanıcılar için üretir.
 *
 * Güvenlik: token zorunlu. Token, İK Bildirim Ayarları sayfasında görünür.
 * Kullanım (Plesk Scheduled Task):
 *   php /var/www/vhosts/<domain>/httpdocs/cron/daily-notifications.php TOKEN
 * veya HTTP:
 *   https://<domain>/cron/daily-notifications.php?token=TOKEN
 */

require_once __DIR__ . '/../includes/notifications.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) { header('Content-Type: text/plain; charset=utf-8'); }

$token = $isCli ? (string) ($argv[1] ?? '') : (string) ($_GET['token'] ?? '');
$expected = (string) notif_setting_get('cron_token', '');

if ($expected === '') {
    if (!$isCli) { http_response_code(503); }
    echo "cron_token ayarlanmamış. İK Bildirim Ayarları sayfasını bir kez açın.\n";
    exit;
}
if (!hash_equals($expected, $token)) {
    if (!$isCli) { http_response_code(403); }
    echo "forbidden\n";
    exit;
}

try {
    $count = generate_daily_hr_notifications();
    echo 'ok: ' . $count . ' yeni bildirim üretildi (' . date('Y-m-d H:i:s') . ")\n";
} catch (Throwable $e) {
    log_error('cron daily-notifications: ' . $e->getMessage());
    if (!$isCli) { http_response_code(500); }
    echo "hata: işlem tamamlanamadı\n";
}
