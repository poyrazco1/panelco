<?php
declare(strict_types=1);

/**
 * cron/check_service_maintenance_reminders.php — Teknik Servis bakım hatırlatma
 * üreticisi (§12).
 *
 * Plesk cron örneği (günde bir, sabah 08:00):
 *   0 8 * * * /usr/bin/php /var/www/vhosts/DOMAIN/httpdocs/cron/check_service_maintenance_reminders.php
 *
 * Her çalıştığında:
 *   - Yaklaşan/bugün/geciken bakım kayıtlarının durumunu günceller.
 *   - Aşamalı (30/15/7 gün önce, bakım günü, 7/30 gün gecikme) panel bildirimi
 *     üretir; aynı aşama için mükerrer üretmez (reminder_stage + INSERT IGNORE).
 *   - "Yarın N bakım" günlük özet bildirimi üretir.
 *   - İptal/tamamlanmış kayıtları, silinmiş servisleri ve pasif kullanıcıları
 *     ATLAR. İletişim izni gerektiren otomatik e-posta ayrı ele alınır.
 *   - Sonucu service_maintenance_cron_logs tablosuna ve cron loguna yazar.
 *
 * Güvenlik: yalnızca CLI; web erişimi reddedilir. flock ile eş zamanlı ikinci
 * çalıştırma engellenir; süreç bitince kilit güvenle bırakılır.
 */

// ---- 1) Yalnızca CLI ----
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bu betik yalnızca komut satırından (CLI) çalıştırılabilir.\n";
    exit(1);
}

$root = dirname(__DIR__);

// ---- 2) İşlem kilidi ----
$lockDir = $root . '/logs';
if (!is_dir($lockDir)) { @mkdir($lockDir, 0770, true); }
$lockFile = $lockDir . '/cron_service_maintenance.lock';
$lock = @fopen($lockFile, 'c');
if ($lock === false) {
    fwrite(STDERR, "Kilit dosyası açılamadı: $lockFile\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, date('c') . " check_service_maintenance_reminders: önceki çalışma sürüyor, atlandı.\n");
    fclose($lock);
    exit(0);
}
$releaseLock = static function () use (&$lock): void {
    if (is_resource($lock)) { @flock($lock, LOCK_UN); @fclose($lock); }
};
register_shutdown_function($releaseLock);

// ---- 3) Bootstrap ----
require_once $root . '/config.php';
require_once $root . '/includes/db.php';
require_once $root . '/includes/helpers.php';
require_once $root . '/includes/service_maintenance.php';
require_once $root . '/includes/service_maintenance_notifications.php';
require_once $root . '/includes/service_maintenance_comm.php';

$logFile = $lockDir . '/cron_service_maintenance.log';
$started = microtime(true);
$cronLog = static function (string $msg) use ($logFile): void {
    $line = date('Y-m-d H:i:s') . ' ' . $msg . "\n";
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    fwrite(STDOUT, $line);
};

$today       = date('Y-m-d');
$markedOver  = 0; $markedToday = 0; $markedUpcoming = 0;
$notifs      = 0; $digest = 0; $errors = 0; $emailsSent = 0;

try {
    smaint_ensure_schema();

    if (!smaint_is_enabled()) {
        $cronLog('Bakım takip sistemi ayarlardan kapalı; atlandı.');
        smaint_cron_log_write(['detail' => 'system disabled']);
        $releaseLock();
        exit(0);
    }

    $s = smaint_settings();

    // Otomatik e-posta ölçütü (§6, §13): ayar açık + manuel onay gerekmiyor + kanal açık.
    // İzin kontrolü smaint_send_email içinde yapılır ($manual=false → izinsiz gönderilmez).
    $autoEmail = (int) ($s['auto_email_enabled'] ?? 0) === 1
        && (int) ($s['manual_email_approval'] ?? 1) === 0
        && (int) ($s['email_enabled'] ?? 1) === 1;
    $maxRem = (int) ($s['max_reminders'] ?? 3);
    $overdueReremind = (int) ($s['overdue_reremind'] ?? 1) === 1;
    $emailTplId = (int) ($s['email_template_id'] ?? 0) ?: null;

    // ---- 4) Durum bakımı ----
    $markedOver     = smaint_mark_overdue();
    $markedToday    = smaint_mark_today();
    $markedUpcoming = smaint_mark_upcoming(30);

    // ---- 5) Aşamalı bildirimler ----
    $order = smaint_stage_order();
    foreach (smaint_reminders_for_stage_notice(5000) as $r) {
        $rid = (int) $r['id'];
        $stage = smaint_applicable_stage((string) $r['maintenance_due_date'], $s, $today);
        if ($stage === null) { continue; }

        $lastIdx = array_search((string) $r['reminder_stage'], $order, true);
        $lastIdx = $lastIdx === false ? -1 : $lastIdx;
        $curIdx  = array_search($stage, $order, true);
        if ($curIdx === false || $curIdx <= $lastIdx) { continue; }

        $uid = (int) ($r['assigned_user_id'] ?? 0);
        $assigneeActive = (int) ($r['assignee_active'] ?? 0) === 1;
        if ($uid > 0 && $assigneeActive && smaint_notif_wants($uid, $stage)) {
            [$title, $msg, $type] = smaint_stage_message($r, $stage);
            if (smaint_notification_push($uid, $type, $title, $msg, $rid, smaint_view_url($rid), $today)) {
                $notifs++;
            }
        }
        // Otomatik e-posta: bakım günü ('due') ve (ayar açıksa) gecikme aşamalarında;
        // izinli müşteriye, maksimum hatırlatma sınırı aşılmadıysa.
        if ($autoEmail
            && ($stage === 'due' || (($stage === 'o7' || $stage === 'o30') && $overdueReremind))
            && ($maxRem <= 0 || (int) ($r['reminder_count'] ?? 0) < $maxRem)
            && (int) ($r['email_consent'] ?? 0) === 1
            && trim((string) ($r['email'] ?? '')) !== ''
        ) {
            $er = smaint_auto_send_email($rid, $emailTplId);
            if (!empty($er['ok'])) { $emailsSent++; }
        }

        // Bildirim üretilmese de aşamayı ilerlet (tekrar denenmesin).
        smaint_set_reminder_stage($rid, $stage);
    }

    // ---- 6) Günlük özet (yarın N bakım) ----
    foreach (smaint_due_tomorrow_by_user() as $row) {
        $uid = (int) $row['uid'];
        $c   = (int) $row['c'];
        if ($uid <= 0 || $c <= 0) { continue; }
        if (!smaint_notif_wants($uid, 'service_maintenance_digest')) { continue; }
        $title = 'Yarınki bakımlar';
        $msg   = 'Yarın ' . $c . ' teknik servis bakım takibi bulunuyor.';
        if (smaint_notification_push($uid, 'service_maintenance_digest', $title, $msg, 0, 'modules/maintenance/index.php', $today)) {
            $digest++;
        }
    }
} catch (Throwable $e) {
    $errors++;
    $cronLog('HATA: ' . $e->getMessage());
}

$elapsedMs = (int) round((microtime(true) - $started) * 1000);
smaint_cron_log_write([
    'upcoming_found' => $markedUpcoming,
    'due_marked'     => $markedToday,
    'overdue_marked' => $markedOver,
    'notifications_created' => $notifs + $digest,
    'emails_sent'    => $emailsSent,
    'errors'         => $errors,
    'duration_ms'    => $elapsedMs,
    'detail'         => sprintf('yaklasan=%d bugun=%d gecikti=%d bildirim=%d ozet=%d eposta=%d',
        $markedUpcoming, $markedToday, $markedOver, $notifs, $digest, $emailsSent),
]);

$cronLog(sprintf('bitti · yaklasan=%d · bugun=%d · gecikti=%d · bildirim=%d · ozet=%d · eposta=%d · hata=%d · sure=%dms',
    $markedUpcoming, $markedToday, $markedOver, $notifs, $digest, $emailsSent, $errors, $elapsedMs));

$releaseLock();
exit($errors > 0 ? 1 : 0);
