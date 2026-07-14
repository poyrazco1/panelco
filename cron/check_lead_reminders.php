<?php
declare(strict_types=1);

/**
 * cron/check_lead_reminders.php — Lead takip bildirim üreticisi (§21).
 *
 * Plesk cron örneği (dakikada bir):
 *   * * * * * /usr/bin/php /var/www/vhosts/DOMAIN/httpdocs/cron/check_lead_reminders.php
 *
 * Her çalıştığında:
 *   - Zamanı geçen açık takipleri "gecikmiş" işaretler.
 *   - Yaklaşan/zamanı gelen takipler için (bir kez) panel bildirimi üretir.
 *   - Gecikmiş takipler için gecikme bildirimi üretir.
 *   - Tamamlanmış/iptal edilmiş takipler, silinmiş lead'ler ve pasif/silinmiş
 *     kullanıcılar ATLANIR (SQL join'lerinde filtrelenir).
 *   - Aynı takip + aynı gün + aynı tür için mükerrer bildirim üretmez
 *     (user_notifications benzersiz index + notify_stage).
 *   - Sonucu cron loguna yazar.
 *
 * Güvenlik: yalnızca CLI'den çalışır; web erişimi reddedilir. İşlem kilidi
 * (flock) ile eş zamanlı iki çalıştırma engellenir; hata/çıkışta kilit güvenle
 * bırakılır (flock süreç bitince otomatik serbest kalır).
 */

// ---- 1) Yalnızca CLI ----
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bu betik yalnızca komut satırından (CLI) çalıştırılabilir.\n";
    exit(1);
}

$root = dirname(__DIR__);

// ---- 2) İşlem kilidi (eş zamanlı çalıştırmayı engelle) ----
$lockDir = $root . '/logs';
if (!is_dir($lockDir)) { @mkdir($lockDir, 0770, true); }
$lockFile = $lockDir . '/cron_lead_reminders.lock';
$lock = @fopen($lockFile, 'c');
if ($lock === false) {
    fwrite(STDERR, "Kilit dosyası açılamadı: $lockFile\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    // Zaten çalışıyor — sessizce çık (mükerrer çalışmayı önler)
    fwrite(STDOUT, date('c') . " check_lead_reminders: önceki çalışma sürüyor, atlandı.\n");
    fclose($lock);
    exit(0);
}

// flock süreç sonunda otomatik bırakılır; yine de garanti için kayıt fonksiyonu:
$releaseLock = static function () use (&$lock, $lockFile): void {
    if (is_resource($lock)) { @flock($lock, LOCK_UN); @fclose($lock); }
};
register_shutdown_function($releaseLock);

// ---- 3) Bootstrap ----
require_once $root . '/config.php';
require_once $root . '/includes/db.php';
require_once $root . '/includes/helpers.php';
require_once $root . '/includes/lead_reminders.php';
require_once $root . '/includes/lead_notifications.php';

$logFile = $lockDir . '/cron_lead_reminders.log';
$started = time();
$cronLog = static function (string $msg) use ($logFile): void {
    $line = date('Y-m-d H:i:s') . ' ' . $msg . "\n";
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    fwrite(STDOUT, $line);
};

$soonCount = 0; $overdueCount = 0; $markedOverdue = 0; $errors = 0;

try {
    // ---- 4) Durum bakımı: gecikmiş / yaklaşan işaretle ----
    $markedOverdue = lead_reminders_mark_overdue();
    lead_reminders_mark_upcoming(60);

    // ---- 5) Yaklaşan/zamanı gelen bildirimleri üret ----
    foreach (lead_reminders_needing_soon_notice(1000) as $r) {
        $uid = (int) $r['assigned_user_id'];
        $rid = (int) $r['id'];
        if ($uid <= 0) { lead_reminder_set_notify_stage($rid, 'soon'); continue; }
        $date = substr((string) $r['remind_at'], 0, 10);
        $url  = 'modules/leads/view.php?id=' . (int) $r['lead_id'] . '&tab=reminders';
        if (lead_notif_wants($uid, 'lead_followup_soon')) {
            $title = 'Yaklaşan lead takibi';
            $msg   = trim((string) $r['company_name']) . ' — ' . lead_reminder_type_label((string) $r['reminder_type'])
                   . ' · ' . (string) $r['remind_at'];
            if (lead_notification_push($uid, 'lead_followup_soon', $title, $msg, $rid, $url, $date)) { $soonCount++; }
        }
        // Bildirim üretilmese de aşamayı ilerlet (tercih kapalıysa tekrar denenmesin)
        lead_reminder_set_notify_stage($rid, 'soon');
    }

    // ---- 6) Gecikme bildirimleri ----
    foreach (lead_reminders_needing_overdue_notice(1000) as $r) {
        $uid = (int) $r['assigned_user_id'];
        $rid = (int) $r['id'];
        if ($uid <= 0) { lead_reminder_set_notify_stage($rid, 'overdue'); continue; }
        $date = substr((string) $r['remind_at'], 0, 10);
        $url  = 'modules/leads/view.php?id=' . (int) $r['lead_id'] . '&tab=reminders';
        if (lead_notif_wants($uid, 'lead_followup_overdue')) {
            $title = 'Gecikmiş lead takibi';
            $msg   = trim((string) $r['company_name']) . ' — belirlenen zaman geçti (' . (string) $r['remind_at'] . '). Lütfen işlem yapın.';
            if (lead_notification_push($uid, 'lead_followup_overdue', $title, $msg, $rid, $url, $date)) { $overdueCount++; }
        }
        lead_reminder_set_notify_stage($rid, 'overdue');
    }
} catch (Throwable $e) {
    $errors++;
    $cronLog('HATA: ' . $e->getMessage());
}

$elapsed = time() - $started;
$cronLog(sprintf('bitti · gecikmiş_işaret=%d · yaklaşan_bildirim=%d · gecikme_bildirim=%d · hata=%d · süre=%ds',
    $markedOverdue, $soonCount, $overdueCount, $errors, $elapsed));

$releaseLock();
exit($errors > 0 ? 1 : 0);
