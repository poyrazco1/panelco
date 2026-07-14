<?php
declare(strict_types=1);

/**
 * includes/lead_reminders.php — Lead takip (follow-up) yaşam döngüsü (§21).
 *
 * İş kuralları:
 *  - Takip gerektiren durumlar (lead_statuses.requires_followup) tarih/saat
 *    olmadan kaydedilemez → çağıran taraf lead_reminder_create ile birlikte çağırır.
 *  - Bildirimin okunması görevi KAPATMAZ; görev yalnız sonuç girilerek tamamlanır.
 *  - Zamanı geçen tamamlanmamış kayıt otomatik "gecikmiş" olur (cron + anlık sorgu).
 *  - Ertelemede eski bildirim pasifleşir, yeni zamana göre yeniden planlanır.
 *  - Tüm oluşturma/erteleme/atama/tamamlama/iptal audit loga yazılır.
 */

require_once __DIR__ . '/leads.php';
require_once __DIR__ . '/lead_scan_places.php'; // lead_audit_log + lead_activity_add

/* ----------------------------------------------------------------------- *
 |  Sabitler
 * ----------------------------------------------------------------------- */

/** Takip türleri (§21). */
function lead_reminder_types(): array
{
    return [
        'call'     => 'Telefonla arama',
        'whatsapp' => 'WhatsApp mesajı',
        'email'    => 'E-posta',
        'quote'    => 'Teklif takibi',
        'meeting'  => 'Tekrar görüşme',
        'custom'   => 'Özel görev',
    ];
}
function lead_reminder_type_label(string $k): string { return lead_reminder_types()[$k] ?? $k; }

/** Öncelikler. */
function lead_reminder_priorities(): array
{
    return ['low' => 'Düşük', 'normal' => 'Normal', 'high' => 'Yüksek', 'urgent' => 'Acil'];
}
function lead_reminder_priority_class(string $k): string
{
    return match ($k) { 'urgent' => 'badge-danger', 'high' => 'badge-warning', 'low' => 'badge-muted', default => 'badge-info' };
}

/** Bildirim zamanı seçenekleri (dakika). */
function lead_remind_before_options(): array
{
    return [0 => 'Tam zamanında', 15 => '15 dakika önce', 30 => '30 dakika önce', 60 => '1 saat önce', 1440 => '1 gün önce'];
}

/** Takip durumları. */
function lead_reminder_statuses(): array
{
    return ['pending' => 'Bekliyor', 'upcoming' => 'Yaklaşıyor', 'overdue' => 'Gecikmiş',
            'done' => 'Tamamlandı', 'postponed' => 'Ertelendi', 'cancelled' => 'İptal Edildi'];
}
function lead_reminder_status_label(string $k): string { return lead_reminder_statuses()[$k] ?? $k; }
function lead_reminder_status_class(string $k): string
{
    return match ($k) { 'overdue' => 'badge-danger', 'upcoming' => 'badge-warning', 'done' => 'badge-success',
                        'cancelled' => 'badge-muted', 'postponed' => 'badge-info', default => 'badge-info' };
}

/** Tamamlama (işlem) sonuçları (§21). */
function lead_reminder_completion_results(): array
{
    return [
        'met'            => 'Görüşme yapıldı',
        'unreachable'    => 'Ulaşılamadı',
        'phone_off'      => 'Telefon kapalı',
        'busy'           => 'Meşguldü',
        'wrong'          => 'Yanlış numara',
        'wa_sent'        => 'WhatsApp gönderildi',
        'quote_sent'     => 'Teklif gönderildi',
        'not_interested' => 'İlgilenmiyor',
        'call_again'     => 'Tekrar aranacak',
        'converted'      => 'Müşteriye dönüştü',
    ];
}
function lead_reminder_result_label(string $k): string { return lead_reminder_completion_results()[$k] ?? $k; }

/** İşlem sonucu → önerilen lead durumu eşlemesi. */
function lead_reminder_result_to_status(string $result): ?string
{
    return [
        'met' => 'met', 'unreachable' => 'unreachable', 'phone_off' => 'unreachable', 'busy' => 'unreachable',
        'wrong' => 'wrong_number', 'wa_sent' => 'wa_sent', 'quote_sent' => 'quote_sent',
        'not_interested' => 'not_interested', 'call_again' => 'call_again', 'converted' => 'converted',
    ][$result] ?? null;
}

/** Erteleme seçenekleri (etiket => dakika; 'tomorrow'/'custom' özel). */
function lead_reminder_postpone_options(): array
{
    return ['15' => '15 dakika', '30' => '30 dakika', '60' => '1 saat', '120' => '2 saat',
            'tomorrow' => 'Yarın aynı saat', 'custom' => 'Özel tarih ve saat'];
}

/** Bu kullanıcı ekip takiplerini görebilir mi? (Yönetici / Süper Admin) */
function lead_reminders_can_see_team(): bool
{
    return can('leads.team_reminders') || (function_exists('current_permissions') && in_array('all', current_permissions(), true));
}

/** Takip atanabilecek aktif panel kullanıcıları [user_id => ad]. */
function lead_assignable_users(): array
{
    try {
        $rows = db()->query("SELECT id, CASE WHEN full_name <> '' THEN full_name ELSE username END AS name FROM users WHERE is_active = 1 ORDER BY name ASC LIMIT 500")->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[(int) $r['id']] = (string) $r['name']; }
        return $out;
    } catch (Throwable $e) { log_error('lead_assignable_users: ' . $e->getMessage()); return []; }
}

/* ----------------------------------------------------------------------- *
 |  Oluşturma
 * ----------------------------------------------------------------------- */

/**
 * Takip kaydı oluşturur. Zorunlu: tarih, saat. Türü/öncelik/bildirim zamanı
 * doğrulanır. remind_at = tarih + saat.
 * @return array{ok:bool, error:string, id:int}
 */
function lead_reminder_create(int $leadId, array $in, ?int $userId): array
{
    $date = trim((string) ($in['reminder_date'] ?? ''));
    $time = trim((string) ($in['reminder_time'] ?? ''));
    if ($date === '' || $time === '') {
        return ['ok' => false, 'error' => 'Takip tarihi ve saati zorunludur.', 'id' => 0];
    }
    if (strlen($time) === 5) { $time .= ':00'; }
    $remindAt = $date . ' ' . $time;
    if (strtotime($remindAt) === false) {
        return ['ok' => false, 'error' => 'Geçersiz takip tarihi/saati.', 'id' => 0];
    }

    $type = (string) ($in['reminder_type'] ?? 'call');
    if (!isset(lead_reminder_types()[$type])) { $type = 'call'; }
    $priority = (string) ($in['priority'] ?? 'normal');
    if (!isset(lead_reminder_priorities()[$priority])) { $priority = 'normal'; }
    $before = (int) ($in['remind_before_minutes'] ?? 0);
    if (!array_key_exists($before, lead_remind_before_options())) { $before = 0; }
    $assigned = (int) ($in['assigned_user_id'] ?? 0) ?: (int) $userId;
    $note = trim((string) ($in['note'] ?? ''));

    try {
        db()->prepare(
            'INSERT INTO lead_reminders
                (lead_id, assigned_user_id, assigned_to, type, reminder_type, remind_at, reminder_date, reminder_time,
                 remind_before_minutes, priority, status, note, created_by)
             VALUES (:l,:au,:au2,:t,:t2,:at,:d,:tm,:before,:prio,\'pending\',:note,:by)'
        )->execute([
            ':l' => $leadId, ':au' => $assigned, ':au2' => $assigned, ':t' => $type, ':t2' => $type,
            ':at' => $remindAt, ':d' => $date, ':tm' => $time, ':before' => $before, ':prio' => $priority,
            ':note' => $note, ':by' => $userId,
        ]);
        $id = (int) db()->lastInsertId();
        // Lead sonraki aksiyon zamanı (en yakın açık takip)
        db()->prepare('UPDATE leads SET next_action_at = :at WHERE id = :id AND (next_action_at IS NULL OR next_action_at > :at2)')
            ->execute([':at' => $remindAt, ':at2' => $remindAt, ':id' => $leadId]);
        lead_activity_add($leadId, 'reminder', 'Takip planlandı: ' . lead_reminder_type_label($type) . ' · ' . $remindAt, ['reminder_id' => $id], $userId);
        lead_audit_log($leadId, 'reminder_create', $type . ' @ ' . $remindAt . ' → user#' . $assigned, $userId);
        return ['ok' => true, 'error' => '', 'id' => $id];
    } catch (Throwable $e) {
        log_error('lead_reminder_create: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Takip kaydedilemedi.', 'id' => 0];
    }
}

function lead_reminder_get(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT r.*, l.company_name, l.phone, l.whatsapp, l.is_deleted AS lead_deleted, u.full_name AS assignee
                             FROM lead_reminders r INNER JOIN leads l ON l.id = r.lead_id
                             LEFT JOIN users u ON u.id = r.assigned_user_id
                             WHERE r.id = :id AND r.deleted_at IS NULL LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('lead_reminder_get: ' . $e->getMessage()); return null; }
}

/** Bir leade ait takipler (silinmemiş). */
function lead_reminders_for_lead(int $leadId): array
{
    try {
        $st = db()->prepare('SELECT r.*, u.full_name AS assignee FROM lead_reminders r LEFT JOIN users u ON u.id = r.assigned_user_id
                             WHERE r.lead_id = :l AND r.deleted_at IS NULL ORDER BY (r.status IN (\'done\',\'cancelled\')) ASC, r.remind_at ASC');
        $st->execute([':l' => $leadId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** Bir leadin açık (tamamlanmamış/iptal edilmemiş) takip sayısı. */
function lead_reminder_open_count(int $leadId): int
{
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM lead_reminders WHERE lead_id = :l AND deleted_at IS NULL AND status NOT IN ('done','cancelled')");
        $st->execute([':l' => $leadId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/* ----------------------------------------------------------------------- *
 |  Erteleme
 * ----------------------------------------------------------------------- */

/** Verilen erteleme seçeneğinden yeni remind_at hesaplar. */
function lead_reminder_calc_postpone(string $baseRemindAt, string $option, string $customAt = ''): ?string
{
    $base = strtotime($baseRemindAt) ?: time();
    switch ($option) {
        case '15': case '30': case '60': case '120':
            return date('Y-m-d H:i:s', $base + ((int) $option) * 60);
        case 'tomorrow':
            return date('Y-m-d H:i:s', strtotime('+1 day', $base));
        case 'custom':
            $t = strtotime($customAt);
            return $t ? date('Y-m-d H:i:s', $t) : null;
        default:
            return null;
    }
}

/**
 * Takibi erteler: geçmişe yazar, yeni zamana taşır, sayaç artırır, bildirim
 * yeniden planlanacak şekilde eski bildirimleri pasifleştirir.
 * @return array{ok:bool, error:string}
 */
function lead_reminder_postpone(int $id, string $option, string $customAt, string $reason, ?int $userId): array
{
    $rem = lead_reminder_get($id);
    if (!$rem) { return ['ok' => false, 'error' => 'Takip bulunamadı.']; }
    if (in_array((string) $rem['status'], ['done', 'cancelled'], true)) {
        return ['ok' => false, 'error' => 'Tamamlanmış/iptal edilmiş takip ertelenemez.'];
    }
    $newAt = lead_reminder_calc_postpone((string) $rem['remind_at'], $option, $customAt);
    if ($newAt === null) { return ['ok' => false, 'error' => 'Geçerli bir erteleme zamanı belirtin.']; }

    try {
        db()->beginTransaction();
        db()->prepare('INSERT INTO lead_reminder_postpones (reminder_id, lead_id, old_remind_at, new_remind_at, reason, postponed_by) VALUES (:r,:l,:o,:n,:reason,:by)')
            ->execute([':r' => $id, ':l' => (int) $rem['lead_id'], ':o' => (string) $rem['remind_at'], ':n' => $newAt, ':reason' => mb_substr($reason, 0, 500), ':by' => $userId]);
        db()->prepare('UPDATE lead_reminders SET remind_at = :at, reminder_date = :d, reminder_time = :tm, status = \'pending\',
                        is_overdue = 0, notify_stage = \'\', postpone_count = postpone_count + 1 WHERE id = :id')
            ->execute([':at' => $newAt, ':d' => substr($newAt, 0, 10), ':tm' => substr($newAt, 11, 8), ':id' => $id]);
        // Eski bekleyen bildirimleri pasifleştir (yeni zamana göre yeniden üretilecek)
        lead_reminder_dismiss_notifications($id);
        db()->prepare('UPDATE leads SET next_action_at = :at WHERE id = :id')->execute([':at' => $newAt, ':id' => (int) $rem['lead_id']]);
        db()->commit();
        lead_activity_add((int) $rem['lead_id'], 'reminder', 'Takip ertelendi → ' . $newAt . ($reason !== '' ? ' · ' . $reason : ''), ['reminder_id' => $id], $userId);
        lead_audit_log((int) $rem['lead_id'], 'reminder_postpone', $rem['remind_at'] . ' → ' . $newAt . ($reason !== '' ? ' · ' . $reason : ''), $userId);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        log_error('lead_reminder_postpone: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erteleme başarısız.'];
    }
}

/** Bir takibe ait erteleme geçmişi. */
function lead_reminder_postpone_history(int $id): array
{
    try {
        $st = db()->prepare('SELECT p.*, u.full_name FROM lead_reminder_postpones p LEFT JOIN users u ON u.id = p.postponed_by WHERE p.reminder_id = :r ORDER BY p.id DESC');
        $st->execute([':r' => $id]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* ----------------------------------------------------------------------- *
 |  Tamamlama / İptal
 * ----------------------------------------------------------------------- */

/**
 * Takibi tamamlar: sonuç + not; lead durumunu sonuca göre günceller; gerekiyorsa
 * yeni takip kaydı oluşturur. Bildirimin okunması DEĞİL, bu işlem görevi kapatır.
 * @return array{ok:bool, error:string, new_reminder_id:int}
 */
function lead_reminder_complete(int $id, array $in, ?int $userId): array
{
    $rem = lead_reminder_get($id);
    if (!$rem) { return ['ok' => false, 'error' => 'Takip bulunamadı.', 'new_reminder_id' => 0]; }
    if ((string) $rem['status'] === 'done') { return ['ok' => true, 'error' => '', 'new_reminder_id' => 0]; }

    $result = (string) ($in['completion_result'] ?? '');
    if (!isset(lead_reminder_completion_results()[$result])) {
        return ['ok' => false, 'error' => 'İşlem sonucu seçmelisiniz.', 'new_reminder_id' => 0];
    }
    $note = trim((string) ($in['completion_note'] ?? ''));
    $contact = trim((string) ($in['contact_person'] ?? ''));
    $leadId = (int) $rem['lead_id'];
    $needFollowup = !empty($in['need_followup']);

    // "Tekrar aranacak" veya takip isteniyorsa yeni tarih/saat zorunlu
    $newRemId = 0;
    if ($result === 'call_again' || $needFollowup) {
        if (trim((string) ($in['next_date'] ?? '')) === '' || trim((string) ($in['next_time'] ?? '')) === '') {
            return ['ok' => false, 'error' => '"Tekrar aranacak" / tekrar takip için yeni tarih ve saat zorunludur.', 'new_reminder_id' => 0];
        }
    }

    try {
        db()->prepare('UPDATE lead_reminders SET status = \'done\', is_done = 1, is_overdue = 0, completed_at = NOW(),
                        completed_by = :by, completion_result = :res, completion_note = :note, done_at = NOW() WHERE id = :id')
            ->execute([':by' => $userId, ':res' => $result, ':note' => $note, ':id' => $id]);
        lead_reminder_dismiss_notifications($id);

        // Lead: son iletişim + durum güncelle
        db()->prepare('UPDATE leads SET last_contact_at = NOW(), updated_by = :by WHERE id = :id')->execute([':by' => $userId, ':id' => $leadId]);
        $newStatus = (string) ($in['new_status'] ?? '');
        if ($newStatus === '' || !isset(lead_status_rows()[$newStatus])) {
            $mapped = lead_reminder_result_to_status($result);
            $newStatus = $mapped ?: '';
        }
        if ($newStatus !== '' && isset(lead_status_rows()[$newStatus])) {
            // set_lead_status yeni takip zorunluluğunu tetiklemesin diye doğrudan burada güncelliyoruz,
            // ama takip gerektiren duruma geçiliyorsa yeni takip zaten aşağıda oluşturulacak.
            set_lead_status($leadId, $newStatus, $userId, $contact !== '' ? ('Görüşülen: ' . $contact) : '');
        }

        // Gerekliyse yeni takip
        if ($result === 'call_again' || $needFollowup) {
            $res = lead_reminder_create($leadId, [
                'reminder_date' => $in['next_date'] ?? '', 'reminder_time' => $in['next_time'] ?? '',
                'reminder_type' => $in['next_type'] ?? $rem['reminder_type'], 'priority' => $in['next_priority'] ?? $rem['priority'],
                'remind_before_minutes' => $rem['remind_before_minutes'] ?? 0, 'assigned_user_id' => $rem['assigned_user_id'] ?? $userId,
                'note' => $in['next_note'] ?? '',
            ], $userId);
            $newRemId = $res['id'];
        }

        lead_activity_add($leadId, 'reminder', 'Takip tamamlandı: ' . lead_reminder_result_label($result)
            . ($contact !== '' ? ' · ' . $contact : '') . ($note !== '' ? ' · ' . $note : ''), ['reminder_id' => $id], $userId);
        lead_audit_log($leadId, 'reminder_complete', 'sonuç=' . $result . ($newRemId ? ' · yeni takip #' . $newRemId : ''), $userId);
        return ['ok' => true, 'error' => '', 'new_reminder_id' => $newRemId];
    } catch (Throwable $e) {
        log_error('lead_reminder_complete: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Tamamlama başarısız.', 'new_reminder_id' => 0];
    }
}

/** Takibi iptal eder. */
function lead_reminder_cancel(int $id, ?int $userId, string $reason = ''): bool
{
    $rem = lead_reminder_get($id);
    if (!$rem) { return false; }
    try {
        db()->prepare('UPDATE lead_reminders SET status = \'cancelled\', completion_note = :n WHERE id = :id')
            ->execute([':n' => mb_substr($reason, 0, 500) ?: null, ':id' => $id]);
        lead_reminder_dismiss_notifications($id);
        lead_audit_log((int) $rem['lead_id'], 'reminder_cancel', $reason, $userId);
        return true;
    } catch (Throwable $e) { log_error('lead_reminder_cancel: ' . $e->getMessage()); return false; }
}

/** Bir lead'in tüm açık takiplerini iptal eder (lead silindiğinde). */
function lead_reminders_cancel_for_lead(int $leadId, ?int $userId): int
{
    try {
        $st = db()->prepare("UPDATE lead_reminders SET status = 'cancelled' WHERE lead_id = :l AND status NOT IN ('done','cancelled') AND deleted_at IS NULL");
        $st->execute([':l' => $leadId]);
        // İlgili bildirimleri de pasifleştir
        db()->prepare("UPDATE user_notifications SET is_dismissed = 1, dismissed_at = NOW() WHERE related_type = 'lead_reminder' AND related_id IN (SELECT id FROM lead_reminders WHERE lead_id = :l)")
            ->execute([':l' => $leadId]);
        return $st->rowCount();
    } catch (Throwable $e) { log_error('lead_reminders_cancel_for_lead: ' . $e->getMessage()); return 0; }
}

/** Bu takibe ait panel bildirimlerini pasifleştirir. */
function lead_reminder_dismiss_notifications(int $reminderId): void
{
    try {
        db()->prepare("UPDATE user_notifications SET is_dismissed = 1, dismissed_at = NOW()
                       WHERE related_type = 'lead_reminder' AND related_id = :r AND is_dismissed = 0")
            ->execute([':r' => $reminderId]);
    } catch (Throwable $e) { log_error('lead_reminder_dismiss_notifications: ' . $e->getMessage()); }
}

/* ----------------------------------------------------------------------- *
 |  Gecikme işaretleme (cron + anlık)
 * ----------------------------------------------------------------------- */

/** Zamanı geçmiş açık takipleri "gecikmiş" olarak işaretler. Etkilenen satır sayısı. */
function lead_reminders_mark_overdue(): int
{
    try {
        $st = db()->prepare("UPDATE lead_reminders SET is_overdue = 1, status = 'overdue'
                             WHERE deleted_at IS NULL AND status IN ('pending','upcoming') AND remind_at < NOW()");
        $st->execute();
        return $st->rowCount();
    } catch (Throwable $e) { log_error('lead_reminders_mark_overdue: ' . $e->getMessage()); return 0; }
}

/** Yaklaşan (henüz vakti gelmemiş ama pencere içindeki) takipleri "upcoming" yapar. */
function lead_reminders_mark_upcoming(int $windowMinutes = 60): int
{
    try {
        $st = db()->prepare("UPDATE lead_reminders SET status = 'upcoming'
                             WHERE deleted_at IS NULL AND status = 'pending'
                               AND remind_at >= NOW() AND remind_at <= (NOW() + INTERVAL :w MINUTE)");
        $st->bindValue(':w', max(1, $windowMinutes), PDO::PARAM_INT);
        $st->execute();
        return $st->rowCount();
    } catch (Throwable $e) { return 0; }
}

/* ----------------------------------------------------------------------- *
 |  Kullanıcı / ekip listeleri (dashboard)
 * ----------------------------------------------------------------------- */

/**
 * Dashboard bölümleri için takip listeleri (kullanıcıya atanmış).
 * @return array{overdue:array, now:array, today:array, upcoming:array, completed:array}
 */
function lead_reminder_dashboard(int $userId, ?int $teamAll = null): array
{
    $scope = $teamAll ? '' : ' AND r.assigned_user_id = :u';
    $join  = 'INNER JOIN leads l ON l.id = r.lead_id AND l.is_deleted = 0
              LEFT JOIN lead_statuses ls ON ls.code = l.status
              LEFT JOIN users u2 ON u2.id = r.assigned_user_id';
    $base  = "SELECT r.*, l.company_name, l.phone, l.whatsapp, l.status AS lead_status, u2.full_name AS assignee
              FROM lead_reminders r $join WHERE r.deleted_at IS NULL AND r.status NOT IN ('cancelled')";
    $run = static function (string $extra, array $params) use ($base, $scope): array {
        try {
            $st = db()->prepare($base . $scope . $extra);
            $st->execute($params);
            return $st->fetchAll();
        } catch (Throwable $e) { log_error('lead_reminder_dashboard: ' . $e->getMessage()); return []; }
    };
    $p = $teamAll ? [] : [':u' => $userId];

    return [
        'overdue'   => $run(" AND r.status <> 'done' AND r.remind_at < NOW() ORDER BY r.remind_at ASC LIMIT 100", $p),
        'now'       => $run(" AND r.status <> 'done' AND r.remind_at >= (NOW() - INTERVAL 15 MINUTE) AND r.remind_at <= (NOW() + INTERVAL 15 MINUTE) ORDER BY r.remind_at ASC LIMIT 100", $p),
        'today'     => $run(" AND r.status <> 'done' AND DATE(r.remind_at) = CURDATE() AND r.remind_at > (NOW() + INTERVAL 15 MINUTE) ORDER BY r.remind_at ASC LIMIT 100", $p),
        'upcoming'  => $run(" AND r.status <> 'done' AND r.remind_at > CURDATE() + INTERVAL 1 DAY AND r.remind_at <= (NOW() + INTERVAL 7 DAY) ORDER BY r.remind_at ASC LIMIT 100", $p),
        'completed' => $run(" AND r.status = 'done' AND DATE(r.completed_at) = CURDATE() ORDER BY r.completed_at DESC LIMIT 100", $p),
    ];
}

/** Dashboard sayaçları (kullanıcı veya ekip). */
function lead_reminder_counts(int $userId, bool $teamAll = false): array
{
    $scope = $teamAll ? '' : ' AND assigned_user_id = :u';
    $p = $teamAll ? [] : [':u' => $userId];
    $q = static function (string $cond, array $params) {
        try { $st = db()->prepare('SELECT COUNT(*) FROM lead_reminders WHERE deleted_at IS NULL ' . $cond); $st->execute($params); return (int) $st->fetchColumn(); }
        catch (Throwable $e) { return 0; }
    };
    return [
        'today_open'  => $q("AND status <> 'done' AND status <> 'cancelled' AND DATE(remind_at) = CURDATE()" . $scope, $p),
        'overdue'     => $q("AND status <> 'done' AND status <> 'cancelled' AND remind_at < NOW()" . $scope, $p),
        'next_hour'   => $q("AND status <> 'done' AND status <> 'cancelled' AND remind_at >= NOW() AND remind_at <= (NOW() + INTERVAL 1 HOUR)" . $scope, $p),
        'done_today'  => $q("AND status = 'done' AND DATE(completed_at) = CURDATE()" . $scope, $p),
        'unreachable' => $q("AND status = 'done' AND completion_result IN ('unreachable','phone_off','busy') AND DATE(completed_at) = CURDATE()" . $scope, $p),
        'need_reschedule' => $q("AND status = 'done' AND completion_result = 'call_again'" . $scope, $p),
    ];
}

/** Ekip: personel bazlı bugünkü takip özeti (yönetici). */
function lead_reminder_team_by_user(): array
{
    try {
        return db()->query(
            "SELECT r.assigned_user_id, u.full_name,
                    SUM(CASE WHEN r.status <> 'done' AND r.status <> 'cancelled' AND DATE(r.remind_at)=CURDATE() THEN 1 ELSE 0 END) today_open,
                    SUM(CASE WHEN r.status <> 'done' AND r.status <> 'cancelled' AND r.remind_at < NOW() THEN 1 ELSE 0 END) overdue,
                    SUM(CASE WHEN r.status = 'done' AND DATE(r.completed_at)=CURDATE() THEN 1 ELSE 0 END) done_today,
                    SUM(CASE WHEN r.status = 'done' AND r.completion_result IN ('unreachable','phone_off','busy') AND DATE(r.completed_at)=CURDATE() THEN 1 ELSE 0 END) unreachable,
                    SUM(CASE WHEN r.postpone_count > 0 AND r.status <> 'done' AND r.status <> 'cancelled' THEN 1 ELSE 0 END) postponed
             FROM lead_reminders r LEFT JOIN users u ON u.id = r.assigned_user_id
             WHERE r.deleted_at IS NULL
             GROUP BY r.assigned_user_id, u.full_name
             ORDER BY overdue DESC, today_open DESC"
        )->fetchAll();
    } catch (Throwable $e) { log_error('lead_reminder_team_by_user: ' . $e->getMessage()); return []; }
}

/** Yönetici: takibi başka personele yeniden atar. */
function lead_reminder_reassign(int $id, int $newUserId, ?int $byUserId): bool
{
    $rem = lead_reminder_get($id);
    if (!$rem) { return false; }
    try {
        db()->prepare('UPDATE lead_reminders SET assigned_user_id = :u, assigned_to = :u2 WHERE id = :id')
            ->execute([':u' => $newUserId, ':u2' => $newUserId, ':id' => $id]);
        lead_reminder_dismiss_notifications($id); // eski kullanıcının bildirimi kapanır, yeni kullanıcıya cron üretir
        lead_audit_log((int) $rem['lead_id'], 'reminder_reassign', 'takip #' . $id . ' → user#' . $newUserId, $byUserId);
        return true;
    } catch (Throwable $e) { log_error('lead_reminder_reassign: ' . $e->getMessage()); return false; }
}

/** Ertelme sayısı eşiğini aşan takipler (yönetici raporu). */
function lead_reminders_over_postponed(int $threshold = 3, int $limit = 100): array
{
    try {
        $st = db()->prepare('SELECT r.*, l.company_name, u.full_name AS assignee FROM lead_reminders r
                             INNER JOIN leads l ON l.id = r.lead_id AND l.is_deleted = 0
                             LEFT JOIN users u ON u.id = r.assigned_user_id
                             WHERE r.deleted_at IS NULL AND r.postpone_count >= :t AND r.status NOT IN (\'done\',\'cancelled\')
                             ORDER BY r.postpone_count DESC LIMIT ' . max(1, min(500, $limit)));
        $st->bindValue(':t', max(1, $threshold), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}
