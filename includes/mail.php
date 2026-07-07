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

/** app_settings'ten tekil değer okur (mail görünen gönderen override'ları için). */
function mail_setting(string $key): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) { return $cache[$key]; }
    $val = '';
    try {
        $st = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        if ($v !== false) { $val = trim((string) $v); }
    } catch (Throwable $e) {
        log_error('mail_setting: ' . $e->getMessage());
    }
    return $cache[$key] = $val;
}

/**
 * Görünen From e-postası: app_settings 'mail_from_email' geçerliyse onu, yoksa
 * config MAIL_FROM'u kullanır. (SMTP giriş hesabı DEĞİŞMEZ; bu yalnızca From başlığı.)
 */
function mail_from_email(): string
{
    $s = mail_setting('mail_from_email');
    if ($s !== '' && is_valid_email($s)) { return $s; }
    return (string) MAIL_FROM;
}

/** Görünen From taban adı: app_settings 'mail_from_name' doluysa onu, yoksa MAIL_FROM_NAME. */
function mail_from_name_base(): string
{
    $s = mail_setting('mail_from_name');
    return $s !== '' ? $s : (string) MAIL_FROM_NAME;
}

/**
 * Mail gönderimini yapan kullanıcıyı (actor) normalize eder.
 *
 * Kaynak: açıkça verilen $user dizisi; yoksa oturumdaki kullanıcı (session + DB).
 * Farklı şema alan adlarını (full_name/name/first_name+last_name/username, email)
 * güvenle destekler. Bulunamayan alanlar boş döner.
 *
 * @param array|null $user Endpoint'ten aktarılan kullanıcı dizisi (opsiyonel)
 * @return array{id:int, name:string, email:string, username:string}
 */
function mail_build_actor(?array $user = null): array
{
    // 1) Açıkça verilen kullanıcı dizisi
    if (is_array($user) && !empty($user)) {
        return mail_normalize_actor($user);
    }

    // 2) Oturumdaki kullanıcı: önce session, e-posta için DB.
    $id = function_exists('current_user_id') ? (int) (current_user_id() ?? 0) : 0;
    $data = ['id' => $id];
    if (function_exists('current_user')) {
        $u = current_user();
        $data['full_name'] = (string) ($u['full_name'] ?? '');
        $data['username']  = (string) ($u['username'] ?? '');
        $data['role']      = (string) ($u['role_name'] ?? '');
        if (!empty($u['email'])) { $data['email'] = (string) $u['email']; }
    }
    if ($id > 0 && empty($data['email'])) {
        try {
            $st = db()->prepare('SELECT email, full_name FROM users WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch();
            if ($row) {
                $data['email'] = (string) ($row['email'] ?? '');
                if (empty($data['full_name'])) { $data['full_name'] = (string) ($row['full_name'] ?? ''); }
            }
        } catch (Throwable $e) {
            log_error('mail_build_actor: ' . $e->getMessage());
        }
    }
    return mail_normalize_actor($data);
}

/**
 * Oturumdaki kullanıcıyı ham alanlarıyla (id, full_name, username, email, role)
 * döndürür — endpoint'lerin MailService'e `sender_user` olarak aktarması içindir.
 * E-posta session'da yoksa users tablosundan çekilir.
 * @return array{id:int, full_name:string, username:string, email:string, role:string}
 */
function mail_session_user(): array
{
    $out = ['id' => 0, 'full_name' => '', 'username' => '', 'email' => '', 'role' => ''];
    if (function_exists('current_user')) {
        $u = current_user();
        $out['id']        = (int) ($u['id'] ?? 0);
        $out['full_name'] = (string) ($u['full_name'] ?? '');
        $out['username']  = (string) ($u['username'] ?? '');
        $out['role']      = (string) ($u['role_name'] ?? '');
        if (!empty($u['email'])) { $out['email'] = (string) $u['email']; }
    }
    if ($out['id'] > 0 && $out['email'] === '') {
        try {
            $st = db()->prepare('SELECT email, full_name FROM users WHERE id = ? LIMIT 1');
            $st->execute([$out['id']]);
            $row = $st->fetch();
            if ($row) {
                $out['email'] = trim((string) ($row['email'] ?? ''));
                if ($out['full_name'] === '') { $out['full_name'] = (string) ($row['full_name'] ?? ''); }
            }
        } catch (Throwable $e) {
            log_error('mail_session_user: ' . $e->getMessage());
        }
    }
    return $out;
}

/** Ham kullanıcı dizisini {id,name,email,username} biçimine indirger. */
function mail_normalize_actor(array $u): array
{
    return [
        'id'       => (int) ($u['id'] ?? $u['user_id'] ?? 0),
        'name'     => mail_resolve_actor_name($u),
        'email'    => mail_resolve_actor_email($u),
        'username' => trim((string) ($u['username'] ?? '')),
    ];
}

/**
 * Actor görünen adı: full_name → name → first_name+last_name → username → ''.
 * (Boş dönerse From adında yalnızca MAIL_FROM_NAME kullanılır.)
 */
function mail_resolve_actor_name(array $u): string
{
    $full = trim((string) ($u['full_name'] ?? ''));
    if ($full !== '') { return $full; }

    $name = trim((string) ($u['name'] ?? ''));
    if ($name !== '') { return $name; }

    $first = trim((string) ($u['first_name'] ?? ''));
    $last  = trim((string) ($u['last_name'] ?? ''));
    $fl = trim($first . ' ' . $last);
    if ($fl !== '') { return $fl; }

    $username = trim((string) ($u['username'] ?? ''));
    if ($username !== '') { return $username; }

    return '';
}

/** Actor e-postası: geçerli user.email → '' (fallback mail_send içinde çözümlenir). */
function mail_resolve_actor_email(array $u): string
{
    $email = trim((string) ($u['email'] ?? ''));
    return ($email !== '' && is_valid_email($email)) ? $email : '';
}

/**
 * From görünen adını üretir: "Rabia Dilek - PoyrazTech Panel".
 * Actor adı yoksa yalnızca MAIL_FROM_NAME döner.
 */
function mail_from_display_name(string $actorName): string
{
    $base = mail_from_name_base();
    $actorName = trim($actorName);
    if ($actorName === '') { return $base; }
    return $base !== '' ? ($actorName . ' - ' . $base) : $actorName;
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
            "[%s] %s | actor_id=%s | actor_name=%s | actor_email=%s | from=%s | reply_to=%s | to=%s | type=%s | status=%s | error=%s\n",
            date('Y-m-d H:i:s'),
            (string) ($ctx['type'] ?? 'mail') . '_mail',
            (string) ($ctx['user_id'] ?? ''),
            (string) ($ctx['user_name'] ?? ''),
            (string) ($ctx['user_email'] ?? ''),
            (string) ($ctx['from'] ?? ''),
            (string) ($ctx['reply_to'] ?? ''),
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
 * Çok kullanıcılı gönderen kimliği:
 *   - From e-postası daima MAIL_FROM (teknik SMTP hesabı).
 *   - From görünen adı: "{ActorAdı} - {MAIL_FROM_NAME}" (örn. "Rabia Dilek - PoyrazTech Panel").
 *   - Reply-To: actor e-postası → yoksa MAIL_REPLY_TO → yoksa MAIL_FROM (fallback loglanır).
 *   - MAIL_ALLOW_ACTOR_FROM=true ise From e-postası da actor'ın adresine ayarlanır
 *     (bazı SMTP sunucuları reddedebilir; varsayılan güvenli mod kapalıdır).
 *
 * @param string $to      Alıcı e-posta
 * @param string $toName  Alıcı adı (opsiyonel)
 * @param string $subject Konu
 * @param string $body    Gövde (varsayılan düz metin)
 * @param array  $opts    ['type'|'mail_type' => '...', 'html' => bool, 'sender_user' => array]
 * @return array{ok:bool, msg:string, error:?string}
 */
function mail_send(string $to, string $toName, string $subject, string $body, array $opts = []): array
{
    $type  = (string) ($opts['type'] ?? $opts['mail_type'] ?? 'generic');
    $actor = mail_build_actor($opts['sender_user'] ?? null);

    // From görünen adı actor'a göre değişir; Reply-To actor'a döner.
    // From e-postası: app_settings override → yoksa config MAIL_FROM (SMTP giriş hesabı değil).
    $fromEmail   = mail_from_email();
    $fromName    = mail_from_display_name($actor['name']);
    $allowFrom   = defined('MAIL_ALLOW_ACTOR_FROM') && MAIL_ALLOW_ACTOR_FROM === true;
    if ($allowFrom && $actor['email'] !== '') { $fromEmail = $actor['email']; }

    // Reply-To çözümleme + fallback tespiti (loglama için).
    $replyEmail = '';
    $replyName  = $actor['name'];
    $replyFallback = '';
    if ($actor['email'] !== '') {
        $replyEmail = $actor['email'];
    } else {
        $rt = defined('MAIL_REPLY_TO') ? trim((string) MAIL_REPLY_TO) : '';
        if ($rt !== '' && is_valid_email($rt)) {
            $replyEmail = $rt;
            $replyName  = defined('MAIL_REPLY_TO_NAME') ? trim((string) MAIL_REPLY_TO_NAME) : '';
            if ($replyName === '') { $replyName = (string) MAIL_FROM_NAME; }
            $replyFallback = 'MAIL_REPLY_TO';
        } else {
            $replyEmail = mail_from_email();
            $replyName  = mail_from_name_base();
            $replyFallback = 'MAIL_FROM';
        }
    }

    $logBase = [
        'user_id'    => $actor['id'],
        'user_name'  => $actor['name'],
        'user_email' => $actor['email'],
        'from'       => $fromName . ' <' . $fromEmail . '>',
        'reply_to'   => $replyName !== '' ? ($replyName . ' <' . $replyEmail . '>') : $replyEmail,
        'to'         => $to,
        'type'       => $type,
    ];

    // 1) Alıcı doğrulaması
    if (!is_valid_email($to)) {
        mail_log($logBase + ['status' => 'invalid_recipient', 'error' => 'Geçersiz alıcı e-postası']);
        return ['ok' => false, 'msg' => 'Geçersiz alıcı e-posta adresi.', 'error' => 'invalid_recipient'];
    }

    // 2) SMTP yapılandırma kontrolü — eksik/placeholder ise GÖNDERME
    $cfg = mail_config_status();
    if (!$cfg['ok']) {
        mail_log($logBase + ['status' => 'not_configured', 'error' => 'SMTP ayarları eksik/placeholder']);
        return ['ok' => false, 'msg' => $cfg['msg'], 'error' => 'not_configured'];
    }

    // Reply-To fallback bilgisi (actor e-postası yoksa) ayrıca loglanır.
    if ($replyFallback !== '') {
        mail_log($logBase + ['status' => 'reply_to_fallback', 'error' => 'Actor e-postası yok, Reply-To ' . $replyFallback . ' değerine düştü']);
    }

    // 3) Gerçek SMTP gönderimi
    $mailer = new PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host       = (string) SMTP_HOST;
        $mailer->Port       = (int) SMTP_PORT;
        $mailer->SMTPAuth   = true;
        $mailer->Username   = (string) SMTP_USERNAME; // yalnızca teknik giriş; görünen gönderen DEĞİL
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

        // From: e-posta MAIL_FROM (veya izinliyse actor), görünen ad "Actor - Panel".
        $mailer->setFrom($fromEmail, $fromName);

        // Reply-To: işlemi yapan kullanıcıya döner (fallback yukarıda çözüldü).
        if ($replyEmail !== '' && is_valid_email($replyEmail)) {
            $mailer->addReplyTo($replyEmail, $replyName);
        }

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
            mail_log($logBase + ['status' => 'sent', 'error' => '']);
            return ['ok' => true, 'msg' => MAIL_MSG_SENT, 'error' => null];
        }

        // Teorik olarak buraya düşülmez (istisna atar) ama savunmacı davran.
        $err = mail_scrub_secret((string) $mailer->ErrorInfo);
        mail_log($logBase + ['status' => 'failed', 'error' => $err]);
        return ['ok' => false, 'msg' => MAIL_MSG_SEND_FAILED, 'error' => $err];
    } catch (PHPMailerException $e) {
        $err = mail_scrub_secret($e->getMessage() . ' | ' . (string) $mailer->ErrorInfo);
        mail_log($logBase + ['status' => 'failed', 'error' => $err]);
        return ['ok' => false, 'msg' => MAIL_MSG_SEND_FAILED, 'error' => $err];
    } catch (Throwable $e) {
        $err = mail_scrub_secret($e->getMessage());
        mail_log($logBase + ['status' => 'failed', 'error' => $err]);
        return ['ok' => false, 'msg' => MAIL_MSG_SEND_FAILED, 'error' => $err];
    }
}
