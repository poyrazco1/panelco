<?php
declare(strict_types=1);

/**
 * kur.php
 * Tüm kur (döviz) işlemlerinin tek kaynağı. Dashboard ve kur çevirici bunu kullanır.
 *
 * İki kullanım:
 *   1) include edilince  -> yalnızca fonksiyonları tanımlar (çıktı üretmez).
 *   2) doğrudan çağrılınca (kur.php?format=json) -> güncel kurları JSON verir (giriş gerekir).
 *
 * Katman sırası:  taze DB önbelleği -> canlı API -> bayat DB önbelleği -> yedek değer.
 * Kurlar EUR bazlı normalize edilir: { "EUR":1, "USD":x, "TRY":y }.
 * TRY/USD/EUR arası her dönüşüm bu tablodan hesaplanır (çapraz kur).
 */

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';

// Önbellek taze sayılma süresi (saniye). Gereksiz API isteğini önler.
if (!defined('KUR_CACHE_TTL')) {
    define('KUR_CACHE_TTL', 3600); // 1 saat
}

/**
 * Panelde kullanılan para birimleri.
 */
function kur_symbols(): array
{
    return ['TRY', 'USD', 'EUR'];
}

/**
 * Bir URL'yi cURL veya file_get_contents ile getirir (Composer gerekmez).
 */
function kur_http_get(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'PoyrazTechPanel/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 300) {
            return (string) $body;
        }
        if ($err !== '') {
            log_error('Kur cURL hatası: ' . $err);
        }
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => ['timeout' => 8, 'header' => "User-Agent: PoyrazTechPanel/1.0\r\n"],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body !== false) {
            return (string) $body;
        }
    }

    return null;
}

/**
 * Canlı API'den EUR bazlı kurları çeker (USD, TRY). Başarısızsa null.
 * Frankfurter EUR bazlıdır; from=EUR&to=USD,TRY ile alıp normalize ederiz.
 */
function kur_fetch_live(): ?array
{
    $base = rtrim(FRANKFURTER_API_URL, '/');
    $url  = $base . '/latest?from=EUR&to=USD,TRY';

    $raw = kur_http_get($url);
    if ($raw === null) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['rates']) || !is_array($data['rates'])) {
        log_error('Kur API geçersiz yanıt: ' . substr($raw, 0, 200));
        return null;
    }

    $rates = ['EUR' => 1.0];
    foreach ($data['rates'] as $cur => $val) {
        $rates[strtoupper((string) $cur)] = (float) $val;
    }

    // İstenen tüm birimler geldi mi?
    foreach (kur_symbols() as $s) {
        if (!isset($rates[$s])) {
            log_error('Kur API eksik sembol: ' . $s);
            return null;
        }
    }

    return [
        'base'       => 'EUR',
        'date'       => (string) ($data['date'] ?? date('Y-m-d')),
        'rates'      => $rates,
        'source'     => 'live',
        'fetched_at' => date('Y-m-d H:i:s'),
    ];
}

/**
 * Normalize edilmiş kur verisini currency_cache tablosuna yazar (upsert).
 */
function kur_cache_write(array $payload): void
{
    try {
        $expires = date('Y-m-d H:i:s', time() + KUR_CACHE_TTL);
        $json = json_encode([
            'date'  => $payload['date'] ?? date('Y-m-d'),
            'rates' => $payload['rates'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        db()->prepare(
            'INSERT INTO currency_cache (cache_key, base_currency, rates_json, fetched_at, expires_at)
             VALUES (:k, :b, :j, NOW(), :e)
             ON DUPLICATE KEY UPDATE
                base_currency = VALUES(base_currency),
                rates_json    = VALUES(rates_json),
                fetched_at    = VALUES(fetched_at),
                expires_at    = VALUES(expires_at)'
        )->execute([
            ':k' => 'latest',
            ':b' => $payload['base'] ?? 'EUR',
            ':j' => $json,
            ':e' => $expires,
        ]);
    } catch (Throwable $e) {
        log_error('Kur önbelleği yazılamadı: ' . $e->getMessage());
    }
}

/**
 * currency_cache tablosundan son kaydı okur. Yoksa null.
 */
function kur_cache_read(): ?array
{
    try {
        $stmt = db()->prepare(
            'SELECT base_currency, rates_json, fetched_at, expires_at
             FROM currency_cache WHERE cache_key = :k LIMIT 1'
        );
        $stmt->execute([':k' => 'latest']);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        log_error('Kur önbelleği okunamadı: ' . $e->getMessage());
        return null;
    }

    if (!$row || empty($row['rates_json'])) {
        return null;
    }

    $decoded = json_decode((string) $row['rates_json'], true);
    if (!is_array($decoded) || empty($decoded['rates'])) {
        return null;
    }

    $fresh = !empty($row['expires_at']) && strtotime((string) $row['expires_at']) > time();

    return [
        'base'       => (string) ($row['base_currency'] ?? 'EUR'),
        'date'       => (string) ($decoded['date'] ?? date('Y-m-d')),
        'rates'      => $decoded['rates'],
        'source'     => $fresh ? 'cache' : 'stale',
        'fetched_at' => (string) ($row['fetched_at'] ?? ''),
        'fresh'      => $fresh,
    ];
}

/**
 * Son çare yedek kurlar (API + DB önbelleği yoksa). UI kırılmasın diye.
 */
function kur_fallback(): array
{
    return [
        'base'       => 'EUR',
        'date'       => date('Y-m-d'),
        'rates'      => ['EUR' => 1.0, 'USD' => 1.08, 'TRY' => 38.50],
        'source'     => 'fallback',
        'fetched_at' => '',
    ];
}

/**
 * Güncel kurları döndürür.
 * Sıra: taze önbellek -> canlı API -> bayat önbellek -> yedek.
 */
function kur_get_rates(bool $forceRefresh = false): array
{
    // 1) Taze önbellek
    if (!$forceRefresh) {
        $cache = kur_cache_read();
        if ($cache && !empty($cache['fresh'])) {
            unset($cache['fresh']);
            return $cache;
        }
    }

    // 2) Canlı API
    $live = kur_fetch_live();
    if ($live !== null) {
        kur_cache_write($live);
        return $live;
    }

    // 3) Bayat önbellek
    $stale = kur_cache_read();
    if ($stale) {
        unset($stale['fresh']);
        return $stale;
    }

    // 4) Yedek
    return kur_fallback();
}

/**
 * EUR bazlı tablodan iki para birimi arasında çevrim yapar.
 */
function kur_convert(array $ratesPayload, float $amount, string $from, string $to): ?float
{
    $rates = $ratesPayload['rates'] ?? [];
    $from  = strtoupper($from);
    $to    = strtoupper($to);
    if (!isset($rates[$from], $rates[$to]) || (float) $rates[$from] == 0.0) {
        return null;
    }
    return $amount * ((float) $rates[$to] / (float) $rates[$from]);
}

/**
 * Kaynak etiketini kullanıcı için okunur yapar.
 */
function kur_source_label(string $source): string
{
    return match ($source) {
        'live'     => 'Canlı',
        'cache'    => 'Önbellek (güncel)',
        'stale'    => 'Önbellek (bayat)',
        'fallback' => 'Yedek değer',
        default    => $source,
    };
}

/* -------------------------------------------------------------------------
 |  Yeniden kullanılabilir kısayol adları (header widget vb. için)
 * ---------------------------------------------------------------------- */

/** Güncel kurlar (kur_get_rates kısayolu). */
function get_exchange_rates(bool $forceRefresh = false): array
{
    return kur_get_rates($forceRefresh);
}

/**
 * Header/diğer alanlar için hızlı çevrim. Her çağrıda API'ye gitmez;
 * mevcut önbellekli kur tablosunu kullanır.
 */
function convert_currency(float $amount, string $from, string $to, ?array $ratesPayload = null): ?float
{
    $ratesPayload = $ratesPayload ?? kur_get_rates();
    return kur_convert($ratesPayload, $amount, $from, $to);
}

/* -------------------------------------------------------------------------
 |  DOĞRUDAN ÇAĞRI: JSON çıktı (giriş gerektirir)
 * ---------------------------------------------------------------------- */
$__kurDirect = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

if ($__kurDirect && (($_GET['format'] ?? '') === 'json')) {
    require_once __DIR__ . '/includes/auth.php';
    auth_boot();
    header('Content-Type: application/json; charset=utf-8');
    if (!is_logged_in()) {
        http_response_code(403);
        echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(kur_get_rates(isset($_GET['refresh'])), JSON_UNESCAPED_UNICODE);
    exit;
}
