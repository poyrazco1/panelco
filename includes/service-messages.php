<?php
declare(strict_types=1);

/**
 * includes/service-messages.php — Servis durum bilgilendirme mesaj altyapısı.
 *
 * İLKE: TOPLU SPAM YOK. Yalnızca ilgili servis kaydına ait, kullanıcı onaylı
 * tekil bilgilendirme mesajı üretilir. Şablonlar ayarlardan düzenlenebilir.
 * Kanallar: WhatsApp (link), E-posta (merkezî mail servisi), SMS (config hazır).
 * Değişkenler: {musteri_adi} {servis_kodu} {urun_adi} {durum} {takip_linki}
 *              {firma_adi} {telefon}
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/service.php';
require_once __DIR__ . '/leave.php'; // app_setting_get / app_setting_set

/** Varsayılan WhatsApp/E-posta şablonları. */
function service_msg_defaults(): array
{
    return [
        'wa'           => "Merhaba {musteri_adi}, {servis_kodu} numaralı servis kaydınızın durumu: {durum}. Ürün: {urun_adi}. Takip: {takip_linki}\n\n{firma_adi}",
        'mail_subject' => "{servis_kodu} — Servis Durumu: {durum}",
        'mail_body'    => "Sayın {musteri_adi},\n\n{servis_kodu} numaralı servis kaydınızın durumu \"{durum}\" olarak güncellenmiştir.\nÜrün: {urun_adi}\n\nSüreci takip linkinden izleyebilirsiniz:\n{takip_linki}\n\nİletişim: {telefon}\n{firma_adi}",
    ];
}

function service_msg_template(string $key): string
{
    $d = service_msg_defaults();
    return (string) app_setting_get('service_msg_' . $key, $d[$key] ?? '');
}

function service_msg_save_templates(array $data): void
{
    foreach (['wa', 'mail_subject', 'mail_body'] as $k) {
        if (array_key_exists($k, $data)) {
            app_setting_set('service_msg_' . $k, trim((string) $data[$k]));
        }
    }
}

/** SMS altyapısı yapılandırıldı mı? (config hazırlığı; gerçek gönderim doküman gelince) */
function service_sms_config(): array
{
    return [
        'enabled'  => (int) app_setting_get('service_sms_enabled', '0') === 1,
        'provider' => (string) app_setting_get('service_sms_provider', ''),
        'sender'   => (string) app_setting_get('service_sms_sender', ''),
    ];
}

/** Mesaj değişkenlerini kayıttan üretir. */
function service_msg_vars(array $rec): array
{
    $device = trim(((string) ($rec['brand_name'] ?? '')) . ' ' . ((string) ($rec['device_model'] ?? '')));
    if ($device === '') { $device = (string) ($rec['device_type'] ?? 'Cihaz'); }
    $company = service_company_info();
    return [
        '{musteri_adi}' => (string) ($rec['customer_name'] ?? ''),
        '{servis_kodu}' => (string) ($rec['reference_code'] ?? ''),
        '{urun_adi}'    => $device,
        '{durum}'       => service_status_label((string) ($rec['status'] ?? '')),
        '{takip_linki}' => (string) (service_public_url($rec) ?? ''),
        '{firma_adi}'   => $company['company_name'] !== '' ? $company['company_name'] : SITE_NAME,
        '{telefon}'     => (string) ($company['company_phone'] ?? ''),
    ];
}

/** Şablonu kayıt değişkenleriyle doldurur. */
function service_msg_render(string $template, array $rec): string
{
    return strtr($template, service_msg_vars($rec));
}

/** Servis kaydı için durum bilgilendirme WhatsApp linki (tekil, onaylı). */
function service_status_whatsapp_link(array $rec): ?string
{
    $phone = trim((string) ($rec['customer_phone'] ?? ''));
    if ($phone === '') { return null; }
    if (!function_exists('build_whatsapp_message_link')) { require_once __DIR__ . '/notifications.php'; }
    $msg = service_msg_render(service_msg_template('wa'), $rec);
    return build_whatsapp_message_link($phone, $msg);
}

/**
 * Servis durum bilgilendirme e-postası gönderir (merkezî mail servisi).
 * @return array{ok:bool, msg:string}
 */
function service_status_send_mail(array $rec): array
{
    $to = trim((string) ($rec['customer_email'] ?? ''));
    if ($to === '') { return ['ok' => false, 'msg' => 'Bu servis kaydında müşteri e-postası bulunmuyor.']; }
    if (!function_exists('mail_send')) { require_once __DIR__ . '/mail.php'; }
    $cfg = mail_config_status();
    if (!$cfg['ok']) { return ['ok' => false, 'msg' => $cfg['msg']]; }
    $subject = service_msg_render(service_msg_template('mail_subject'), $rec);
    $body = service_msg_render(service_msg_template('mail_body'), $rec);
    $res = mail_send($to, (string) ($rec['customer_name'] ?? ''), $subject, $body, ['type' => 'service_status', 'sender_user' => mail_session_user()]);
    return ['ok' => $res['ok'], 'msg' => $res['msg']];
}
