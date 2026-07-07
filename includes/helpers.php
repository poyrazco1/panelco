<?php
declare(strict_types=1);

/**
 * includes/helpers.php
 * Ortak yardımcılar: yol sabitleri, XSS escape, URL üretimi, flash, log.
 */

require_once __DIR__ . '/../config.php';

// Uygulama yolları (helpers.php includes/ içindedir)
if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }
if (!defined('INC_PATH')) { define('INC_PATH', APP_ROOT . '/includes'); }
if (!defined('LOG_PATH')) { define('LOG_PATH', APP_ROOT . '/logs'); }

// Merkezî ikon sistemi (icon())
require_once __DIR__ . '/icons.php';

/**
 * İstek HTTPS üzerinden mi geliyor?
 */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}

/**
 * Etkin taban URL. config'de BASE_URL doluysa onu, boşsa otomatik tespiti kullanır.
 * Böylece hem kök hem alt klasör kurulumunda göreli yollar sağlam çalışır.
 */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (defined('BASE_URL') && BASE_URL !== '') {
        $base = rtrim(BASE_URL, '/');
        return $base;
    }
    $scheme = is_https() ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $web    = '';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $docroot = str_replace('\\', '/', rtrim((string) realpath($_SERVER['DOCUMENT_ROOT']), '/'));
        $approot = str_replace('\\', '/', rtrim((string) realpath(APP_ROOT), '/'));
        if ($docroot !== '' && strpos($approot, $docroot) === 0) {
            $web = substr($approot, strlen($docroot));
        }
    }
    $base = $scheme . '://' . $host . rtrim($web, '/');
    return $base;
}

/**
 * Taban URL'e göre bağlantı üretir.
 */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/**
 * Asset (css/js/img) bağlantısı üretir.
 */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * XSS güvenli çıktı. Ekrana basılan tüm değişkenler bundan geçmelidir.
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Güvenli yönlendirme + çıkış.
 */
function redirect(string $path): void
{
    $location = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $location);
    exit;
}

/**
 * Hata kaydı. logs/ klasörüne yazar (webden okunamaz).
 */
function log_error(string $message): void
{
    $dir = defined('LOG_PATH') ? LOG_PATH : (__DIR__ . '/../logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Kullanıcıya gösterilecek güvenli mesaj (teknik detayı loglar).
 */
function safe_error(string $userMessage, ?string $technical = null): string
{
    if ($technical !== null) {
        log_error($technical);
    }
    return (DEBUG && $technical !== null) ? $technical : $userMessage;
}

/**
 * Flash mesaj ekler (bir sonraki istekte gösterilir). $type: success | error | info
 */
function flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }
}

/**
 * Birikmiş flash mesajlarını döndürür ve temizler.
 */
function get_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/**
 * Form tekrar doldurma için eski değerleri saklar.
 */
function set_old(array $data): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_old'] = $data;
    }
}

/**
 * Saklanan eski form değerini okur.
 */
function old(string $key, string $default = ''): string
{
    return (string) ($_SESSION['_old'][$key] ?? $default);
}

/**
 * Eski form değerlerini temizler.
 */
function clear_old(): void
{
    unset($_SESSION['_old']);
}

/**
 * POST/GET değerini trim'leyerek alır (escape ayrıca e() ile yapılır).
 */
function input(string $key, string $default = ''): string
{
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($val) ? trim($val) : $default;
}

/**
 * E-posta biçim doğrulama.
 */
function is_valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * İstemci IP adresi.
 */
function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * İstemci User-Agent (kısaltılmış).
 */
function client_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/* -------------------------------------------------------------------------
 |  YERLEŞİM (LAYOUT) YARDIMCILARI  (PART 3)
 |  Sayfa akışı:
 |    layout_top('Başlık', 'navAnahtar');
 |      ... içerik ...
 |    layout_bottom();
 * ---------------------------------------------------------------------- */

/**
 * Sayfa kabuğunu açar: header + sidebar + topbar (içerik alanını açar).
 */
function layout_top(string $title, string $navActive = ''): void
{
    $GLOBALS['PAGE_TITLE'] = $title;
    $GLOBALS['NAV_ACTIVE'] = $navActive;
    include INC_PATH . '/layout-header.php';
    include INC_PATH . '/layout-sidebar.php';
    include INC_PATH . '/layout-topbar.php';
}

/**
 * Sayfa kabuğunu kapatır: footer (içerik alanını kapatır).
 */
function layout_bottom(): void
{
    include INC_PATH . '/layout-footer.php';
}

/**
 * Geçerli sayfa başlığı.
 */
function page_title(): string
{
    return $GLOBALS['PAGE_TITLE'] ?? SITE_NAME;
}

/**
 * Aktif menü anahtarı (sidebar'da vurgulamak için).
 */
function nav_active(): string
{
    return $GLOBALS['NAV_ACTIVE'] ?? '';
}

/**
 * Flash mesajlarını basar (içerik alanının üstünde).
 */
function render_flashes(): void
{
    foreach (get_flashes() as $f) {
        $type = in_array($f['type'], ['success', 'error', 'info'], true) ? $f['type'] : 'info';
        echo '<div class="alert alert-' . $type . '">' . e($f['message']) . '</div>';
    }
}

/**
 * Para biçimlendirme (tr-TR).
 */
function fmt_money(float $amount, int $decimals = 2): string
{
    return number_format($amount, $decimals, ',', '.');
}

/**
 * Tarih/saat biçimlendirme.
 */
function fmt_date(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d.m.Y H:i', $ts) : e($datetime);
}

/**
 * Metinden URL-güvenli slug üretir (Türkçe karakter dönüşümlü).
 */
function slugify(string $text): string
{
    $map = ['ç'=>'c','ğ'=>'g','ı'=>'i','İ'=>'i','ö'=>'o','ş'=>'s','ü'=>'u',
            'Ç'=>'c','Ğ'=>'g','Ö'=>'o','Ş'=>'s','Ü'=>'u','â'=>'a','î'=>'i','û'=>'u'];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text) ?? '';
    $text = preg_replace('/-+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text === '' ? 'rol' : $text;
}

/* -------------------------------------------------------------------------
 |  GLOBAL HATA YÖNETİMI  (PART 5)
 |  DEBUG=false iken kullanıcıya teknik hata gösterilmez; her şey loglanır.
 * ---------------------------------------------------------------------- */

/**
 * Kullanıcıya sade 500 sayfası gösterir (yalnızca DEBUG kapalıyken).
 */
function app_fatal_response(): void
{
    if (DEBUG || headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8">'
       . '<div style="font:15px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;'
       . 'max-width:520px;margin:60px auto;padding:24px;border:1px solid #E2E5EA;'
       . 'border-radius:8px;color:#1F2937;background:#fff">'
       . 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
       . '</div>';
    exit;
}

/**
 * Global hata/istisna/kapanış yakalayıcılarını bir kez kurar.
 */
function app_init_errors(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    error_reporting(E_ALL);
    ini_set('display_errors', DEBUG ? '1' : '0');
    ini_set('log_errors', '0'); // loglamayı kendimiz yapıyoruz

    set_exception_handler(function (Throwable $e): void {
        log_error('Yakalanmayan istisna: ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (DEBUG) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<pre style="white-space:pre-wrap">' . e((string) $e) . '</pre>';
            return;
        }
        app_fatal_response();
    });

    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        log_error('PHP hatası [' . $severity . ']: ' . $message . ' @ ' . $file . ':' . $line);
        if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            app_fatal_response();
        }
        // DEBUG açıksa PHP'nin de göstermesine izin ver (false), kapalıysa bastır (true)
        return !DEBUG;
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            log_error('Ölümcül hata: ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
            app_fatal_response();
        }
    });
}

// Yakalayıcıları etkinleştir (helpers her sayfada erken yüklenir)
app_init_errors();

if (!function_exists('pub_logo_url')) {
    /**
     * Public sayfalarda kullanılacak firma logosu URL'i.
     * app_settings 'company_logo' anahtarında göreli yol tutulur; dosya varsa asset URL döner.
     */
    function pub_logo_url(): ?string
    {
        static $cached = false;
        static $val = null;
        if ($cached) { return $val; }
        $cached = true;
        try {
            if (!function_exists('db')) { return $val = null; }
            $st = db()->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'company_logo' LIMIT 1");
            $st->execute();
            $path = trim((string) ($st->fetchColumn() ?: ''));
            if ($path === '') { return $val = null; }
            $rel = ltrim($path, '/');
            if (is_file(APP_ROOT . '/' . $rel)) { return $val = asset($rel); }
        } catch (Throwable $e) {
            // sessiz
        }
        return $val = null;
    }
}

if (!function_exists('log_activity')) {
    /**
     * İşlem denetim logu (activity_logs). Asla exception fırlatmaz.
     * $result: 'success' | 'failed'
     */
    function log_activity(string $action, ?string $entityType = null, ?int $entityId = null,
                          ?string $referenceCode = null, string $result = 'success', ?string $detail = null): void
    {
        try {
            if (!function_exists('db')) { return; }
            $uid = function_exists('current_user_id') ? current_user_id() : null;
            $ip = function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? null);
            db()->prepare(
                'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, reference_code, ip, result, detail)
                 VALUES (:uid, :ac, :et, :eid, :ref, :ip, :res, :det)'
            )->execute([
                ':uid' => $uid, ':ac' => $action, ':et' => $entityType, ':eid' => $entityId,
                ':ref' => $referenceCode, ':ip' => $ip, ':res' => $result,
                ':det' => ($detail !== null ? mb_substr($detail, 0, 2000) : null),
            ]);
        } catch (Throwable $e) {
            log_error('log_activity: ' . $e->getMessage());
        }
    }
}
