<?php
declare(strict_types=1);

/**
 * includes/message-templates.php
 * Dış ticaret mesaj şablonları + değişkenli render + gönderim/loglama.
 *
 * GÜVENLİK / İLKELER:
 *  - OTOMATİK TOPLU SPAM YOK. Mail gönderimi kullanıcı onaylıdır; WhatsApp
 *    yalnızca tıklanabilir wa.me linki üretir (arka planda otomatik gönderim YOK).
 *  - Kara listedeki müşteriye gönderim ENGELLENİR.
 *  - İletişim izni olmayan müşteri için UYARI verilir.
 *  - Günlük mail limiti aşılırsa gönderim engellenir.
 *  - Tüm gönderimler message_send_logs + international_customer_messages'e loglanır.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/leave.php';
require_once __DIR__ . '/notifications.php'; // build_whatsapp_message_link

function mt_types(): array
{
    return [
        'sale_mail'    => 'Satış — Mail',
        'request_mail' => 'Talep — Mail',
        'sale_wa'      => 'Satış — WhatsApp',
        'request_wa'   => 'Talep — WhatsApp',
        'followup'     => 'Takip',
        'reminder'     => 'Cevap Hatırlatma',
    ];
}
function mt_type_label(string $k): string { return mt_types()[$k] ?? $k; }

/** Kullanılabilir değişkenler (etiket açıklamalı). */
function mt_variables(): array
{
    return [
        '{company_name}'       => 'Kendi firma adınız',
        '{contact_name}'       => 'Müşteri yetkilisinin adı',
        '{customer_company}'   => 'Müşteri firma adı',
        '{product_list_title}' => 'Ürün listesi başlığı',
        '{product_list_text}'  => 'Ürün listesi (düz metin)',
        '{pdf_link}'           => 'Ürün listesi bağlantısı',
        '{valid_until}'        => 'Geçerlilik tarihi',
        '{sender_name}'        => 'Gönderen adı',
        '{sender_email}'       => 'Gönderen e-posta',
        '{sender_whatsapp}'    => 'Gönderen WhatsApp',
        '{website}'            => 'Müşteri web sitesi',
    ];
}

function mt_list(string $type = ''): array
{
    try {
        if ($type !== '' && isset(mt_types()[$type])) {
            $st = db()->prepare('SELECT * FROM message_templates WHERE is_deleted = 0 AND template_type = :t ORDER BY name ASC');
            $st->execute([':t' => $type]);
            return $st->fetchAll();
        }
        return db()->query('SELECT * FROM message_templates WHERE is_deleted = 0 ORDER BY template_type, name')->fetchAll();
    } catch (Throwable $e) { log_error('mt_list: ' . $e->getMessage()); return []; }
}
function mt_active_by_channel(string $channel): array
{
    try {
        $st = db()->prepare('SELECT * FROM message_templates WHERE is_deleted = 0 AND is_active = 1 AND channel = :c ORDER BY name ASC');
        $st->execute([':c' => $channel]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('mt_active_by_channel: ' . $e->getMessage()); return []; }
}
function mt_get(int $id): ?array
{
    try { $st = db()->prepare('SELECT * FROM message_templates WHERE id = :id AND is_deleted = 0 LIMIT 1'); $st->execute([':id' => $id]); return $st->fetch() ?: null; }
    catch (Throwable $e) { log_error('mt_get: ' . $e->getMessage()); return null; }
}
function mt_fields_from_input(array $in): array
{
    $type = (string) ($in['template_type'] ?? 'sale_mail');
    if (!isset(mt_types()[$type])) { $type = 'sale_mail'; }
    $channel = (strpos($type, 'wa') !== false || (string) ($in['channel'] ?? '') === 'whatsapp') ? 'whatsapp' : 'mail';
    if (in_array($type, ['followup', 'reminder'], true) && (string) ($in['channel'] ?? '') === 'whatsapp') { $channel = 'whatsapp'; }
    return [
        'name'          => trim((string) ($in['name'] ?? '')),
        'template_type' => $type,
        'channel'       => $channel,
        'subject'       => trim((string) ($in['subject'] ?? '')),
        'body'          => (string) ($in['body'] ?? ''),
        'is_active'     => isset($in['is_active']) ? 1 : 0,
    ];
}
function mt_create(array $d, ?int $userId): int
{
    if ($d['name'] === '' || trim($d['body']) === '') { return 0; }
    try {
        db()->prepare('INSERT INTO message_templates (name, template_type, channel, subject, body, is_active, created_by, updated_by) VALUES (:n,:t,:c,:s,:b,:a,:by,:by)')
            ->execute([':n' => $d['name'], ':t' => $d['template_type'], ':c' => $d['channel'], ':s' => $d['subject'] ?: null, ':b' => $d['body'], ':a' => (int) $d['is_active'], ':by' => $userId]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { log_error('mt_create: ' . $e->getMessage()); return 0; }
}
function mt_update(int $id, array $d, ?int $userId): bool
{
    try {
        return db()->prepare('UPDATE message_templates SET name=:n, template_type=:t, channel=:c, subject=:s, body=:b, is_active=:a, updated_by=:by WHERE id=:id AND is_deleted = 0')
            ->execute([':n' => $d['name'], ':t' => $d['template_type'], ':c' => $d['channel'], ':s' => $d['subject'] ?: null, ':b' => $d['body'], ':a' => (int) $d['is_active'], ':by' => $userId, ':id' => $id]);
    } catch (Throwable $e) { log_error('mt_update: ' . $e->getMessage()); return false; }
}
function mt_delete(int $id, ?int $userId): bool
{
    try { return db()->prepare('UPDATE message_templates SET is_deleted = 1, updated_by = :by WHERE id = :id')->execute([':by' => $userId, ':id' => $id]); }
    catch (Throwable $e) { log_error('mt_delete: ' . $e->getMessage()); return false; }
}

/** Değişkenleri değerlerle doldur. */
function mt_render(string $text, array $vars): string
{
    return strtr($text, $vars);
}

/** Bir müşteri + (opsiyonel) ürün listesi için değişken haritası. */
function mt_build_vars(array $customer, ?array $contact, ?array $productList, string $productListText = '', string $pdfLink = ''): array
{
    $senderName  = (string) app_setting_get('intl_sender_name', '');
    $senderWa    = (string) app_setting_get('intl_sender_whatsapp', '');
    $companyName = (string) app_setting_get('company_name', defined('SITE_NAME') ? SITE_NAME : '');
    $senderEmail = (string) app_setting_get('mail_from_email', '');
    $contactName = $contact['full_name'] ?? '';
    if ($contactName === '') { $contactName = 'Sir/Madam'; }
    return [
        '{company_name}'       => $companyName,
        '{contact_name}'       => (string) $contactName,
        '{customer_company}'   => (string) ($customer['company_name'] ?? ''),
        '{product_list_title}' => (string) ($productList['title'] ?? ''),
        '{product_list_text}'  => $productListText,
        '{pdf_link}'           => $pdfLink,
        '{valid_until}'        => (string) ($productList['valid_until'] ?? ''),
        '{sender_name}'        => $senderName,
        '{sender_email}'       => $senderEmail,
        '{sender_whatsapp}'    => $senderWa,
        '{website}'            => (string) ($customer['website'] ?? ''),
    ];
}

/* ---- Limit / duplicate / log ---- */
function msg_daily_mail_limit(): int { return (int) app_setting_get('intl_daily_mail_limit', '200'); }
function msg_daily_mail_count(): int
{
    try {
        $st = db()->query("SELECT COUNT(*) FROM message_send_logs WHERE channel = 'mail' AND status = 'sent' AND DATE(created_at) = CURDATE()");
        return (int) $st->fetchColumn();
    } catch (Throwable $e) { log_error('msg_daily_mail_count: ' . $e->getMessage()); return 0; }
}
function msg_mail_remaining(): int { return max(0, msg_daily_mail_limit() - msg_daily_mail_count()); }

/** Bu müşteriye son $hours saatte aynı kanaldan gönderim yapıldı mı? (duplicate uyarısı) */
function msg_recently_sent(int $customerId, string $channel, int $hours = 24): bool
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM message_send_logs WHERE customer_id = :c AND channel = :ch AND status IN ("sent","link") AND created_at >= (NOW() - INTERVAL :h HOUR)');
        // MariaDB INTERVAL :h bind sorunlu olabilir → değeri güvenli inline (int).
        $st = db()->prepare('SELECT COUNT(*) FROM message_send_logs WHERE customer_id = :c AND channel = :ch AND status IN ("sent","link") AND created_at >= (NOW() - INTERVAL ' . (int) $hours . ' HOUR)');
        $st->execute([':c' => $customerId, ':ch' => $channel]);
        return (int) $st->fetchColumn() > 0;
    } catch (Throwable $e) { log_error('msg_recently_sent: ' . $e->getMessage()); return false; }
}

/** Gönderim logu (message_send_logs + international_customer_messages). */
function msg_log(array $d, ?int $userId): void
{
    try {
        db()->prepare('INSERT INTO message_send_logs (customer_id, channel, template_id, product_list_id, to_email, to_phone, subject, status, note, created_by)
            VALUES (:c,:ch,:tid,:pl,:em,:ph,:sub,:st,:note,:by)')
            ->execute([
                ':c' => $d['customer_id'] ?: null, ':ch' => $d['channel'], ':tid' => $d['template_id'] ?: null,
                ':pl' => $d['product_list_id'] ?: null, ':em' => $d['to_email'] ?? null, ':ph' => $d['to_phone'] ?? null,
                ':sub' => $d['subject'] ?? null, ':st' => $d['status'], ':note' => $d['note'] ?? null, ':by' => $userId,
            ]);
        if (!empty($d['customer_id'])) {
            db()->prepare('INSERT INTO international_customer_messages (customer_id, channel, template_id, product_list_id, subject, body, to_email, to_phone, status, created_by)
                VALUES (:c,:ch,:tid,:pl,:sub,:body,:em,:ph,:st,:by)')
                ->execute([
                    ':c' => $d['customer_id'], ':ch' => $d['channel'], ':tid' => $d['template_id'] ?: null, ':pl' => $d['product_list_id'] ?: null,
                    ':sub' => $d['subject'] ?? null, ':body' => $d['body'] ?? null, ':em' => $d['to_email'] ?? null, ':ph' => $d['to_phone'] ?? null,
                    ':st' => $d['status'], ':by' => $userId,
                ]);
            if (in_array($d['status'], ['sent', 'link'], true)) {
                db()->prepare('UPDATE international_customers SET last_message_at = NOW(), communication_status = COALESCE(NULLIF(communication_status,""), :cs) WHERE id = :id')
                    ->execute([':cs' => 'Mesaj gönderildi', ':id' => $d['customer_id']]);
            }
        }
    } catch (Throwable $e) { log_error('msg_log: ' . $e->getMessage()); }
}

/** Genel gönderim raporu (özet). */
function msg_report(int $days = 30): array
{
    $out = ['mail' => 0, 'whatsapp' => 0, 'blocked' => 0, 'by_day' => []];
    try {
        $st = db()->prepare('SELECT channel, status, COUNT(*) c FROM message_send_logs WHERE created_at >= (NOW() - INTERVAL ' . (int) $days . ' DAY) GROUP BY channel, status');
        $st->execute();
        foreach ($st->fetchAll() as $r) {
            if ($r['status'] === 'blocked') { $out['blocked'] += (int) $r['c']; }
            elseif ($r['channel'] === 'mail') { $out['mail'] += (int) $r['c']; }
            elseif ($r['channel'] === 'whatsapp') { $out['whatsapp'] += (int) $r['c']; }
        }
    } catch (Throwable $e) { log_error('msg_report: ' . $e->getMessage()); }
    return $out;
}
