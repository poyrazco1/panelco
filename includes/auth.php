<?php
declare(strict_types=1);

/**
 * includes/auth.php
 * Oturum güvenliği, giriş/çıkış, "beni hatırla" ve giriş kaydı.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

/**
 * Güvenli oturumu başlatır. Her korumalı ve giriş sayfasının başında çağrılır.
 * Oturum yoksa "beni hatırla" çerezinden otomatik girişi dener.
 */
function auth_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = is_https();
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Oturum sabitleme (fixation) koruması
    if (empty($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = true;
    }

    // Oturum yoksa "beni hatırla" çerezini dene
    if (empty($_SESSION['user_id'])) {
        auth_try_remember();
    }
}

/**
 * Kullanıcı adı VEYA e-posta ile kaydı getirir (rol bilgisiyle birlikte).
 */
function auth_find_user_by_identifier(string $identifier): ?array
{
    $identifier = trim($identifier);
    // ÖNEMLİ: native prepared statement (EMULATE_PREPARES=false) aynı named
    // placeholder'ı iki kez KABUL ETMEZ (SQLSTATE HY093). Bu yüzden :u ve :e ayrı.
    $stmt = db()->prepare(
        'SELECT u.*, r.name AS role_name, r.permissions AS role_permissions
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         WHERE u.username = :u OR u.email = :e
         LIMIT 1'
    );
    $stmt->execute([':u' => $identifier, ':e' => $identifier]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * Kimlik bilgilerini doğrular (OTURUM AÇMADAN). Geçerliyse true döner ve
 * $foundUser'ı doldurur; değilse false döner ve $reason'a sebep yazar.
 * login.php, install-check.php ve auth-self-test.php AYNI bu fonksiyonu kullanır
 * (tek gerçeklik). Sebep kodları: empty_input, db_exception, user_not_found,
 * inactive_user, password_verify_failed, ok.
 */
function auth_verify_credentials(string $identifier, string $password, ?string &$reason = null, ?array &$foundUser = null): bool
{
    $reason = 'ok';
    $foundUser = null;
    $identifier = trim($identifier);

    if ($identifier === '' || $password === '') {
        $reason = 'empty_input';
        return false;
    }

    try {
        $user = auth_find_user_by_identifier($identifier);
    } catch (Throwable $e) {
        log_error('auth db_exception: ' . $e->getMessage());
        $reason = 'db_exception';
        return false;
    }

    if (!$user) {
        // Sabit zaman: kullanıcı yokken de bir bcrypt doğrula
        password_verify($password, '$2y$12$XNkldLd6TpAVzba2yt2TeeG/51nXQgUUbO5Tvdhl39a5dZOQcZFQy');
        $reason = 'user_not_found';
        return false;
    }

    $foundUser = $user;

    if ((int) $user['is_active'] !== 1) {
        $reason = 'inactive_user';
        return false;
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        $reason = 'password_verify_failed';
        return false;
    }

    $reason = 'ok';
    return true;
}

/**
 * Oturuma kullanıcı bilgilerini yazar.
 */
function auth_set_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']          = (int) $user['id'];
    $_SESSION['username']         = $user['username'];
    $_SESSION['full_name']        = $user['full_name'] ?? '';
    $_SESSION['role_id']          = $user['role_id'] !== null ? (int) $user['role_id'] : 0;
    $_SESSION['role_name']        = $user['role_name'] ?? '';
    $_SESSION['role_permissions'] = $user['role_permissions'] ?? '[]';
    $_SESSION['login_time']       = time();
}

/**
 * Giriş kaydını login_logs tablosuna yazar.
 */
function auth_log_attempt(?int $userId, string $usernameInput, bool $success): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO login_logs (user_id, username_input, ip_address, user_agent, success)
             VALUES (:uid, :uname, :ip, :ua, :ok)'
        );
        $stmt->execute([
            ':uid'   => $userId,
            ':uname' => substr($usernameInput, 0, 190),
            ':ip'    => client_ip(),
            ':ua'    => client_user_agent(),
            ':ok'    => $success ? 1 : 0,
        ]);
    } catch (Throwable $e) {
        log_error('login_logs yazılamadı: ' . $e->getMessage());
    }
}

/**
 * Kullanıcı adı/e-posta + şifre ile giriş dener.
 * Başarılıysa true döner ve oturumu açar; değilse false.
 */
function auth_login(string $identifier, string $password, bool $remember = false): bool
{
    $reason = null;
    $user   = null;
    $ok = auth_verify_credentials($identifier, $password, $reason, $user);

    if (!$ok) {
        // Kullanıcıya genel mesaj gider; log'a net teknik sebep yazılır.
        log_error('login_failed [' . $reason . '] id="' . substr(trim($identifier), 0, 64) . '" ip=' . client_ip());
        $uid = ($user !== null && isset($user['id'])) ? (int) $user['id'] : null;
        auth_log_attempt($uid, $identifier, false); // best-effort; girişi etkilemez
        return false;
    }

    // Gerekliyse şifreyi daha güçlü algoritmayla yeniden hash'le
    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        try {
            db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
        } catch (Throwable $e) {
            log_error('rehash hatası: ' . $e->getMessage());
        }
    }

    // Oturumu aç
    auth_set_session($user);

    // Son giriş zamanı (best-effort)
    try {
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => $user['id']]);
    } catch (Throwable $e) {
        log_error('last_login güncellenemedi: ' . $e->getMessage());
    }

    // Beni hatırla
    if ($remember) {
        auth_issue_remember((int) $user['id']);
    } else {
        auth_clear_remember((int) $user['id']);
    }

    // Giriş kaydı (best-effort; başarısız olsa bile giriş geçerli)
    auth_log_attempt((int) $user['id'], $identifier, true);
    return true;
}

/**
 * "Beni hatırla" token'ı üretir, users tablosuna (hash'li) yazar ve çerez kurar.
 */
function auth_issue_remember(int $userId): void
{
    try {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        db()->prepare('UPDATE users SET remember_token = :t WHERE id = :id')
            ->execute([':t' => $hash, ':id' => $userId]);

        $days   = (int) (defined('REMEMBER_COOKIE_DAYS') ? REMEMBER_COOKIE_DAYS : 30);
        $expire = time() + ($days * 86400);

        setcookie(REMEMBER_COOKIE_NAME, $token, [
            'expires'  => $expire,
            'path'     => '/',
            'domain'   => '',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } catch (Throwable $e) {
        log_error('remember token oluşturulamadı: ' . $e->getMessage());
    }
}

/**
 * "Beni hatırla" token'ını DB'den ve çerezden temizler.
 */
function auth_clear_remember(?int $userId = null): void
{
    try {
        if ($userId !== null) {
            db()->prepare('UPDATE users SET remember_token = NULL WHERE id = :id')
                ->execute([':id' => $userId]);
        }
    } catch (Throwable $e) {
        log_error('remember token temizlenemedi: ' . $e->getMessage());
    }

    if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        setcookie(REMEMBER_COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[REMEMBER_COOKIE_NAME]);
    }
}

/**
 * "Beni hatırla" çerezinden otomatik giriş dener; başarılıysa token'ı yeniler.
 */
function auth_try_remember(): void
{
    $cookieName = defined('REMEMBER_COOKIE_NAME') ? REMEMBER_COOKIE_NAME : '';
    if ($cookieName === '' || empty($_COOKIE[$cookieName])) {
        return;
    }

    $token = (string) $_COOKIE[$cookieName];
    if ($token === '' || !ctype_xdigit($token)) {
        return;
    }
    $hash = hash('sha256', $token);

    try {
        $stmt = db()->prepare(
            'SELECT u.*, r.name AS role_name, r.permissions AS role_permissions
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.remember_token = :t AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':t' => $hash]);
        $user = $stmt->fetch();
    } catch (Throwable $e) {
        log_error('auth_try_remember hatası: ' . $e->getMessage());
        return;
    }

    if (!$user) {
        // Geçersiz çerez -> temizle
        auth_clear_remember();
        return;
    }

    auth_set_session($user);
    // Token'ı döndür (rotate) — çalınmaya karşı
    auth_issue_remember((int) $user['id']);

    try {
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute([':id' => $user['id']]);
    } catch (Throwable $e) {
        log_error('remember last_login güncellenemedi: ' . $e->getMessage());
    }
}

/**
 * Çıkış: oturumu ve "beni hatırla" token'ını temizler.
 */
function auth_logout(): void
{
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    auth_clear_remember($userId);

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Kullanıcı giriş yapmış mı?
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Oturumdaki kullanıcı özeti.
 */
function current_user(): array
{
    return [
        'id'          => (int) ($_SESSION['user_id'] ?? 0),
        'username'    => $_SESSION['username'] ?? '',
        'full_name'   => $_SESSION['full_name'] ?? '',
        'role_id'     => (int) ($_SESSION['role_id'] ?? 0),
        'role_name'   => $_SESSION['role_name'] ?? '',
        'permissions' => $_SESSION['role_permissions'] ?? '[]',
    ];
}

/**
 * Oturumdaki kullanıcının id'si (yoksa null).
 */
function current_user_id(): ?int
{
    return !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Giriş zorunluluğu. Girişsizse login'e yönlendirir.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Bu sayfayı görüntülemek için giriş yapmalısınız.');
        redirect('login.php');
    }
}

/**
 * Şifre sıfırlama e-postası göndermek için hazır kanca (SMTP ileride eklenecek).
 * Şu an teslimat yapılandırılmadığı için false döner; çağıran taraf bağlantıyı
 * ekranda gösterir. SMTP eklendiğinde burada mail gönderip true döndürün.
 */
function send_password_reset_email(string $email, string $link): bool
{
    // Örnek (ileride): mail($email, $subject, $body, $headers) veya SMTP kütüphanesi.
    return false;
}
