<?php
declare(strict_types=1);

/**
 * includes/leads_crm.php — Lead takip iş mantığı (§7,§8,§11,§12,§13).
 *   - Arama (telefon) kayıtları
 *   - WhatsApp kayıtları + şablonları (çoklu, app_settings JSON)
 *   - Notlar
 *   - Hatırlatmalar
 *   - Atamalar + çalışma listeleri (Arama/WhatsApp Listem)
 *
 * Tüm sorgular hazır ifadelerle; her yazımda aktivite akışına da düşülür.
 */

require_once __DIR__ . '/leads.php';
require_once __DIR__ . '/lead_scan_places.php'; // lead_audit_log

/* =========================================================================
 |  ARAMA (TELEFON) KAYITLARI  (§7)
 * ====================================================================== */

/** Arama sonucu seçenekleri. */
function lead_call_results(): array
{
    return [
        'reached'     => 'Ulaşıldı / görüşüldü',
        'no_answer'   => 'Cevap yok',
        'busy'        => 'Meşgul',
        'wrong'       => 'Yanlış numara',
        'callback'    => 'Sonra aranacak',
        'voicemail'   => 'Telesekreter',
        'not_interested' => 'İlgilenmiyor',
    ];
}
function lead_call_result_label(string $k): string { return lead_call_results()[$k] ?? $k; }

/** Arama kaydı ekler; lead'in son iletişim/sonraki aksiyon alanlarını günceller. */
function lead_call_log_add(int $leadId, array $in, ?int $userId): int
{
    $result = (string) ($in['result'] ?? '');
    if (!isset(lead_call_results()[$result])) { $result = 'no_answer'; }
    $nextAt = trim((string) ($in['next_at'] ?? '')) ?: null;
    try {
        db()->prepare(
            'INSERT INTO lead_call_logs (lead_id, result, contact_person, duration_sec, note, next_action, next_at, created_by)
             VALUES (:l,:r,:cp,:dur,:note,:na,:nat,:by)'
        )->execute([
            ':l' => $leadId, ':r' => $result, ':cp' => trim((string) ($in['contact_person'] ?? '')),
            ':dur' => (int) ($in['duration_sec'] ?? 0) ?: null, ':note' => trim((string) ($in['note'] ?? '')) ?: null,
            ':na' => trim((string) ($in['next_action'] ?? '')), ':nat' => $nextAt, ':by' => $userId,
        ]);
        $callId = (int) db()->lastInsertId();
        // Lead son iletişim / sonraki aksiyon
        db()->prepare('UPDATE leads SET last_contact_at = NOW(), next_action_at = :na, updated_by = :by WHERE id = :id')
            ->execute([':na' => $nextAt, ':by' => $userId, ':id' => $leadId]);
        lead_activity_add($leadId, 'call', 'Arama: ' . lead_call_result_label($result)
            . (trim((string) ($in['note'] ?? '')) !== '' ? ' · ' . trim((string) $in['note']) : ''), ['call_id' => $callId], $userId);
        // Sonuç → otomatik durum önerisi (yalnızca mantıklıysa)
        if ($result === 'reached') { set_lead_status($leadId, 'called', $userId); }
        elseif ($result === 'no_answer' || $result === 'voicemail') { set_lead_status($leadId, 'unreachable', $userId); }
        elseif ($result === 'wrong') { set_lead_status($leadId, 'wrong_number', $userId); }
        elseif ($result === 'callback') { set_lead_status($leadId, 'call_again', $userId); }
        elseif ($result === 'not_interested') { set_lead_status($leadId, 'not_interested', $userId); }
        return $callId;
    } catch (Throwable $e) { log_error('lead_call_log_add: ' . $e->getMessage()); return 0; }
}

function lead_call_logs(int $leadId, int $limit = 100): array
{
    try {
        $st = db()->prepare('SELECT c.*, u.full_name FROM lead_call_logs c LEFT JOIN users u ON u.id = c.created_by WHERE c.lead_id = :l ORDER BY c.id DESC LIMIT ' . max(1, min(300, $limit)));
        $st->execute([':l' => $leadId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* =========================================================================
 |  WHATSAPP KAYITLARI + ŞABLONLAR  (§8)
 * ====================================================================== */

/** Çoklu WhatsApp şablonları (app_settings JSON). Yoksa varsayılan tek şablon. */
function lead_wa_templates(): array
{
    $raw = (string) app_setting_get('lead_wa_templates', '');
    $arr = json_decode($raw, true);
    if (is_array($arr) && $arr) {
        $out = [];
        foreach ($arr as $t) {
            if (is_array($t) && trim((string) ($t['name'] ?? '')) !== '' && trim((string) ($t['body'] ?? '')) !== '') {
                $out[] = ['name' => (string) $t['name'], 'body' => (string) $t['body']];
            }
        }
        if ($out) { return $out; }
    }
    return [['name' => 'Varsayılan', 'body' => lead_wa_template()]];
}

/** Şablonları kaydeder (yönetim ekranı). */
function lead_wa_templates_save(array $names, array $bodies): void
{
    $out = [];
    foreach ($names as $i => $n) {
        $n = trim((string) $n);
        $b = trim((string) ($bodies[$i] ?? ''));
        if ($n !== '' && $b !== '') { $out[] = ['name' => $n, 'body' => $b]; }
    }
    app_setting_set('lead_wa_templates', json_encode(array_values($out), JSON_UNESCAPED_UNICODE));
}

/** Şablona lead alanlarını uygular. */
function lead_wa_render(string $body, array $lead): string
{
    return strtr($body, [
        '{firma}'   => (string) ($lead['company_name'] ?? ''),
        '{yetkili}' => (string) ($lead['contact_name'] ?? '') ?: 'Yetkili',
        '{sehir}'   => (string) ($lead['city'] ?? ''),
        '{ilce}'    => (string) ($lead['district'] ?? ''),
        '{telefon}' => (string) ($lead['phone'] ?? ''),
        '{web}'     => (string) ($lead['website'] ?? ''),
    ]);
}

/**
 * Belirli mesajla WhatsApp linki (tekil, onaylı). Kara liste / telefon yoksa null.
 * Telefonu normalize eder (+90…).
 */
function lead_wa_link_for(array $lead, string $message): ?string
{
    if (lead_is_blacklisted($lead)) { return null; }
    $phone = trim((string) ($lead['whatsapp'] ?? '')) ?: trim((string) ($lead['phone'] ?? ''));
    if ($phone === '') { return null; }
    $norm = lead_normalize_phone($phone);
    $digits = ltrim(preg_replace('/\D+/', '', $norm) ?? '', '0');
    if ($digits === '') { return null; }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

/** WhatsApp gönderim kaydı ekler + durum/aktivite. */
function lead_wa_log_add(int $leadId, string $phone, string $message, ?int $templateId, ?int $userId, string $note = ''): int
{
    try {
        db()->prepare('INSERT INTO lead_whatsapp_logs (lead_id, template_id, phone, message, note, created_by) VALUES (:l,:t,:p,:m,:n,:by)')
            ->execute([':l' => $leadId, ':t' => $templateId, ':p' => $phone, ':m' => $message, ':n' => $note, ':by' => $userId]);
        $id = (int) db()->lastInsertId();
        db()->prepare('UPDATE leads SET last_message_at = NOW(), last_contact_at = NOW(), updated_by = :by WHERE id = :id')
            ->execute([':by' => $userId, ':id' => $leadId]);
        lead_activity_add($leadId, 'whatsapp', 'WhatsApp mesajı gönderildi', ['wa_id' => $id], $userId);
        set_lead_status($leadId, 'wa_sent', $userId);
        return $id;
    } catch (Throwable $e) { log_error('lead_wa_log_add: ' . $e->getMessage()); return 0; }
}

function lead_wa_logs(int $leadId, int $limit = 100): array
{
    try {
        $st = db()->prepare('SELECT w.*, u.full_name FROM lead_whatsapp_logs w LEFT JOIN users u ON u.id = w.created_by WHERE w.lead_id = :l ORDER BY w.id DESC LIMIT ' . max(1, min(300, $limit)));
        $st->execute([':l' => $leadId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* =========================================================================
 |  NOTLAR  (§11)
 * ====================================================================== */

function lead_note_add(int $leadId, string $note, ?int $userId): int
{
    $note = trim($note);
    if ($note === '') { return 0; }
    try {
        db()->prepare('INSERT INTO lead_notes (lead_id, note, created_by) VALUES (:l,:n,:by)')
            ->execute([':l' => $leadId, ':n' => $note, ':by' => $userId]);
        $id = (int) db()->lastInsertId();
        lead_activity_add($leadId, 'note', mb_substr($note, 0, 200), ['note_id' => $id], $userId);
        return $id;
    } catch (Throwable $e) { log_error('lead_note_add: ' . $e->getMessage()); return 0; }
}

function lead_notes_list(int $leadId, int $limit = 100): array
{
    try {
        $st = db()->prepare('SELECT n.*, u.full_name FROM lead_notes n LEFT JOIN users u ON u.id = n.created_by WHERE n.lead_id = :l ORDER BY n.id DESC LIMIT ' . max(1, min(300, $limit)));
        $st->execute([':l' => $leadId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* =========================================================================
 |  HATIRLATMALAR  (§13)
 * ====================================================================== */

function lead_reminder_types(): array
{
    return ['call' => 'Arama', 'whatsapp' => 'WhatsApp', 'email' => 'E-posta', 'meeting' => 'Görüşme', 'other' => 'Diğer'];
}

function lead_reminder_add(int $leadId, array $in, ?int $userId): int
{
    $remindAt = trim((string) ($in['remind_at'] ?? ''));
    if ($remindAt === '') { return 0; }
    $type = (string) ($in['type'] ?? 'call');
    if (!isset(lead_reminder_types()[$type])) { $type = 'call'; }
    $assignedTo = (int) ($in['assigned_to'] ?? 0) ?: $userId;
    try {
        db()->prepare('INSERT INTO lead_reminders (lead_id, type, remind_at, note, assigned_to, created_by) VALUES (:l,:t,:at,:n,:asg,:by)')
            ->execute([':l' => $leadId, ':t' => $type, ':at' => $remindAt, ':n' => trim((string) ($in['note'] ?? '')), ':asg' => $assignedTo, ':by' => $userId]);
        $id = (int) db()->lastInsertId();
        db()->prepare('UPDATE leads SET next_action_at = :at WHERE id = :id AND (next_action_at IS NULL OR next_action_at > :at2)')
            ->execute([':at' => $remindAt, ':at2' => $remindAt, ':id' => $leadId]);
        lead_activity_add($leadId, 'reminder', 'Hatırlatma: ' . $remindAt, ['reminder_id' => $id], $userId);
        return $id;
    } catch (Throwable $e) { log_error('lead_reminder_add: ' . $e->getMessage()); return 0; }
}

function lead_reminder_done(int $reminderId, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE lead_reminders SET is_done = 1, done_at = NOW() WHERE id = :id')->execute([':id' => $reminderId]);
    } catch (Throwable $e) { log_error('lead_reminder_done: ' . $e->getMessage()); return false; }
}

function lead_reminders_for_lead(int $leadId): array
{
    try {
        $st = db()->prepare('SELECT r.*, u.full_name AS assignee FROM lead_reminders r LEFT JOIN users u ON u.id = r.assigned_to WHERE r.lead_id = :l ORDER BY r.is_done ASC, r.remind_at ASC');
        $st->execute([':l' => $leadId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** Kullanıcının bekleyen (vadesi gelen/geçen) hatırlatmaları. */
function lead_due_reminders(?int $userId, int $limit = 50): array
{
    try {
        $sql = 'SELECT r.*, l.company_name FROM lead_reminders r
                INNER JOIN leads l ON l.id = r.lead_id AND l.is_deleted = 0
                WHERE r.is_done = 0 AND r.remind_at <= (NOW() + INTERVAL 1 DAY)';
        $params = [];
        if ($userId !== null) { $sql .= ' AND (r.assigned_to = :u OR r.assigned_to IS NULL)'; $params[':u'] = $userId; }
        $sql .= ' ORDER BY r.remind_at ASC LIMIT ' . max(1, min(200, $limit));
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* =========================================================================
 |  ATAMALAR  (§12)
 * ====================================================================== */

/** Lead'i personele atar; geçmişe yazar + aktivite. */
function lead_assign(int $leadId, ?int $personnelId, ?int $userId): bool
{
    try {
        $ok = db()->prepare('UPDATE leads SET assigned_personnel_id = :p, updated_by = :by WHERE id = :id AND is_deleted = 0')
            ->execute([':p' => $personnelId ?: null, ':by' => $userId, ':id' => $leadId]);
        db()->prepare('INSERT INTO lead_assignments (lead_id, personnel_id, assigned_by) VALUES (:l,:p,:by)')
            ->execute([':l' => $leadId, ':p' => $personnelId ?: null, ':by' => $userId]);
        lead_activity_add($leadId, 'assign', $personnelId ? 'Personele atandı' : 'Atama kaldırıldı', ['personnel_id' => $personnelId], $userId);
        return (bool) $ok;
    } catch (Throwable $e) { log_error('lead_assign: ' . $e->getMessage()); return false; }
}

/* =========================================================================
 |  ÇALIŞMA LİSTELERİ — Arama Listem / WhatsApp Listem  (§12)
 * ====================================================================== */

function lead_worklist_types(): array { return ['call' => 'Arama Listem', 'whatsapp' => 'WhatsApp Listem']; }

/** Lead'i kullanıcının çalışma listesine ekler (aktif). Zaten varsa aktifleştirir. */
function lead_worklist_add(int $leadId, int $personnelId, string $type): bool
{
    if (!isset(lead_worklist_types()[$type])) { return false; }
    try {
        // uq_lwl_active (lead_id, list_type) → upsert
        db()->prepare(
            'INSERT INTO lead_work_lists (lead_id, personnel_id, list_type, is_active) VALUES (:l,:p,:t,1)
             ON DUPLICATE KEY UPDATE personnel_id = VALUES(personnel_id), is_active = 1'
        )->execute([':l' => $leadId, ':p' => $personnelId, ':t' => $type]);
        return true;
    } catch (Throwable $e) { log_error('lead_worklist_add: ' . $e->getMessage()); return false; }
}

/** Lead'i çalışma listesinden çıkarır (pasife alır). */
function lead_worklist_remove(int $leadId, string $type): bool
{
    try {
        return db()->prepare('UPDATE lead_work_lists SET is_active = 0 WHERE lead_id = :l AND list_type = :t')
            ->execute([':l' => $leadId, ':t' => $type]);
    } catch (Throwable $e) { log_error('lead_worklist_remove: ' . $e->getMessage()); return false; }
}

/** Bir lead belirli listede aktif mi? */
function lead_worklist_has(int $leadId, string $type): bool
{
    try {
        $st = db()->prepare('SELECT 1 FROM lead_work_lists WHERE lead_id = :l AND list_type = :t AND is_active = 1 LIMIT 1');
        $st->execute([':l' => $leadId, ':t' => $type]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) { return false; }
}

/** Kullanıcının çalışma listesindeki lead'ler. */
function lead_worklist_items(int $personnelId, string $type): array
{
    try {
        $st = db()->prepare(
            'SELECT l.*, w.created_at AS added_at FROM lead_work_lists w
             INNER JOIN leads l ON l.id = w.lead_id AND l.is_deleted = 0
             WHERE w.personnel_id = :p AND w.list_type = :t AND w.is_active = 1
             ORDER BY l.next_action_at IS NULL, l.next_action_at ASC, w.id ASC'
        );
        $st->execute([':p' => $personnelId, ':t' => $type]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('lead_worklist_items: ' . $e->getMessage()); return []; }
}

function lead_worklist_count(int $personnelId, string $type): int
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM lead_work_lists w INNER JOIN leads l ON l.id = w.lead_id AND l.is_deleted = 0 WHERE w.personnel_id = :p AND w.list_type = :t AND w.is_active = 1');
        $st->execute([':p' => $personnelId, ':t' => $type]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
