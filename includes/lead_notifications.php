<?php
declare(strict_types=1);

/**
 * includes/lead_notifications.php — Lead takip bildirimleri + kullanıcı tercihleri (§21).
 *
 * Mevcut user_notifications tablosu ve notifications.php yardımcıları KORUNUR;
 * bu katman lead takip bildirimlerini üretir (dedup + action_url), kullanıcı
 * bildirim tercihlerini (user_preferences) yönetir ve polling için "sonra oluşan"
 * bildirimleri döndürür.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/preferences.php';

/** Lead takip bildirim türleri (dedup için ayrı ayrı kullanılır). */
function lead_notification_types(): array
{
    return ['lead_followup_soon', 'lead_followup_due', 'lead_followup_overdue', 'lead_followup_digest'];
}

/**
 * Bir kullanıcıya lead takip bildirimi ekler (mükerrer engelli: aynı kullanıcı +
 * tür + takip + tarih için tek kayıt). Eklendiyse true.
 */
function lead_notification_push(int $userId, string $type, string $title, string $message, int $reminderId, string $actionUrl, string $date): bool
{
    if ($userId <= 0) { return false; }
    try {
        $st = db()->prepare(
            'INSERT IGNORE INTO user_notifications
                (user_id, notification_type, title, message, related_type, related_id, action_url, notification_date)
             VALUES (:uid,:type,:title,:msg,\'lead_reminder\',:rid,:url,:ndate)'
        );
        $st->execute([
            ':uid' => $userId, ':type' => $type, ':title' => mb_substr($title, 0, 180),
            ':msg' => mb_substr($message, 0, 1000), ':rid' => $reminderId,
            ':url' => mb_substr($actionUrl, 0, 255), ':ndate' => $date,
        ]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) { log_error('lead_notification_push: ' . $e->getMessage()); return false; }
}

/**
 * Polling: kullanıcının belirtilen id'den SONRA oluşan, kapatılmamış bildirimleri.
 * Yalnızca kendi bildirimleri döner (yetki dışı lead sızmaz).
 */
function get_notifications_since(int $userId, int $afterId, int $limit = 20): array
{
    if ($userId <= 0) { return []; }
    try {
        $st = db()->prepare(
            'SELECT id, notification_type, title, message, action_url, related_type, related_id, is_read, created_at
             FROM user_notifications
             WHERE user_id = :uid AND is_deleted = 0 AND is_dismissed = 0 AND id > :after
             ORDER BY id ASC LIMIT ' . max(1, min(50, $limit))
        );
        $st->execute([':uid' => $userId, ':after' => $afterId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('get_notifications_since: ' . $e->getMessage()); return []; }
}

/** Kullanıcının en yüksek bildirim id'si (polling başlangıcı için). */
function latest_notification_id(int $userId): int
{
    try {
        $st = db()->prepare('SELECT COALESCE(MAX(id),0) FROM user_notifications WHERE user_id = :uid AND is_deleted = 0');
        $st->execute([':uid' => $userId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** Bildirimi kapatır (dismiss) — okundu'dan farklı; görevi kapatmaz. */
function dismiss_user_notification(int $notificationId, int $userId): bool
{
    try {
        db()->prepare('UPDATE user_notifications SET is_dismissed = 1, dismissed_at = NOW(), is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE id = :id AND user_id = :uid')
            ->execute([':id' => $notificationId, ':uid' => $userId]);
        return true;
    } catch (Throwable $e) { log_error('dismiss_user_notification: ' . $e->getMessage()); return false; }
}

/* ----------------------------------------------------------------------- *
 |  Kullanıcı bildirim tercihleri (user_preferences)
 * ----------------------------------------------------------------------- */

/** Varsayılan bildirim tercihleri. */
function lead_notif_pref_defaults(): array
{
    return [
        'lead_followup_enabled'   => 1, // Lead takip bildirimleri aktif
        'sound_enabled'           => 1, // Bildirim sesi
        'dashboard_enabled'       => 1, // Dashboard bildirimi
        'upcoming_enabled'        => 1, // Yaklaşan görev bildirimi
        'overdue_enabled'         => 1, // Geciken görev bildirimi
        'remind_before_minutes'   => 15, // Kaç dakika önce
        'daily_digest'            => 0, // Günlük takip özeti
        'show_pending_on_login'   => 1, // Girişte tamamlanmamışları göster
    ];
}

/** Kullanıcının bildirim tercihleri (varsayılanlarla birleşmiş). */
function lead_notif_prefs(int $userId): array
{
    $def = lead_notif_pref_defaults();
    if ($userId <= 0) { return $def; }
    $raw = get_user_preference($userId, 'lead_notif_prefs', null);
    if (is_string($raw) && $raw !== '') {
        $arr = json_decode($raw, true);
        if (is_array($arr)) {
            $out = $def;
            foreach ($def as $k => $v) {
                if (array_key_exists($k, $arr)) {
                    $out[$k] = $k === 'remind_before_minutes' ? (int) $arr[$k] : (int) (bool) $arr[$k];
                }
            }
            return $out;
        }
    }
    return $def;
}

/** Bildirim tercihlerini kaydeder. */
function lead_notif_prefs_save(int $userId, array $in): bool
{
    if ($userId <= 0) { return false; }
    $def = lead_notif_pref_defaults();
    $out = [];
    foreach ($def as $k => $v) {
        if ($k === 'remind_before_minutes') {
            $m = (int) ($in[$k] ?? $v);
            $out[$k] = array_key_exists($m, [0 => 1, 15 => 1, 30 => 1, 60 => 1, 1440 => 1]) ? $m : 15;
        } else {
            $out[$k] = !empty($in[$k]) ? 1 : 0;
        }
    }
    return set_user_preference($userId, 'lead_notif_prefs', json_encode($out, JSON_UNESCAPED_UNICODE));
}

/** Kısayol: kullanıcı bu bildirim türünü almak istiyor mu? */
function lead_notif_wants(int $userId, string $stage): bool
{
    $p = lead_notif_prefs($userId);
    if (empty($p['lead_followup_enabled'])) { return false; }
    return match ($stage) {
        'lead_followup_soon' => !empty($p['upcoming_enabled']),
        'lead_followup_overdue' => !empty($p['overdue_enabled']),
        default => true,
    };
}
