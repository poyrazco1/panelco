<?php
declare(strict_types=1);

/**
 * includes/mail.php
 * Merkezî mail servisi. TÜM mail gönderimi bu dosya üzerinden yapılır.
 *
 * İlkeler:
 *  - Yalnızca PHPMailer + gerçek SMTP kullanılır. PHP mail() fonksiyonuna GÜVENİLMEZ.
 *  - Başarı mesajı SADECE PHPMailer send() true dönerse döner (sahte başarı yok).
 *  - Ayarlar yalnızca config.php'den okunur (.env KULLANILMAZ).
 *  - Eksik/placeholder SMTP ayarında mail gönderilmez, açık hata döner.
 *  - Gönderen kimliği: From daima MAIL_FROM; Reply-To işlemi yapan kullanıcıdır.
 *  - SMTP_PASSWORD hiçbir koşulda log'a/ekrana/istisnaya yazılmaz.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/** Kullanıcıya gösterilecek standart mail mesajları. */
const MAIL_MSG_NOT_CONFIGURED = 'Mail gönderimi yapılandırılmamış. Lütfen SMTP ayarlarını tamamlayın.';
const MAIL_MSG_SEND_FAILED    = 'Mail gönderilemedi. Lütfen SMTP ayarlarını veya sunucu bağlantısını kontrol edin.';
const MAIL_MSG_SENT           = 'Mail başarıyla gönderildi.';

/**
 * SMTP yapılandırması eksiksiz ve gerçek mi?
 * @return array{ok:bool, msg:string}
 */
function mail_config_status(): array
{
    $host = defined('SMTP_HOST') ? trim((string) SMTP_HOST) : '';
    $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 0;
    $user = defined('SMTP_USERNAME') ? trim((string) SMTP_USERNAME) : '';
    $pass = defined('SMTP_PASSWORD') ? (string) SMTP_PASSWORD : '';
    $from = defined('MAIL_FROM') ? trim((string) MAIL_FROM) : '';
    $fromName = defined('MAIL_FROM_NAME') ? trim((string) MAIL_FROM_NAME) : '';
    $placeholder = defined('SMTP_PASSWORD_PLACEHOLDER') ? (string) SMTP_PASSWORD_PLACEHOLDER : 'BURAYA_MAIL_SIFRESI_YAZILACAK';

    if ($host === '')                 { return ['ok' => false, 'msg' => MAIL_MSG_NOT_CONFIGURED]; }
    if ($port <= 0)                   { return ['ok' => false, 'msg' => MAIL_MSG_NOT_CONFIGURED]; }
    if ($user === '')                 { return ['ok' => false, 'msg' => MAIL_MSG_NOT_CONFIGURED]; }
    if ($pass === '')                 { return ['ok' => false, 'msg' => MAIL_MSG_NOT_CONFIGURED]; }
    if ($pass === $placeholder)       { return ['ok' => false, 'msg' => MAIL_MSG_NOT_CONFIGURED]; }
    if ($from === '')                 { return ['ok' => false, 'msg' => MAIL_MSG_NOT_CONFIGURED]; }
    if ($fromName === '')             { return ['ok' => false, 'msg' => MAIL_MSG_NOT_CONFIGURED]; }

    return ['ok' => true, 'msg' => ''];
}

/** SMTP yapılandırması gerçekten kullanıma hazır mı? */
function mail_is_configured(): bool
{
    return mail_config_status()['ok'];
}

/**
 * Oturumdaki kullanıcının kimliğini (e-posta + ad) döndürür.
 * Reply-To ve loglama için kullanılır. Bulunamazsa boş alanlar döner.
 * @return array{id:int, name:string, email:string}
 */
function mail_current_user_identity(): array
{
    $id = function_exists('current_user_id') ? (int) (current_user_id() ?? 0) : 0;
    $name = '';
    $email = '';
    if (function_exists('current_user')) {
        $u = current_user();
        $name = trim((string) ($u['full_name'] ?? '')) ?: trim((string) ($u['username'] ?? ''));
    }
    if ($id > 0) {
        try {
            $st = db()->prepare('SELECT email, full_name FROM users WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch();
            if ($row) {
                $email = trim((string) ($row['email'] ?? ''));
                if ($name === '') { $name = trim((string) ($row['full_name'] ?? '')); }
            }
        } catch (Throwable $e) {
            log_error('mail_current_user_identity: ' . $e->getMessage());
        }
    }
    return ['id' => $id, 'name' => $name, 'email' => $email];
}

/**
 * Reply-To hedefini belirler:
 *  1) Oturumdaki kullanıcının geçerli e-postası,
 *  2) yoksa MAIL_REPLY_TO,
 *  3) o da yoksa MAIL_FROM.
 * @return array{email:string, name:string}
 */
function mail_resolve_reply_to(array $identity): array
{
    if ($identity['email'] !== '' && is_valid_email($identity['email'])) {
        return ['email' => $identity['email'], 'name' => $identity['name']];
    }
    $rt = defined('MAIL_REPLY_TO') ? trim((string) MAIL_REPLY_TO) : '';
    if ($rt !== '' && is_valid_email($rt)) {
        $rtName = defined('MAIL_REPLY_TO_NAME') ? trim((string) MAIL_REPLY_TO_NAME) : '';
        return ['email' => $rt, 'name' => $rtName];
    }
    return ['email' => (string) MAIL_FROM, 'name' => (string) MAIL_FROM_NAME];
}

/** Hata metninden SMTP şifresini temizler (güvenlik: şifre asla loglanmaz). */
function mail_scrub_secret(string $text): string
{
    $pass = defined('SMTP_PASSWORD') ? (string) SMTP_PASSWORD : '';
    if ($pass !== '') { $text = str_replace($pass, '***', $text); }
    return $text;
}

/**
 * Mail işlemini logs/mail-YYYY-MM-DD.log dosyasına yazar.
 * SMTP_PASSWORD asla loglanmaz (mail_scrub_secret uygulanır).
 */
function mail_log(array $ctx): void
{
    try {
        $dir = defined('LOG_PATH') ? LOG_PATH : (__DIR__ . '/../logs');
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $line = sprintf(
            "[%s] user_id=%s user_name=%s user_email=%s to=%s type=%s status=%s error=%s\n",
            date('Y-m-d H:i:s'),
            (string) ($ctx['user_id'] ?? ''),
            (string) ($ctx['user_name'] ?? ''),
            (string) ($ctx['user_email'] ?? ''),
            (string) ($ctx['to'] ?? ''),
            (string) ($ctx['type'] ?? ''),
            (string) ($ctx['status'] ?? ''),
            mail_scrub_secret((string) ($ctx['error'] ?? ''))
        );
        @file_put_contents($dir . '/mail-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        log_error('mail_log: ' . $e->getMessage());
    }
}

/**
 * Merkezî mail gönderimi. Gerçek SMTP üzerinden PHPMailer ile gönderir.
 *
 * @param string $to      Alıcı e-posta
 * @param string $toName  Alıcı adı (opsiyonel)
 * @param string $subject Konu
 * @param string $body    Gövde (varsayılan düz metin)
 * @param array  $opts    ['type' => 'birthday'|'anniversary'|'test'|..., 'html' => bool]
 * @return array{ok:bool, msg:string, error:?string}
 */
function mail_send(string $to, string $toName, string $subject, string $body, array $opts = []): array
{
    $type = (string) ($opts['type'] ?? 'generic');
    $identity = mail_current_user_identity();

    // 1) Alıcı doğrulaması
    if (!is_valid_email($to)) {
        mail_log(['user_id' => $identity['id'], 'user_name' => $identity['name'], 'user_email' => $identity['email'],
                  'to' => $to, 'type' => $type, 'status' => 'invalid_recipient', 'error' => 'Geçersiz alıcı e-postası']);
        return ['ok' => false, 'msg' => 'Geçersiz alıcı e-posta adresi.', 'error' => 'invalid_recipient'];
    }

    // 2) SMTP yapılandırma kontrolü — eksik/placeholder ise GÖNDERME
    $cfg = mail_config_status();
    if (!$cfg['ok']) {
        mail_log(['user_id' => $identity['id'], 'user_name' => $identity['name'], 'user_email' => $identity['email'],
                  'to' => $to, 'type' => $type, 'status' => 'not_configured', 'error' => 'SMTP ayarları eksik/placeholder']);
        return ['ok' => false, 'msg' => $cfg['msg'], 'error' => 'not_configured'];
    }

    // 3) Gerçek SMTP gönderimi
    $mailer = new PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host       = (string) SMTP_HOST;
        $mailer->Port       = (int) SMTP_PORT;
        $mailer->SMTPAuth   = true;
        $mailer->Username   = (string) SMTP_USERNAME;
        $mailer->Password   = (string) SMTP_PASSWORD;
        $mailer->CharSet    = PHPMailer::CHARSET_UTF8;
        $mailer->Timeout    = 20;

        $enc = defined('SMTP_ENCRYPTION') ? strtolower(trim((string) SMTP_ENCRYPTION)) : '';
        if ($enc === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
        }

        // From daima teknik panel hesabıdır (kullanıcı görünen gönderen DEĞİL).
        $mailer->setFrom((string) MAIL_FROM, (string) MAIL_FROM_NAME);

        // Reply-To işlemi yapan kullanıcıya döner.
        $rt = mail_resolve_reply_to($identity);
        $mailer->addReplyTo($rt['email'], $rt['name']);

        $mailer->addAddress($to, $toName !== '' ? $toName : $to);
        $mailer->Subject = $subject;

        if (!empty($opts['html'])) {
            $mailer->isHTML(true);
            $mailer->Body    = $body;
            $mailer->AltBody = trim(strip_tags($body));
        } else {
            $mailer->isHTML(false);
            $mailer->Body = $body;
        }

        $sent = $mailer->send(); // true dönmezse istisna atar (exceptions=true)

        if ($sent === true) {
            mail_log(['user_id' => $identity['id'], 'user_name' => $identity['name'], 'user_email' => $identity['email'],
                      'to' => $to, 'type' => $type, 'status' => 'sent', 'error' => '']);
            return ['ok' => true, 'msg' => MAIL_MSG_SENT, 'error' => null];
        }

        // Teorik olarak buraya düşülmez (istisna atar) ama savunmacı davran.
        $err = mail_scrub_secret((string) $mailer->ErrorInfo);
        mail_log(['user_id' => $identity['id'], 'user_name' => $identity['name'], 'user_email' => $identity['email'],
                  'to' => $to, 'type' => $type, 'status' => 'failed', 'error' => $err]);
        return ['ok' => false, 'msg' => MAIL_MSG_SEND_FAILED, 'error' => $err];
    } catch (PHPMailerException $e) {
        $err = mail_scrub_secret($e->getMessage() . ' | ' . (string) $mailer->ErrorInfo);
        mail_log(['user_id' => $identity['id'], 'user_name' => $identity['name'], 'user_email' => $identity['email'],
                  'to' => $to, 'type' => $type, 'status' => 'failed', 'error' => $err]);
        return ['ok' => false, 'msg' => MAIL_MSG_SEND_FAILED, 'error' => $err];
    } catch (Throwable $e) {
        $err = mail_scrub_secret($e->getMessage());
        mail_log(['user_id' => $identity['id'], 'user_name' => $identity['name'], 'user_email' => $identity['email'],
                  'to' => $to, 'type' => $type, 'status' => 'failed', 'error' => $err]);
        return ['ok' => false, 'msg' => MAIL_MSG_SEND_FAILED, 'error' => $err];
    }
}
