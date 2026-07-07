<?php
declare(strict_types=1);

/**
 * includes/tsoft.php
 * T-Soft REST API istemcisi — Part 1A: bağlantı, token alma, ham getProducts testi.
 * Bu partta hiçbir SQL/DB işlemi yapılmaz; ürünler kaydedilmez (Part 1B).
 *
 * Güvenlik: token asla ekrana düz basılmaz, loglara tam yazılmaz (yalnız maskeli).
 * cURL hataları kullanıcıya ham gösterilmez; logs/ klasörüne yazılır.
 */

require_once __DIR__ . '/helpers.php';

/** Yapılandırılmış temel URL (sondaki / temizlenir). */
function tsoft_base_url(): string
{
    $base = defined('TSOFT_BASE_URL') ? (string) TSOFT_BASE_URL : 'http://www.poyraztoner.com/rest1';
    return rtrim($base, '/');
}

/** auth/login için opsiyonel alanlar (config: 'k1=v1&k2=v2' ya da ''). */
function tsoft_login_fields(): array
{
    $raw = defined('TSOFT_LOGIN_FIELDS') ? (string) TSOFT_LOGIN_FIELDS : '';
    if (trim($raw) === '') { return []; }
    $out = [];
    parse_str($raw, $out);
    return is_array($out) ? $out : [];
}

/** Token'ı maskele: ilk 4 + yıldız + son 4. */
function tsoft_mask_token(?string $token): string
{
    $t = (string) $token;
    $len = strlen($t);
    if ($len === 0) { return ''; }
    if ($len <= 8) { return str_repeat('*', $len); }
    return substr($t, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($t, -4);
}

/**
 * T-Soft REST'e POST isteği. Yapı:
 * ['ok'=>bool, 'http_status'=>int, 'error'=>?string, 'is_json'=>bool,
 *  'data'=>mixed|null, 'raw'=>string, 'tsoft_success'=>?bool, 'message'=>string]
 */
function tsoft_request(string $endpoint, array $fields = []): array
{
    $result = [
        'ok' => false, 'http_status' => 0, 'error' => null, 'is_json' => false,
        'data' => null, 'raw' => '', 'tsoft_success' => null, 'message' => '',
    ];

    if (!function_exists('curl_init')) {
        $result['error'] = 'curl_unavailable';
        log_error('tsoft_request: PHP cURL eklentisi yok.');
        return $result;
    }

    $url = tsoft_base_url() . '/' . ltrim($endpoint, '/');
    $timeout = defined('TSOFT_TIMEOUT') ? (int) TSOFT_TIMEOUT : 30;
    $connect = defined('TSOFT_CONNECT_TIMEOUT') ? (int) TSOFT_CONNECT_TIMEOUT : 10;
    $sslVerify = defined('TSOFT_SSL_VERIFY') ? (bool) TSOFT_SSL_VERIFY : true;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); // T-Soft örneği dizi (form) bekliyor
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PoyrazTechPanel/1.0');

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $result['http_status'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // cURL taşıma hatası (token/fields ASLA loglanmaz)
    if ($errno !== 0 || $response === false) {
        $result['error'] = 'curl_error';
        log_error(sprintf('tsoft_request: endpoint=%s errno=%d err=%s http=%d', $endpoint, $errno, $err, $result['http_status']));
        return $result;
    }

    $response = (string) $response;
    $result['raw'] = $response;

    if (trim($response) === '') {
        $result['error'] = 'empty_response';
        log_error('tsoft_request: boş yanıt, endpoint=' . $endpoint . ' http=' . $result['http_status']);
        return $result;
    }

    // JSON çöz
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $result['is_json'] = true;
        $result['data'] = $decoded;
        if (array_key_exists('success', $decoded)) {
            $result['tsoft_success'] = (bool) $decoded['success'];
        }
        $result['message'] = tsoft_result_message($decoded);
    }

    // Taşıma başarılı sayılır (T-Soft düzeyinde başarı ayrı: tsoft_success)
    $result['ok'] = ($result['http_status'] >= 200 && $result['http_status'] < 400);
    return $result;
}

/** T-Soft yanıtından okunabilir mesaj çıkarır (message/error alanları). */
function tsoft_result_message(array $decoded): string
{
    $collect = static function ($m): string {
        if (is_string($m)) { return trim($m); }
        if (is_array($m)) {
            $parts = [];
            foreach ($m as $item) {
                if (is_string($item)) { $parts[] = $item; }
                elseif (is_array($item)) {
                    foreach (['text', 'message', 'msg', 'description'] as $k) {
                        if (isset($item[$k]) && is_string($item[$k])) { $parts[] = $item[$k]; break; }
                    }
                }
            }
            return trim(implode(' ', array_filter($parts)));
        }
        return '';
    };
    foreach (['message', 'error', 'errors', 'Message'] as $key) {
        if (isset($decoded[$key])) {
            $msg = $collect($decoded[$key]);
            if ($msg !== '') { return $msg; }
        }
    }
    return '';
}

/**
 * Yanıttan token'ı çeşitli yapılarda arar:
 * token | data.token | data[0].token | response.token | result.token
 * $response: tsoft_request sonucu (structured) veya çözülmüş gövde (array).
 */
function tsoft_extract_token($response): ?string
{
    // structured sonuç geldiyse gövdeyi al
    $body = $response;
    if (is_array($response) && array_key_exists('data', $response) && array_key_exists('raw', $response)) {
        $body = $response['data'];
    }
    if (!is_array($body)) { return null; }

    $val = static function ($v): ?string {
        if (is_string($v) && $v !== '') { return $v; }
        if (is_int($v)) { return (string) $v; }
        return null;
    };

    if (isset($body['token'])) { $t = $val($body['token']); if ($t !== null) { return $t; } }
    if (isset($body['data']) && is_array($body['data'])) {
        if (isset($body['data']['token'])) { $t = $val($body['data']['token']); if ($t !== null) { return $t; } }
        if (isset($body['data'][0]) && is_array($body['data'][0]) && isset($body['data'][0]['token'])) {
            $t = $val($body['data'][0]['token']); if ($t !== null) { return $t; }
        }
    }
    if (isset($body['response']) && is_array($body['response']) && isset($body['response']['token'])) {
        $t = $val($body['response']['token']); if ($t !== null) { return $t; }
    }
    if (isset($body['result']) && is_array($body['result']) && isset($body['result']['token'])) {
        $t = $val($body['result']['token']); if ($t !== null) { return $t; }
    }
    return null;
}

/** cURL/T-Soft sonucunu kullanıcı dostu Türkçe mesaja çevirir (ham detay yok). */
function tsoft_user_error(array $res): string
{
    if (($res['error'] ?? null) === 'curl_unavailable') { return 'Sunucuda PHP cURL eklentisi etkin değil.'; }
    if (($res['error'] ?? null) === 'curl_error')       { return 'T-Soft sunucusuna bağlanılamadı (zaman aşımı veya ağ hatası).'; }
    if (($res['error'] ?? null) === 'empty_response')   { return 'T-Soft boş yanıt döndürdü.'; }

    $msg = (string) ($res['message'] ?? '');
    if ($msg !== '' && (stripos($msg, 'ip') !== false || stripos($msg, 'yetki') !== false)) {
        return 'Yetkisiz IP adresi: sunucunuzun IP adresi T-Soft API tarafında yetkilendirilmemiş görünüyor. (' . $msg . ')';
    }
    if (($res['http_status'] ?? 0) >= 400) { return 'T-Soft geçersiz yanıt döndürdü (HTTP ' . (int) $res['http_status'] . ').'; }
    if ($msg !== '') { return $msg; }
    return 'Token alınamadı. T-Soft yanıtı beklenen yapıda değil.';
}

/**
 * auth/login → token. Yapı:
 * ['ok'=>bool, 'token'=>?string, 'masked'=>string, 'error'=>?string, 'result'=>array]
 * Not: token çağıran tarafta kısa süreli kullanılır; kalıcı saklanmaz (Part 1A).
 */
function tsoft_login(): array
{
    $res = tsoft_request('auth/login/', tsoft_login_fields());
    if (!$res['ok']) {
        return ['ok' => false, 'token' => null, 'masked' => '', 'error' => tsoft_user_error($res), 'result' => $res];
    }
    $token = tsoft_extract_token($res);
    if ($token === null || $token === '') {
        return ['ok' => false, 'token' => null, 'masked' => '', 'error' => tsoft_user_error($res), 'result' => $res];
    }
    // Loglara token'ın tamamı YAZILMAZ; yalnız maskeli.
    log_activity('tsoft_login', 'tsoft', null, null, 'success', 'token=' . tsoft_mask_token($token));
    return ['ok' => true, 'token' => $token, 'masked' => tsoft_mask_token($token), 'error' => null, 'result' => $res];
}

/**
 * product/getProducts ham yanıtı. Token parametre olarak verilmeli.
 * Bu partta yanıt PARÇALANMAZ; ham structured sonuç döner (Part 1B'de işlenecek).
 */
function tsoft_get_products_raw(array $params = []): array
{
    if (empty($params['token'])) {
        return ['ok' => false, 'http_status' => 0, 'error' => 'missing_token', 'is_json' => false,
                'data' => null, 'raw' => '', 'tsoft_success' => null, 'message' => 'Token eksik.'];
    }
    // Güvenli sınırlar (opsiyonel alanlar)
    if (isset($params['limit']) && $params['limit'] !== '') { $params['limit'] = max(1, min(100, (int) $params['limit'])); }
    if (isset($params['start']) && $params['start'] !== '') { $params['start'] = max(0, (int) $params['start']); }
    if (isset($params['columns'])) {
        $params['columns'] = trim((string) $params['columns']);
        if ($params['columns'] === '') { unset($params['columns']); }
    }
    return tsoft_request('product/getProducts', $params);
}

/**
 * Test/gösterim yardımcı: yanıttan ürün dizisini ve toplam sayıyı bulmaya çalışır.
 * Sadece görüntüleme amaçlı; ürünleri kalıcı işlemez.
 * Döner: ['products'=>array, 'total'=>?int]
 */
function tsoft_locate_products(?array $decoded): array
{
    if (!is_array($decoded)) { return ['products' => [], 'total' => null]; }

    $total = null;
    foreach (['count', 'total', 'totalCount', 'productCount'] as $k) {
        if (isset($decoded[$k]) && is_numeric($decoded[$k])) { $total = (int) $decoded[$k]; break; }
    }
    if ($total === null && isset($decoded['summary']) && is_array($decoded['summary'])) {
        foreach (['total', 'count', 'totalCount'] as $k) {
            if (isset($decoded['summary'][$k]) && is_numeric($decoded['summary'][$k])) { $total = (int) $decoded['summary'][$k]; break; }
        }
    }

    $products = [];
    if (isset($decoded['data'])) {
        $d = $decoded['data'];
        if (is_array($d)) {
            if (isset($d[0]) && is_array($d[0])) { $products = $d; }
            elseif (isset($d['products']) && is_array($d['products'])) { $products = $d['products']; }
            elseif (isset($d['data']) && is_array($d['data'])) { $products = $d['data']; }
        }
    }
    if (empty($products) && isset($decoded['products']) && is_array($decoded['products'])) {
        $products = $decoded['products'];
    }
    if ($total === null && !empty($products)) { $total = count($products); }

    return ['products' => $products, 'total' => $total];
}

/* =========================================================================
 |  Part 1B — Ürün response ayrıştırma / normalize / (gelecekte) senkron
 * ====================================================================== */

/** Bir dizinin "satır listesi" (numeric index + eleman array) olup olmadığı. */
function tsoft_is_row_list($a): bool
{
    if (!is_array($a) || $a === []) { return false; }
    return isset($a[0]) && is_array($a[0]);
}

/**
 * Response içinden ürün listesini bulur. Denenen yapılar:
 * data | data.products | products | result | result.products | Product | Products
 * $response: tsoft_request structured sonucu VEYA çözülmüş gövde (array).
 * Bulunamazsa: [] döner ve yanıt yapısını (yalnız anahtarlar) loglar.
 */
function tsoft_parse_products_response($response): array
{
    $body = $response;
    if (is_array($response) && array_key_exists('data', $response) && array_key_exists('raw', $response)) {
        $body = $response['data'];
    }
    if (!is_array($body)) {
        log_error('tsoft_parse_products_response: gövde array değil.');
        return [];
    }

    // Aday yollar (sırayla)
    $candidates = [];
    if (isset($body['data']))    { $candidates[] = $body['data']; }
    if (isset($body['data']) && is_array($body['data']) && isset($body['data']['products'])) { $candidates[] = $body['data']['products']; }
    if (isset($body['products'])) { $candidates[] = $body['products']; }
    if (isset($body['result']))   { $candidates[] = $body['result']; }
    if (isset($body['result']) && is_array($body['result']) && isset($body['result']['products'])) { $candidates[] = $body['result']['products']; }
    if (isset($body['Product']))  { $candidates[] = $body['Product']; }
    if (isset($body['Products'])) { $candidates[] = $body['Products']; }

    foreach ($candidates as $cand) {
        if (tsoft_is_row_list($cand)) { return $cand; }
        // Tek ürün (assoc) geldiyse listeye sar
        if (is_array($cand) && $cand !== [] && !isset($cand[0])) {
            $looksProduct = false;
            foreach (['ProductId', 'ProductCode', 'ProductName', 'Barcode', 'id', 'name'] as $k) {
                if (array_key_exists($k, $cand)) { $looksProduct = true; break; }
            }
            if ($looksProduct) { return [$cand]; }
        }
    }

    // Bulunamadı → yapıyı (yalnız anahtarları) logla
    $keys = is_array($body) ? implode(',', array_slice(array_keys($body), 0, 30)) : '(yok)';
    log_error('tsoft_parse_products_response: ürün listesi bulunamadı. Üst anahtarlar: ' . $keys);
    return [];
}

/** İlk ürünün tüm anahtarlarını döndürür (dinamik alan keşfi). */
function tsoft_product_field_keys(array $products): array
{
    if ($products === [] || !is_array($products[0] ?? null)) { return []; }
    return array_keys($products[0]);
}

/** Değeri gösterim için düz metne indirger (dizi/nesne güvenli). */
function tsoft_scalar($v): string
{
    if ($v === null) { return ''; }
    if (is_bool($v)) { return $v ? 'true' : 'false'; }
    if (is_scalar($v)) { return (string) $v; }
    if (is_array($v)) {
        // ilk skaler değeri ya da kısa JSON
        foreach ($v as $x) { if (is_scalar($x)) { return (string) $x; } }
        $j = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $j === false ? '[dizi]' : mb_substr($j, 0, 120);
    }
    return '';
}

/** Bir üründen ilk mevcut anahtarın değerini alır (alan adı varyasyonları için). */
function tsoft_pick(array $item, array $keys)
{
    foreach ($keys as $k) {
        if (array_key_exists($k, $item) && $item[$k] !== '') { return $item[$k]; }
    }
    return null;
}

/**
 * Ürünü panelde kullanmak için normalize eder. Eksik alanlar null.
 * Yapı sabit; ham veri 'raw' altında saklanır.
 */
function tsoft_normalize_product(array $item): array
{
    return [
        'external_id'  => tsoft_pick($item, ['ProductId', 'productId', 'id']),
        'code'         => tsoft_pick($item, ['ProductCode', 'productCode', 'StockCode', 'stockCode']),
        'name'         => tsoft_pick($item, ['ProductName', 'productName', 'name', 'Label']),
        'barcode'      => tsoft_pick($item, ['Barcode', 'barcode']),
        'brand'        => tsoft_pick($item, ['Brand', 'brand', 'BrandName']),
        'brand_id'     => tsoft_pick($item, ['BrandId', 'brandId']),
        'category'     => tsoft_pick($item, ['Category', 'category', 'CategoryName']),
        'category_id'  => tsoft_pick($item, ['CategoryId', 'categoryId']),
        'model'        => tsoft_pick($item, ['Model', 'model']),
        'price'        => tsoft_pick($item, ['Price', 'price', 'SellingPrice']),
        'buying_price' => tsoft_pick($item, ['BuyingPrice', 'buyingPrice', 'CostPrice']),
        'currency'     => tsoft_pick($item, ['Currency', 'currency', 'MoneyOrderCurrency']),
        'stock_amount' => tsoft_pick($item, ['StockAmount', 'stockAmount', 'Stock', 'stock']),
        'image_url'    => tsoft_pick($item, ['ImageUrl', 'imageUrl', 'Image', 'image', 'Picture']),
        'raw'          => $item,
    ];
}

/**
 * Ürünleri T-Soft'tan çeker ve normalize edilmiş liste döner (DB'ye YAZMAZ).
 * Gelecek partlarda kalıcı senkronun giriş noktasıdır.
 * Döner: ['ok'=>bool, 'count'=>int, 'products'=>array<normalized>, 'error'=>?string, 'result'=>array]
 */
function sync_tsoft_products(int $limit = 10, int $start = 0): array
{
    $limit = max(1, min(100, $limit));
    $start = max(0, $start);

    $login = tsoft_login();
    if (empty($login['ok'])) {
        return ['ok' => false, 'count' => 0, 'products' => [], 'error' => (string) ($login['error'] ?? 'Token alınamadı.'), 'result' => $login['result'] ?? []];
    }
    $res = tsoft_get_products_raw(['token' => $login['token'], 'limit' => $limit, 'start' => $start]);
    if (empty($res['ok'])) {
        return ['ok' => false, 'count' => 0, 'products' => [], 'error' => tsoft_user_error($res), 'result' => $res];
    }
    $rows = tsoft_parse_products_response($res);
    if ($rows === []) {
        return ['ok' => false, 'count' => 0, 'products' => [], 'error' => 'Ürün listesi response içinde bulunamadı.', 'result' => $res];
    }
    $normalized = array_map('tsoft_normalize_product', $rows);
    return ['ok' => true, 'count' => count($normalized), 'products' => $normalized, 'error' => null, 'result' => $res];
}

/**
 * Normalize edilmiş ürünü kalıcı kaydeder — KALICI YAZIM PART 2'DE ETKİNLEŞTİRİLECEK.
 * Bu partta ürün tablosu tanımlı olmadığından yazma yapılmaz; alan doğrulaması yapıp
 * net bir sonuç döndürür (fatal/half-code yok).
 * Döner: ['ok'=>bool, 'action'=>'skipped', 'reason'=>string, 'external_id'=>mixed]
 */
function upsert_tsoft_product(array $normalized): array
{
    $extId = $normalized['external_id'] ?? null;
    if ($extId === null || $extId === '') {
        return ['ok' => false, 'action' => 'skipped', 'reason' => 'external_id (ProductId) eksik.', 'external_id' => null];
    }
    // Part 2: burada tsoft_products tablosuna INSERT ... ON DUPLICATE KEY UPDATE yapılacak.
    return ['ok' => false, 'action' => 'skipped', 'reason' => 'Kalıcı kayıt Part 2 kapsamında etkinleştirilecek (bu partta DB yazımı yok).', 'external_id' => $extId];
}
