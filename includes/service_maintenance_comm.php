<?php
declare(strict_types=1);

/**
 * includes/service_maintenance_comm.php
 * Bakım hatırlatma iletişimi: şablonlar, değişken doldurma, e-posta gönderimi
 * (mevcut SMTP), WhatsApp wa.me bağlantısı, gönderim kayıtları (§6, §7).
 *
 * İLKELER:
 *  - WhatsApp ekranının açılması "gönderildi" anlamına gelmez; kullanıcı ayrıca
 *    "Gönderildi Olarak İşaretle" yapmalı (§7).
 *  - İletişim izni olmayan müşterilere OTOMATİK gönderim yapılmaz; yetkili
 *    kullanıcı manuel gönderirse işlem audit loga yazılır (§8).
 *  - Toplu/kontrolsüz WhatsApp gönderimi yoktur; yalnızca kullanıcı kontrollü
 *    wa.me bağlantısı üretilir (§7).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/service_maintenance.php';

/* ---- Varsayılan şablonlar (§6, §7) ---- */

function smaint_default_email_template(): array
{
    return [
        'subject' => 'Cihazınızın Periyodik Bakım Zamanı Geldi',
        'body'    =>
            "Sayın {yetkili_adi},\n\n" .
            "{urun_markasi} {urun_modeli} (Seri No: {seri_numarasi}) cihazınızın periyodik bakım zamanı gelmiştir.\n\n" .
            "Düzenli bakım, cihazınızın performansını korur ve olası arızaların önüne geçilmesine yardımcı olur. " .
            "Bakım randevusu oluşturmak veya detaylı bilgi almak için bizimle iletişime geçebilirsiniz.\n\n" .
            "Servis No: {servis_no}\n" .
            "Son Teslim Tarihi: {teslim_tarihi}\n" .
            "Önerilen Bakım Tarihi: {bakim_tarihi}\n\n" .
            "İletişim: {sirket_telefonu}\n" .
            "Saygılarımızla,\n{sirket_adi}",
    ];
}

function smaint_default_whatsapp_template(): string
{
    return "Merhaba {musteri_adi}, {urun_markasi} {urun_modeli} cihazınızın periyodik bakım zamanı gelmiştir. " .
        "Cihazınızın performansını korumak ve olası arızaları önlemek için bakım randevusu oluşturabilirsiniz. " .
        "Detaylı bilgi ve randevu için bizimle iletişime geçebilirsiniz. {sirket_adi}";
}

/** Kullanılabilir değişken listesi (ipucu için). */
function smaint_template_variables(string $channel = 'email'): array
{
    $email = ['{musteri_adi}', '{firma_adi}', '{yetkili_adi}', '{urun_markasi}', '{urun_modeli}',
        '{seri_numarasi}', '{servis_no}', '{teslim_tarihi}', '{bakim_tarihi}', '{yapilan_islem}',
        '{sirket_adi}', '{sirket_telefonu}', '{personel_adi}', '{randevu_linki}'];
    if ($channel === 'whatsapp') {
        return ['{musteri_adi}', '{firma_adi}', '{urun_markasi}', '{urun_modeli}', '{seri_numarasi}',
            '{servis_no}', '{bakim_tarihi}', '{sirket_adi}', '{sirket_telefonu}', '{personel_adi}', '{randevu_linki}'];
    }
    return $email;
}

/* ---- Şablon değişkenleri ---- */

function smaint_msg_vars(array $r): array
{
    $company = function_exists('service_company_info') ? service_company_info() : [];
    $companyName  = trim((string) ($company['company_name'] ?? '')) !== '' ? (string) $company['company_name'] : (defined('SITE_NAME') ? SITE_NAME : '');
    $companyPhone = (string) ($company['company_phone'] ?? '');
    $companyWeb   = (string) ($company['company_website'] ?? '');
    return [
        '{musteri_adi}'   => (string) ($r['customer_name'] ?? ''),
        '{firma_adi}'     => (string) ($r['company_name'] ?: $r['customer_name'] ?? ''),
        '{yetkili_adi}'   => (string) ($r['contact_name'] ?: $r['customer_name'] ?? ''),
        '{urun_markasi}'  => (string) ($r['brand_name'] ?? ''),
        '{urun_modeli}'   => (string) ($r['device_model'] ?? ''),
        '{seri_numarasi}' => (string) ($r['serial_no'] ?? ''),
        '{servis_no}'     => (string) ($r['reference_code'] ?? ''),
        '{teslim_tarihi}' => substr((string) ($r['delivery_date'] ?? ''), 0, 10),
        '{bakim_tarihi}'  => substr((string) ($r['maintenance_due_date'] ?? ''), 0, 10),
        '{yapilan_islem}' => (string) ($r['work_done'] ?? ''),
        '{sirket_adi}'    => $companyName,
        '{sirket_telefonu}' => $companyPhone,
        '{personel_adi}'  => (string) ($r['assignee_name'] ?? ''),
        '{randevu_linki}' => $companyWeb,
    ];
}

function smaint_msg_render(string $template, array $r): string
{
    return strtr($template, smaint_msg_vars($r));
}

/* ---- Şablon CRUD (service_maintenance_templates) ---- */

function smaint_templates(string $channel = ''): array
{
    smaint_ensure_schema();
    try {
        if ($channel !== '') {
            $st = db()->prepare('SELECT * FROM service_maintenance_templates WHERE channel = :c ORDER BY is_default DESC, name ASC');
            $st->execute([':c' => $channel]);
            return $st->fetchAll();
        }
        return db()->query('SELECT * FROM service_maintenance_templates ORDER BY channel ASC, is_default DESC, name ASC')->fetchAll();
    } catch (Throwable $e) { log_error('smaint_templates: ' . $e->getMessage()); return []; }
}

function smaint_template_get(int $id): ?array
{
    if ($id <= 0) { return null; }
    smaint_ensure_schema();
    try {
        $st = db()->prepare('SELECT * FROM service_maintenance_templates WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { log_error('smaint_template_get: ' . $e->getMessage()); return null; }
}

/** Kanalın etkin varsayılan şablonu; yoksa gömülü varsayılan (sentetik). */
function smaint_template_default(string $channel): array
{
    smaint_ensure_schema();
    try {
        $st = db()->prepare('SELECT * FROM service_maintenance_templates WHERE channel = :c AND is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1');
        $st->execute([':c' => $channel]);
        $row = $st->fetch();
        if ($row) { return $row; }
    } catch (Throwable $e) { log_error('smaint_template_default: ' . $e->getMessage()); }
    if ($channel === 'whatsapp') {
        return ['id' => 0, 'channel' => 'whatsapp', 'name' => 'Varsayılan WhatsApp', 'subject' => null, 'body' => smaint_default_whatsapp_template()];
    }
    $d = smaint_default_email_template();
    return ['id' => 0, 'channel' => 'email', 'name' => 'Varsayılan E-posta', 'subject' => $d['subject'], 'body' => $d['body']];
}

function smaint_template_save(array $in, ?int $userId): array
{
    smaint_ensure_schema();
    $channel = in_array((string) ($in['channel'] ?? ''), ['email', 'whatsapp'], true) ? (string) $in['channel'] : 'email';
    $name = trim((string) ($in['name'] ?? ''));
    $body = trim((string) ($in['body'] ?? ''));
    if ($name === '' || $body === '') { return ['ok' => false, 'error' => 'Şablon adı ve içeriği zorunludur.']; }
    $subject = $channel === 'email' ? trim((string) ($in['subject'] ?? '')) : null;
    $isDefault = !empty($in['is_default']) ? 1 : 0;
    $isActive  = !empty($in['is_active']) ? 1 : 0;
    $id = (int) ($in['id'] ?? 0);
    try {
        if ($isDefault === 1) {
            db()->prepare('UPDATE service_maintenance_templates SET is_default = 0 WHERE channel = :c')->execute([':c' => $channel]);
        }
        if ($id > 0) {
            db()->prepare('UPDATE service_maintenance_templates SET channel=:c, name=:n, subject=:s, body=:b, is_default=:d, is_active=:a WHERE id=:id')
                ->execute([':c' => $channel, ':n' => $name, ':s' => $subject, ':b' => $body, ':d' => $isDefault, ':a' => $isActive, ':id' => $id]);
        } else {
            db()->prepare('INSERT INTO service_maintenance_templates (channel, name, subject, body, is_default, is_active, created_by) VALUES (:c,:n,:s,:b,:d,:a,:by)')
                ->execute([':c' => $channel, ':n' => $name, ':s' => $subject, ':b' => $body, ':d' => $isDefault, ':a' => $isActive, ':by' => $userId]);
            $id = (int) db()->lastInsertId();
        }
        log_activity('maintenance_template_save', 'maintenance', $id, null, 'success', $channel . ' · ' . $name);
        return ['ok' => true, 'error' => '', 'id' => $id];
    } catch (Throwable $e) {
        log_error('smaint_template_save: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Şablon kaydedilemedi.'];
    }
}

function smaint_template_delete(int $id, ?int $userId): bool
{
    if ($id <= 0) { return false; }
    try {
        db()->prepare('DELETE FROM service_maintenance_templates WHERE id = :id')->execute([':id' => $id]);
        log_activity('maintenance_template_delete', 'maintenance', $id, null, 'success');
        return true;
    } catch (Throwable $e) { log_error('smaint_template_delete: ' . $e->getMessage()); return false; }
}

/* ---- İletişim kayıtları ---- */

function smaint_comm_log_insert(array $d): int
{
    smaint_ensure_schema();
    try {
        db()->prepare(
            'INSERT INTO service_maintenance_communications
                (reminder_id, service_id, channel, direction, to_phone, to_email, subject, body,
                 template_key, template_name, from_account, sent_by_user_id, sent_by_name,
                 wa_opened_at, marked_sent_at, status, customer_reply, error_message, note)
             VALUES (:rid,:sid,:ch,:dir,:ph,:em,:sub,:body,:tk,:tn,:from,:uid,:uname,:wo,:ms,:st,:cr,:err,:note)'
        )->execute([
            ':rid' => (int) ($d['reminder_id'] ?? 0), ':sid' => $d['service_id'] ?? null,
            ':ch' => (string) ($d['channel'] ?? 'email'), ':dir' => (string) ($d['direction'] ?? 'out'),
            ':ph' => $d['to_phone'] ?? null, ':em' => $d['to_email'] ?? null,
            ':sub' => $d['subject'] ?? null, ':body' => $d['body'] ?? null,
            ':tk' => $d['template_key'] ?? null, ':tn' => $d['template_name'] ?? null,
            ':from' => $d['from_account'] ?? null, ':uid' => $d['sent_by_user_id'] ?? null,
            ':uname' => $d['sent_by_name'] ?? null, ':wo' => $d['wa_opened_at'] ?? null,
            ':ms' => $d['marked_sent_at'] ?? null, ':st' => (string) ($d['status'] ?? 'pending'),
            ':cr' => $d['customer_reply'] ?? null, ':err' => $d['error_message'] ?? null,
            ':note' => isset($d['note']) ? mb_substr((string) $d['note'], 0, 500) : null,
        ]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('smaint_comm_log_insert: ' . $e->getMessage()); return 0; }
}

function smaint_comms_for_reminder(int $reminderId, int $limit = 100): array
{
    smaint_ensure_schema();
    try {
        $st = db()->prepare(
            'SELECT c.*, u.full_name AS user_name FROM service_maintenance_communications c
             LEFT JOIN users u ON u.id = c.sent_by_user_id
             WHERE c.reminder_id = :r ORDER BY c.id DESC LIMIT ' . max(1, min(500, $limit))
        );
        $st->execute([':r' => $reminderId]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('smaint_comms_for_reminder: ' . $e->getMessage()); return []; }
}

/* ---- E-posta gönderimi (mevcut SMTP) ---- */

/**
 * Bakım hatırlatma e-postası gönderir + kayıt tutar.
 * @param bool $manual Kullanıcı elle mi tetikliyor (izin yoksa uyarı+audit ile devam).
 * @return array{ok:bool,msg:string,comm_id:int}
 */
function smaint_send_email(int $reminderId, string $subject, string $body, ?string $templateName, ?int $userId, bool $manual = true): array
{
    $r = smaint_reminder_get($reminderId);
    if (!$r) { return ['ok' => false, 'msg' => 'Bakım kaydı bulunamadı.', 'comm_id' => 0]; }
    $to = trim((string) ($r['email'] ?? ''));
    if ($to === '') { return ['ok' => false, 'msg' => 'Bu kayıtta müşteri e-postası bulunmuyor.', 'comm_id' => 0]; }

    $hasConsent = (int) ($r['email_consent'] ?? 0) === 1;
    if (!$manual && !$hasConsent) {
        return ['ok' => false, 'msg' => 'Bu müşterinin e-posta iletişim izni bulunmuyor.', 'comm_id' => 0];
    }
    if ($manual && !$hasConsent) {
        // Yetkili kullanıcı manuel gönderiyor: izin yok ama audit'e yazılır (§8).
        log_activity('maintenance_email_consent_override', 'maintenance', $reminderId, (string) ($r['reference_code'] ?? ''), 'success',
            'İzinsiz e-posta manuel gönderildi: ' . $to);
    }

    if (!function_exists('mail_send')) { require_once __DIR__ . '/mail.php'; }
    $cfg = mail_config_status();
    $senderUser = function_exists('mail_session_user') ? mail_session_user() : [];
    $fromAccount = defined('MAIL_FROM') ? (string) MAIL_FROM : '';
    $senderName  = (string) ($senderUser['full_name'] ?? ($senderUser['username'] ?? ''));

    if (!$cfg['ok']) {
        $cid = smaint_comm_log_insert([
            'reminder_id' => $reminderId, 'service_id' => $r['service_id'] ?? null, 'channel' => 'email',
            'to_email' => $to, 'subject' => $subject, 'body' => $body, 'template_name' => $templateName,
            'from_account' => $fromAccount, 'sent_by_user_id' => $userId, 'sent_by_name' => $senderName,
            'status' => 'failed', 'error_message' => $cfg['msg'],
        ]);
        return ['ok' => false, 'msg' => $cfg['msg'], 'comm_id' => $cid];
    }

    $res = mail_send($to, (string) ($r['customer_name'] ?? ''), $subject, $body,
        ['type' => 'service_maintenance', 'sender_user' => $senderUser]);

    $cid = smaint_comm_log_insert([
        'reminder_id' => $reminderId, 'service_id' => $r['service_id'] ?? null, 'channel' => 'email',
        'to_email' => $to, 'subject' => $subject, 'body' => $body, 'template_name' => $templateName,
        'from_account' => $fromAccount, 'sent_by_user_id' => $userId, 'sent_by_name' => $senderName,
        'marked_sent_at' => $res['ok'] ? date('Y-m-d H:i:s') : null,
        'status' => $res['ok'] ? 'sent' : 'failed',
        'error_message' => $res['ok'] ? null : ($res['error'] ?? $res['msg'] ?? 'Gönderim hatası'),
    ]);

    if ($res['ok']) {
        // Gönderim başarılıysa kaydı ilerlet; başarısızsa tamamlanmış sayılmaz (§6).
        try {
            db()->prepare(
                "UPDATE service_maintenance_reminders
                 SET status = 'email_sent', last_message_at = NOW(), last_contact_at = NOW(),
                     last_template_key = :tk, reminder_count = reminder_count + 1
                 WHERE id = :id AND deleted_at IS NULL"
            )->execute([':tk' => $templateName, ':id' => $reminderId]);
            smaint_status_history_add($reminderId, (string) $r['status'], 'email_sent', 'E-posta gönderildi: ' . $to, $userId);
        } catch (Throwable $e) { log_error('smaint_send_email status: ' . $e->getMessage()); }
        log_activity('maintenance_email_sent', 'maintenance', $reminderId, (string) ($r['reference_code'] ?? ''), 'success', $to);
        if (function_exists('smaint_dismiss_notifications')) { smaint_dismiss_notifications($reminderId); }
    } else {
        log_activity('maintenance_email_failed', 'maintenance', $reminderId, (string) ($r['reference_code'] ?? ''), 'failed',
            (string) ($res['msg'] ?? ''));
    }
    return ['ok' => (bool) $res['ok'], 'msg' => (string) $res['msg'], 'comm_id' => $cid];
}

/* ---- WhatsApp ---- */

/** wa.me bağlantısı (uluslararası format + urlencode). */
function smaint_wa_link(array $r, string $message): ?string
{
    $phone = trim((string) ($r['whatsapp'] ?: $r['phone'] ?? ''));
    if ($phone === '') { return null; }
    if (function_exists('build_whatsapp_message_link')) {
        return build_whatsapp_message_link($phone, $message);
    }
    require_once __DIR__ . '/notifications.php';
    return build_whatsapp_message_link($phone, $message);
}

/**
 * WhatsApp "Gönderildi Olarak İşaretle" — kaydı ve durumu günceller (§7).
 * Ekranın açılması değil, bu işlem gönderimi kaydeder.
 */
function smaint_wa_mark_sent(int $reminderId, string $body, ?int $userId, bool $manual = true): array
{
    $r = smaint_reminder_get($reminderId);
    if (!$r) { return ['ok' => false, 'msg' => 'Kayıt bulunamadı.']; }
    $phone = trim((string) ($r['whatsapp'] ?: $r['phone'] ?? ''));
    if ($phone === '') { return ['ok' => false, 'msg' => 'Telefon/WhatsApp numarası bulunmuyor.']; }

    if ($manual && (int) ($r['whatsapp_consent'] ?? 0) !== 1) {
        log_activity('maintenance_wa_consent_override', 'maintenance', $reminderId, (string) ($r['reference_code'] ?? ''), 'success',
            'İzinsiz WhatsApp manuel gönderim işaretlendi: ' . $phone);
    }

    $senderUser = function_exists('mail_session_user') ? mail_session_user() : [];
    $now = date('Y-m-d H:i:s');
    $cid = smaint_comm_log_insert([
        'reminder_id' => $reminderId, 'service_id' => $r['service_id'] ?? null, 'channel' => 'whatsapp',
        'to_phone' => $phone, 'body' => $body, 'sent_by_user_id' => $userId,
        'sent_by_name' => (string) ($senderUser['full_name'] ?? ''),
        'wa_opened_at' => $now, 'marked_sent_at' => $now, 'status' => 'sent',
    ]);
    try {
        db()->prepare(
            "UPDATE service_maintenance_reminders
             SET status = 'wa_sent', last_message_at = NOW(), last_contact_at = NOW(),
                 reminder_count = reminder_count + 1
             WHERE id = :id AND deleted_at IS NULL"
        )->execute([':id' => $reminderId]);
        smaint_status_history_add($reminderId, (string) $r['status'], 'wa_sent', 'WhatsApp gönderildi olarak işaretlendi.', $userId);
        log_activity('maintenance_wa_sent', 'maintenance', $reminderId, (string) ($r['reference_code'] ?? ''), 'success', $phone);
        if (function_exists('smaint_dismiss_notifications')) { smaint_dismiss_notifications($reminderId); }
    } catch (Throwable $e) { log_error('smaint_wa_mark_sent: ' . $e->getMessage()); }
    return ['ok' => true, 'msg' => 'WhatsApp gönderildi olarak işaretlendi.', 'comm_id' => $cid];
}

/** Son WhatsApp iletişimine müşteri cevabı işler. */
function smaint_mark_customer_replied(int $reminderId, ?int $userId, string $note = ''): bool
{
    $r = smaint_reminder_get($reminderId);
    if (!$r) { return false; }
    try {
        db()->prepare(
            "UPDATE service_maintenance_communications SET customer_reply = 'replied'
             WHERE reminder_id = :r ORDER BY id DESC LIMIT 1"
        )->execute([':r' => $reminderId]);
        smaint_status_history_add($reminderId, (string) $r['status'], (string) $r['status'], 'Müşteri cevap verdi.' . ($note !== '' ? ' · ' . $note : ''), $userId);
        log_activity('maintenance_customer_replied', 'maintenance', $reminderId, (string) ($r['reference_code'] ?? ''), 'success', $note);
        return true;
    } catch (Throwable $e) { log_error('smaint_mark_customer_replied: ' . $e->getMessage()); return false; }
}
