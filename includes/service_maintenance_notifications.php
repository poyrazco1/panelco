<?php
declare(strict_types=1);

/**
 * includes/service_maintenance_notifications.php
 * Teknik Servis bakım hatırlatmaları için panel bildirim katmanı (§5).
 *
 * user_notifications tablosuna, ilgili servise atanmış kullanıcıya tekil satır
 * yazar (mükerrer engelleme: uniq_user_notif_dedupe + INSERT IGNORE, ayrıca
 * reminder.reminder_stage ilerletmesi). Bildirimin okunması bakım görevini
 * tamamlanmış saymaz (§18).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/preferences.php';

/** Bildirim türleri (dedupe/tercih için ayrık anahtarlar). */
function smaint_notification_types(): array
{
    return [
        'service_maintenance_soon'    => 'Yaklaşan bakım',
        'service_maintenance_due'     => 'Bakım zamanı',
        'service_maintenance_overdue' => 'Geciken bakım',
        'service_maintenance_digest'  => 'Bakım özeti',
    ];
}

/**
 * Tek kullanıcıya bakım bildirimi yazar. Yeni satır eklendiyse true döner
 * (mükerrer INSERT IGNORE ile bastırılır).
 */
function smaint_notification_push(int $userId, string $type, string $title, string $message, int $reminderId, string $actionUrl, string $date): bool
{
    if ($userId <= 0) { return false; }
    if (function_exists('notif_ensure_schema')) { notif_ensure_schema(); }
    try {
        $st = db()->prepare(
            'INSERT IGNORE INTO user_notifications
                (user_id, notification_type, title, message, related_type, related_id, action_url, notification_date)
             VALUES (:uid,:type,:title,:msg,\'service_maintenance\',:rid,:url,:ndate)'
        );
        $st->execute([
            ':uid' => $userId, ':type' => $type, ':title' => mb_substr($title, 0, 180),
            ':msg' => mb_substr($message, 0, 1000), ':rid' => $reminderId,
            ':url' => mb_substr($actionUrl, 0, 255), ':ndate' => $date,
        ]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        log_error('smaint_notification_push: ' . $e->getMessage());
        return false;
    }
}

/** Bir bakım kaydına ait açık bildirimleri kapatır (görev bittiğinde/iptalinde). */
function smaint_dismiss_notifications(int $reminderId): void
{
    if ($reminderId <= 0) { return; }
    try {
        db()->prepare(
            "UPDATE user_notifications
             SET is_dismissed = 1, dismissed_at = NOW(), is_read = 1, read_at = COALESCE(read_at, NOW())
             WHERE related_type = 'service_maintenance' AND related_id = :rid AND is_dismissed = 0"
        )->execute([':rid' => $reminderId]);
    } catch (Throwable $e) {
        log_error('smaint_dismiss_notifications: ' . $e->getMessage());
    }
}

/* ---- Kişisel bildirim tercihleri (user_preferences JSON) ---- */

function smaint_notif_pref_defaults(): array
{
    return [
        'maint_enabled'     => 1,
        'sound_enabled'     => 1,
        'dashboard_enabled' => 1,
        'soon_enabled'      => 1,
        'overdue_enabled'   => 1,
        'digest_enabled'    => 1,
    ];
}

function smaint_notif_prefs(int $userId): array
{
    $def = smaint_notif_pref_defaults();
    $raw = get_user_preference($userId, 'service_maint_prefs', null);
    if (is_string($raw) && $raw !== '') {
        $arr = json_decode($raw, true);
        if (is_array($arr)) {
            $out = $def;
            foreach ($def as $k => $v) {
                if (array_key_exists($k, $arr)) { $out[$k] = (int) (bool) $arr[$k]; }
            }
            return $out;
        }
    }
    return $def;
}

function smaint_notif_prefs_save(int $userId, array $in): bool
{
    $def = smaint_notif_pref_defaults();
    $out = [];
    foreach ($def as $k => $v) { $out[$k] = !empty($in[$k]) ? 1 : 0; }
    return set_user_preference($userId, 'service_maint_prefs', json_encode($out, JSON_UNESCAPED_UNICODE));
}

/** Kullanıcı bu aşama/tür için bildirim istiyor mu? */
function smaint_notif_wants(int $userId, string $stageOrType): bool
{
    $p = smaint_notif_prefs($userId);
    if (empty($p['maint_enabled'])) { return false; }
    // Aşama anahtarı geldiyse tür grubuna eşle.
    $overdue = in_array($stageOrType, ['o7', 'o30', 'service_maintenance_overdue'], true);
    if ($overdue) { return !empty($p['overdue_enabled']); }
    if ($stageOrType === 'service_maintenance_digest') { return !empty($p['digest_enabled']); }
    return !empty($p['soon_enabled']);
}
