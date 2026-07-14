<?php
declare(strict_types=1);

/**
 * classes/MailService.php
 * Belge e-posta gönderimi. includes/mail.php'nin (config tabanlı tek hesap)
 * ötesinde: SEÇİLEN SMTP PROFİLİ + DEPARTMAN HESABI ile gönderir, PDF ek /
 * CC / BCC destekler ve her gönderimi document_email_logs'a yazar.
 *
 * Gönderen mimarisi (SPF/DKIM/DMARC uyumu için):
 *   - Gerçek From = departman hesabının from_email'i (doğrulanmış SMTP mailbox).
 *   - Reply-To = departman kuralı: kullanıcı / departman / sabit.
 *   - SMTP kimlik doğrulaması daima seçili profilin kullanıcı/şifresidir.
 * Kullanıcının kişisel e-postası ASLA doğrudan SMTP From/kimlik olarak kullanılmaz.
 */

require_once __DIR__ . '/../includes/mail.php';           // PHPMailer + actor yardımcıları
require_once __DIR__ . '/../includes/email_system.php';   // profil/şifre/log

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

final class MailService
{
    /**
     * Belge e-postası gönderir ve loglar.
     * @return array{ok:bool, msg:string, log_id:int, error:?string}
     */
    public static function sendDocument(array $opts): array
    {
        $moduleKey = (string) ($opts['module_key'] ?? '');
        $actor     = mail_build_actor($opts['sender_user'] ?? null);

        // 1) Departman hesabı + SMTP profili çöz
        $dept    = $opts['department_account'] ?? ($moduleKey !== '' ? dept_account_for_module($moduleKey) : null);
        $profile = $opts['smtp_profile'] ?? null;
        if ($profile === null && $dept !== null && !empty($dept['smtp_profile_id'])) {
            $profile = smtp_profile_get((int) $dept['smtp_profile_id']);
        }
        if ($profile === null) { $profile = smtp_profile_default(); }

        // 2) From (departman → profil → config)
        if ($dept !== null && trim((string) $dept['from_email']) !== '') {
            $fromEmail = trim((string) $dept['from_email']);
            $fromName  = trim((string) $dept['from_name']) ?: mail_from_name_base();
        } elseif ($profile !== null && trim((string) $profile['from_email']) !== '') {
            $fromEmail = trim((string) $profile['from_email']);
            $fromName  = trim((string) $profile['from_name']) ?: mail_from_name_base();
        } else {
            $fromEmail = mail_from_email();
            $fromName  = mail_from_display_name($actor['name']);
        }

        // 3) Reply-To (departman kuralı; kullanıcı e-postası yoksa departmana düşer)
        [$replyEmail, $replyName] = self::resolveReplyTo($dept, $actor, $fromEmail, $fromName);

        // 4) Alıcı + CC/BCC (departman varsayılanları + verilenler)
        $to  = trim((string) ($opts['to'] ?? ''));
        $cc  = self::mergeEmails($dept['default_cc'] ?? '', $opts['cc'] ?? '');
        $bcc = self::mergeEmails($dept['default_bcc'] ?? '', $opts['bcc'] ?? '');
        // Kullanıcı auto_cc tercihi
        if ($actor['id'] > 0) {
            $pref = user_email_pref_get($actor['id']);
            if (!empty($pref['auto_cc']) && $actor['email'] !== '' && !in_array($actor['email'], $cc, true)) {
                $cc[] = $actor['email'];
            }
        }

        $subject = (string) ($opts['subject'] ?? '');
        $body    = (string) ($opts['body'] ?? '');
        $isHtml  = (bool) ($opts['html'] ?? true);
        $attachments = (array) ($opts['attachments'] ?? []);

        $logBase = [
            'document_type'   => (string) ($opts['document_type'] ?? ''),
            'document_id'     => $opts['document_id'] ?? null,
            'document_no'     => (string) ($opts['document_no'] ?? ''),
            'sent_by_user_id' => $actor['id'] ?: null,
            'sent_by_name'    => $actor['name'],
            'department'      => (string) ($dept['department_name'] ?? ''),
            'smtp_profile_id' => $profile['id'] ?? null,
            'from_email'      => $fromEmail,
            'reply_to'        => $replyEmail,
            'to_email'        => $to,
            'cc'              => implode(', ', $cc),
            'bcc'             => implode(', ', $bcc),
            'subject'         => $subject,
            'body'            => $body,
            'has_pdf'         => !empty($attachments) ? 1 : 0,
            'pdf_path'        => (string) ($opts['pdf_rel'] ?? ''),
            'doc_link'        => (string) ($opts['doc_link'] ?? ''),
            'resend_of_id'    => $opts['resend_of_id'] ?? null,
        ];

        // 5) Ön kontroller
        if (!is_valid_email($to)) {
            return self::fail($logBase, 'invalid_recipient', 'Geçersiz alıcı e-posta adresi.');
        }
        if ($profile === null) {
            return self::fail($logBase, 'no_smtp_profile', 'Aktif bir SMTP profili bulunamadı. Ayarlar → SMTP Profilleri.');
        }
        $password = smtp_profile_password($profile);
        if (trim((string) $profile['host']) === '' || trim((string) $profile['username']) === '' || $password === '') {
            return self::fail($logBase, 'smtp_incomplete', 'SMTP profili eksik (host/kullanıcı/şifre).');
        }

        // 6) Gönderim
        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host     = (string) $profile['host'];
            $mailer->Port     = (int) $profile['port'];
            $mailer->SMTPAuth = true;
            $mailer->Username = (string) $profile['username'];
            $mailer->Password = $password;
            $mailer->CharSet  = PHPMailer::CHARSET_UTF8;
            $mailer->Timeout  = (int) ($profile['timeout'] ?? 20) ?: 20;

            $enc = strtolower(trim((string) $profile['encryption']));
            if ($enc === 'ssl')      { $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; }
            elseif ($enc === 'tls')  { $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; }
            else                     { $mailer->SMTPSecure = ''; $mailer->SMTPAutoTLS = false; }

            $mailer->setFrom($fromEmail, $fromName);
            if ($replyEmail !== '' && is_valid_email($replyEmail)) {
                $mailer->addReplyTo($replyEmail, $replyName);
            }
            $mailer->addAddress($to);
            foreach ($cc as $addr)  { if (is_valid_email($addr)) { $mailer->addCC($addr); } }
            foreach ($bcc as $addr) { if (is_valid_email($addr)) { $mailer->addBCC($addr); } }

            foreach ($attachments as $att) {
                $path = (string) ($att['path'] ?? '');
                $name = (string) ($att['name'] ?? basename($path));
                if ($path !== '' && is_file($path)) { $mailer->addAttachment($path, $name); }
            }

            $mailer->Subject = $subject;
            if ($isHtml) {
                $mailer->isHTML(true);
                $mailer->Body    = $body;
                $mailer->AltBody = trim(strip_tags($body));
            } else {
                $mailer->isHTML(false);
                $mailer->Body = $body;
            }

            $mailer->send();
            $messageId = trim((string) $mailer->getLastMessageID());
            $logId = document_email_log_insert($logBase + ['status' => 'sent', 'error_message' => null, 'message_id' => $messageId]);
            return ['ok' => true, 'msg' => 'E-posta başarıyla gönderildi.', 'log_id' => $logId, 'error' => null,
                    'to' => $to, 'from' => $fromEmail, 'reply_to' => $replyEmail];
        } catch (PHPMailerException $e) {
            $err = mail_scrub_secret($e->getMessage() . ' | ' . (string) $mailer->ErrorInfo);
            return self::fail($logBase, $err, 'Mail gönderilemedi. SMTP ayarlarını/sunucu bağlantısını kontrol edin.');
        } catch (Throwable $e) {
            $err = mail_scrub_secret($e->getMessage());
            return self::fail($logBase, $err, 'Mail gönderilemedi. SMTP ayarlarını/sunucu bağlantısını kontrol edin.');
        }
    }

    /** Yalnızca SMTP bağlantı/kimlik testi (test maili gönderir). */
    public static function testProfile(array $profile, string $toEmail): array
    {
        $password = smtp_profile_password($profile);
        if (trim((string) $profile['host']) === '')     { return ['ok' => false, 'msg' => 'SMTP host boş.']; }
        if (trim((string) $profile['username']) === '')  { return ['ok' => false, 'msg' => 'SMTP kullanıcı adı boş.']; }
        if ($password === '')                            { return ['ok' => false, 'msg' => 'SMTP şifresi çözülemedi/boş.']; }
        if (!is_valid_email($toEmail))                   { return ['ok' => false, 'msg' => 'Geçerli bir test e-postası girin.']; }

        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host     = (string) $profile['host'];
            $mailer->Port     = (int) $profile['port'];
            $mailer->SMTPAuth = true;
            $mailer->Username = (string) $profile['username'];
            $mailer->Password = $password;
            $mailer->CharSet  = PHPMailer::CHARSET_UTF8;
            $mailer->Timeout  = (int) ($profile['timeout'] ?? 20) ?: 20;
            $enc = strtolower(trim((string) $profile['encryption']));
            if ($enc === 'ssl')     { $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; }
            elseif ($enc === 'tls') { $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; }
            else                    { $mailer->SMTPSecure = ''; $mailer->SMTPAutoTLS = false; }

            $from = trim((string) $profile['from_email']) ?: (string) $profile['username'];
            $mailer->setFrom($from, trim((string) $profile['from_name']) ?: 'SMTP Test');
            $mailer->addAddress($toEmail);
            $mailer->Subject = 'SMTP Test — ' . (string) ($profile['name'] ?? '');
            $mailer->Body    = "Bu bir SMTP test e-postasıdır.\nProfil: " . (string) ($profile['name'] ?? '') . "\nTarih: " . date('d.m.Y H:i');
            $mailer->send();
            return ['ok' => true, 'msg' => 'Test maili gönderildi: ' . $toEmail];
        } catch (Throwable $e) {
            $err = mail_scrub_secret($e->getMessage() . ' | ' . (string) $mailer->ErrorInfo);
            return ['ok' => false, 'msg' => self::friendlySmtpError($err), 'error' => $err];
        }
    }

    /* --------------------------------------------------------------------- */

    private static function resolveReplyTo(?array $dept, array $actor, string $fromEmail, string $fromName): array
    {
        $mode = $dept['reply_to_mode'] ?? 'user';
        if ($mode === 'fixed' && !empty($dept['reply_to_fixed']) && is_valid_email($dept['reply_to_fixed'])) {
            return [(string) $dept['reply_to_fixed'], (string) ($dept['department_name'] ?? $fromName)];
        }
        if ($mode === 'department') {
            return [$fromEmail, $fromName];
        }
        // 'user': işlemi yapan kullanıcı; e-postası yoksa departmana/From'a düş
        if ($actor['email'] !== '' && is_valid_email($actor['email'])) {
            return [$actor['email'], $actor['name'] ?: $fromName];
        }
        return [$fromEmail, $fromName];
    }

    /** Virgül/noktalı virgülle ayrık adres listelerini birleştirip normalize eder. */
    private static function mergeEmails($a, $b): array
    {
        $split = static function ($v): array {
            if (is_array($v)) { return $v; }
            return preg_split('/[;,]+/', (string) $v) ?: [];
        };
        $out = [];
        foreach (array_merge($split($a), $split($b)) as $e) {
            $e = trim((string) $e);
            if ($e !== '' && is_valid_email($e) && !in_array($e, $out, true)) { $out[] = $e; }
        }
        return $out;
    }

    private static function fail(array $logBase, string $error, string $userMsg): array
    {
        $logId = document_email_log_insert($logBase + ['status' => 'failed', 'error_message' => $error, 'message_id' => '']);
        return ['ok' => false, 'msg' => $userMsg, 'log_id' => $logId, 'error' => $error,
                'to' => (string) ($logBase['to_email'] ?? ''), 'from' => (string) ($logBase['from_email'] ?? ''),
                'reply_to' => (string) ($logBase['reply_to'] ?? '')];
    }

    private static function friendlySmtpError(string $err): string
    {
        $e = mb_strtolower($err, 'UTF-8');
        if (str_contains($e, 'authenticate') || str_contains($e, 'auth'))          { return 'Kimlik doğrulama başarısız (kullanıcı adı/şifre).'; }
        if (str_contains($e, 'timed out') || str_contains($e, 'timeout'))          { return 'Bağlantı zaman aşımı — port/host erişilemiyor olabilir.'; }
        if (str_contains($e, 'tls') || str_contains($e, 'ssl') || str_contains($e, 'certificate')) { return 'TLS/SSL hatası — şifreleme türünü/portu kontrol edin.'; }
        if (str_contains($e, 'connect') || str_contains($e, 'refused'))            { return 'Sunucuya bağlanılamadı (port erişilemiyor).'; }
        return 'Gönderim başarısız. Ayrıntı sistem loguna yazıldı.';
    }
}
