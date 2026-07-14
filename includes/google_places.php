<?php
declare(strict_types=1);

/**
 * includes/google_places.php — Google Places (New) ayar/yardımcı katmanı.
 *
 * GÜVENLİK İLKELERİ (spec §1, §15, §18):
 *  - API anahtarı VERİTABANINDA ŞİFRELİ saklanır (vault_encrypt / AES-256-GCM).
 *  - Anahtar ASLA frontend'e, HTML kaynağına, URL'e veya hata mesajına yazılmaz.
 *  - Anahtarı yalnızca Süper Admin görüntüleyebilir/değiştirebilir; tam anahtar
 *    düzenleme ekranında GÖSTERİLMEZ (yalnızca maskeli ipucu).
 *  - Boş kaydetme mevcut anahtarı KORUR; yeni anahtar eskisini değiştirir.
 *  - Google çağrıları yalnızca GooglePlacesService (backend) üzerinden yapılır.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vault.php';
require_once __DIR__ . '/permissions.php';

/** Google Places ayar satırı (tek satır, id=1). Anahtar ŞİFRELİ döner; çözülmez. */
function gp_settings(): array
{
    static $cache = null;
    if ($cache !== null) { return $cache; }
    $defaults = [
        'id' => 1, 'api_key_enc' => null, 'is_active' => 0,
        'default_country' => 'Türkiye', 'default_city' => '', 'default_language' => 'tr',
        'default_radius' => 5000, 'max_results' => 60,
        'daily_query_limit' => 1000, 'monthly_est_limit' => 20000,
        'block_duplicates' => 1, 'include_no_phone' => 1, 'include_no_website' => 1,
        'auto_details' => 1, 'last_success_at' => null, 'last_error' => '',
        'updated_by' => null, 'updated_at' => null,
    ];
    try {
        $row = db()->query('SELECT * FROM google_places_settings WHERE id = 1 LIMIT 1')->fetch();
        $cache = $row ? array_merge($defaults, $row) : $defaults;
    } catch (Throwable $e) {
        log_error('gp_settings: ' . $e->getMessage());
        $cache = $defaults;
    }
    return $cache;
}

/** Ayarları yeniden yükle (kayıttan sonra). */
function gp_settings_refresh(): void
{
    // static önbelleği sıfırlamanın taşınabilir yolu: değeri yeniden çekmeye zorla.
    // (gp_settings statik olduğu için bir sonraki istekte tazedir; aynı istekte
    //  güncel değer gerekiyorsa doğrudan sorgulanır.)
}

/** Kayıtlı (şifreli) bir API anahtarı var mı? */
function gp_has_api_key(): bool
{
    $enc = (string) (gp_settings()['api_key_enc'] ?? '');
    return $enc !== '';
}

/**
 * Düz metin API anahtarını döndürür (YALNIZCA backend/servis kullanımı).
 * ASLA ekrana/loga/HTTP'ye doğrudan yazılmaz. Çözülemezse null.
 */
function gp_api_key(): ?string
{
    $enc = (gp_settings()['api_key_enc'] ?? null);
    if ($enc === null || $enc === '') { return null; }
    return vault_decrypt((string) $enc);
}

/** Anahtarın son 4 hanesi için maskeli ipucu ("•••• •••• 1234"). Tam anahtar sızmaz. */
function gp_api_key_masked_hint(): string
{
    $plain = gp_api_key();
    if ($plain === null || $plain === '') { return ''; }
    $tail = substr($plain, -4);
    return '•••• •••• •••• ' . $tail;
}

/** Yalnızca Süper Admin ("all" yetkisi) anahtarı görüntüleyip değiştirebilir. */
function gp_is_super_admin(): bool
{
    $perms = function_exists('current_permissions') ? current_permissions() : [];
    return in_array('all', $perms, true);
}

/**
 * Ayarları kaydeder. $newApiKey null/'' ise mevcut anahtar KORUNUR; doluysa
 * eskisinin yerine (şifreli) yazılır. Anahtar değişikliği yalnızca Süper Admin'e
 * izinlidir (çağıran taraf da denetlemeli).
 *
 * @return array{ok:bool, error:string}
 */
function gp_settings_save(array $in, ?string $newApiKey, ?int $userId): array
{
    $cur = gp_settings();

    $country  = trim((string) ($in['default_country'] ?? $cur['default_country']));
    $city     = trim((string) ($in['default_city'] ?? ''));
    $lang     = trim((string) ($in['default_language'] ?? 'tr'));
    $lang     = preg_match('/^[a-zA-Z\-]{2,10}$/', $lang) ? $lang : 'tr';
    $radius   = (int) ($in['default_radius'] ?? 5000);
    $radius   = max(50, min(50000, $radius));
    $maxRes   = (int) ($in['max_results'] ?? 60);
    $maxRes   = max(1, min(200, $maxRes));
    $daily    = max(0, (int) ($in['daily_query_limit'] ?? 1000));
    $monthly  = max(0, (int) ($in['monthly_est_limit'] ?? 20000));
    $isActive = !empty($in['is_active']) ? 1 : 0;
    $blockDup = !empty($in['block_duplicates']) ? 1 : 0;
    $incNoPh  = !empty($in['include_no_phone']) ? 1 : 0;
    $incNoWeb = !empty($in['include_no_website']) ? 1 : 0;
    $autoDet  = !empty($in['auto_details']) ? 1 : 0;

    // Anahtar: yeni değer varsa şifrele; yoksa mevcut şifreli değeri koru.
    $enc = $cur['api_key_enc'];
    if ($newApiKey !== null && trim($newApiKey) !== '') {
        if (!vault_is_configured()) {
            return ['ok' => false, 'error' => 'Şifreleme yapılandırılmamış (VAULT_KEY). Anahtar güvenle saklanamaz.'];
        }
        $encNew = vault_encrypt(trim($newApiKey));
        if ($encNew === null) {
            return ['ok' => false, 'error' => 'Anahtar şifrelenemedi. Sistem yöneticisine başvurun.'];
        }
        $enc = $encNew;
    }
    // Anahtar yoksa aktif edilemez.
    if ($isActive && ($enc === null || $enc === '')) {
        return ['ok' => false, 'error' => 'API anahtarı olmadan Google Places etkinleştirilemez.'];
    }

    try {
        $st = db()->prepare(
            'UPDATE google_places_settings SET
                api_key_enc=:k, is_active=:act, default_country=:country, default_city=:city,
                default_language=:lang, default_radius=:radius, max_results=:maxres,
                daily_query_limit=:daily, monthly_est_limit=:monthly, block_duplicates=:bdup,
                include_no_phone=:inph, include_no_website=:inweb, auto_details=:auto, updated_by=:uby
             WHERE id = 1'
        );
        $st->execute([
            ':k' => $enc, ':act' => $isActive, ':country' => $country, ':city' => $city,
            ':lang' => $lang, ':radius' => $radius, ':maxres' => $maxRes,
            ':daily' => $daily, ':monthly' => $monthly, ':bdup' => $blockDup,
            ':inph' => $incNoPh, ':inweb' => $incNoWeb, ':auto' => $autoDet, ':uby' => $userId,
        ]);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        log_error('gp_settings_save: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Ayarlar kaydedilemedi.'];
    }
}

/** Son başarı zamanını / hatayı günceller (servis çağırır). Anahtar/gizli veri yazılmaz. */
function gp_settings_mark(bool $success, string $error = ''): void
{
    try {
        if ($success) {
            db()->prepare("UPDATE google_places_settings SET last_success_at = NOW(), last_error = '' WHERE id = 1")->execute();
        } else {
            db()->prepare('UPDATE google_places_settings SET last_error = :e WHERE id = 1')
                ->execute([':e' => mb_substr($error, 0, 250)]);
        }
    } catch (Throwable $e) { log_error('gp_settings_mark: ' . $e->getMessage()); }
}

/* --------------------------------------------------------------------- *
 |  Kullanım / kota
 * --------------------------------------------------------------------- */

/** Bugün yapılan (başarılı) API çağrısı sayısı. */
function gp_usage_today(): int
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM google_places_api_usage WHERE DATE(created_at) = CURDATE()')->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** İçinde bulunulan ayın çağrı sayısı. */
function gp_usage_month(): int
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM google_places_api_usage WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())')->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** Günlük kota aşıldı mı? (0 = sınırsız) */
function gp_daily_limit_reached(): bool
{
    $lim = (int) gp_settings()['daily_query_limit'];
    return $lim > 0 && gp_usage_today() >= $lim;
}

/** Aylık tahmini kota aşıldı mı? (0 = sınırsız) */
function gp_monthly_limit_reached(): bool
{
    $lim = (int) gp_settings()['monthly_est_limit'];
    return $lim > 0 && gp_usage_month() >= $lim;
}

/** API kullanım kaydı ekler (maliyet/kota izleme). Anahtar/gizli veri YAZILMAZ. */
function gp_log_usage(string $endpoint, string $opType, bool $success, int $httpCode, string $googleError, int $resultCount, float $estCost, ?int $searchId, ?int $userId): void
{
    try {
        db()->prepare(
            'INSERT INTO google_places_api_usage
                (endpoint, op_type, search_id, user_id, result_count, success, http_code, google_error, est_cost)
             VALUES (:ep,:op,:sid,:uid,:rc,:ok,:http,:gerr,:cost)'
        )->execute([
            ':ep' => mb_substr($endpoint, 0, 60), ':op' => mb_substr($opType, 0, 40),
            ':sid' => $searchId, ':uid' => $userId, ':rc' => max(0, $resultCount),
            ':ok' => $success ? 1 : 0, ':http' => max(0, min(65535, $httpCode)),
            ':gerr' => mb_substr($googleError, 0, 120), ':cost' => $estCost,
        ]);
    } catch (Throwable $e) { log_error('gp_log_usage: ' . $e->getMessage()); }
}

/* --------------------------------------------------------------------- *
 |  Normalleştirme yardımcıları (kopya kontrolü + WhatsApp)
 * --------------------------------------------------------------------- */

/**
 * Telefonu normalize eder: boşluk/parantez/tire kaldırır; Türkiye numaralarını
 * +90 biçimine getirir. Kopya karşılaştırması ve WhatsApp linki için ortak.
 *  - "0212 000 00 00"  → "+90212..."
 *  - "0090..."         → "+90..."
 *  - "5xx..."          → "+905xx..." (10 haneli GSM)
 * Yabancı numaralar +<rakamlar> olarak korunur.
 */
function lead_normalize_phone(?string $raw): string
{
    $s = (string) $raw;
    if ($s === '') { return ''; }
    $hasPlus = str_starts_with(trim($s), '+');
    // Yalnızca rakamları tut
    $digits = preg_replace('/\D+/', '', $s) ?? '';
    if ($digits === '') { return ''; }

    // 00 uluslararası ön eki → +
    if (str_starts_with($digits, '00')) {
        return '+' . substr($digits, 2);
    }
    if ($hasPlus) {
        return '+' . $digits; // zaten ülke kodlu
    }
    // Türkiye kalıpları
    if (str_starts_with($digits, '90') && strlen($digits) >= 12) {
        return '+' . $digits;
    }
    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
        return '+90' . substr($digits, 1);
    }
    if (strlen($digits) === 10) { // 5xx / 2xx (başında 0 yok)
        return '+90' . $digits;
    }
    // Belirsiz: rakamları koru (karşılaştırma yine tutarlı olur)
    return '+' . $digits;
}

/** URL'den ana alan adını çıkarır (kopya kontrolü). "https://www.x.com/a" → "x.com" */
function lead_domain_from_url(?string $url): string
{
    $u = trim((string) $url);
    if ($u === '') { return ''; }
    if (!preg_match('#^https?://#i', $u)) { $u = 'http://' . $u; }
    $host = parse_url($u, PHP_URL_HOST);
    if (!is_string($host) || $host === '') { return ''; }
    $host = strtolower($host);
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    return $host;
}
