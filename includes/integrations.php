<?php
declare(strict_types=1);

/**
 * includes/integrations.php — Entegrasyon ayarları + bağlantı testi + log altyapısı.
 *
 * İlke: API dokümanı olmayan entegrasyonlar için GERÇEK entegrasyon UYDURULMAZ.
 * Burada ayar saklama, bağlantı test altyapısı, log ve placeholder servis
 * çağrıları vardır. Secret alanlar (token/key/salt/şifre) AES-256-GCM ile
 * şifreli saklanır (includes/vault.php) ve ekranda maskeli gösterilir.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vault.php'; // vault_encrypt / vault_decrypt / vault_is_configured

/**
 * Entegrasyon tanımları.
 * key => [name, icon, api_url(bool), username(bool), secret_fields[], config_fields[label=>type], test_type]
 * test_type: 'tsoft' | 'url' | 'fields'
 */
function integration_definitions(): array
{
    return [
        'paytr' => [
            'name' => 'PayTR', 'icon' => 'credit-card', 'api_url' => false, 'username' => false,
            'secret_fields' => ['merchant_id' => 'Merchant ID', 'merchant_key' => 'Merchant Key', 'merchant_salt' => 'Merchant Salt'],
            'config_fields' => ['test_mode' => 'bool', 'notification_url' => 'text', 'success_url' => 'text', 'fail_url' => 'text'],
            'test_type' => 'fields',
        ],
        'pbx' => [
            'name' => 'Santral API', 'icon' => 'phone', 'api_url' => true, 'username' => true,
            'secret_fields' => ['token' => 'Token / Şifre'],
            'config_fields' => [],
            'test_type' => 'url',
        ],
        'fingerprint' => [
            'name' => 'Parmak İzi API', 'icon' => 'user-check', 'api_url' => true, 'username' => true,
            'secret_fields' => ['token' => 'Token / Şifre'],
            'config_fields' => [],
            'test_type' => 'url',
        ],
        'logo_erp' => [
            'name' => 'Logo ERP API', 'icon' => 'building-2', 'api_url' => true, 'username' => true,
            'secret_fields' => ['token' => 'Token / Şifre'],
            'config_fields' => [],
            'test_type' => 'url',
        ],
        'tsoft' => [
            'name' => 'T-Soft API', 'icon' => 'store', 'api_url' => false, 'username' => false,
            'secret_fields' => [],
            'config_fields' => [],
            'test_type' => 'tsoft',
            'readonly' => true, // T-Soft ayarları config.php'de tutulur
        ],
    ];
}

function integration_def(string $key): ?array
{
    return integration_definitions()[$key] ?? null;
}

/** Kayıtlı entegrasyon satırı (yoksa boş varsayılan). */
function get_integration(string $key): array
{
    $def = integration_def($key);
    $base = ['int_key' => $key, 'name' => $def['name'] ?? $key, 'is_active' => 0, 'api_url' => '', 'username' => '', 'config_json' => '', 'secret_enc' => '', 'last_test_at' => null, 'last_error' => null];
    try {
        $st = db()->prepare('SELECT * FROM integrations WHERE int_key = :k LIMIT 1');
        $st->execute([':k' => $key]);
        $row = $st->fetch();
        return $row ?: $base;
    } catch (Throwable $e) { log_error('get_integration: ' . $e->getMessage()); return $base; }
}

/** Tüm entegrasyonlar (tanımlı sırayla, kayıtla birleştirilmiş). */
function get_integrations(): array
{
    $out = [];
    foreach (integration_definitions() as $key => $def) {
        $out[$key] = get_integration($key) + ['def' => $def];
        $out[$key]['def'] = $def;
    }
    return $out;
}

/** Secret alanları çözer (assoc). Yapılandırma yoksa boş. */
function integration_secrets(string $key): array
{
    $row = get_integration($key);
    $enc = (string) ($row['secret_enc'] ?? '');
    if ($enc === '') { return []; }
    $json = vault_decrypt($enc);
    $arr = $json !== null ? json_decode($json, true) : null;
    return is_array($arr) ? $arr : [];
}

/** Config JSON çözer. */
function integration_config(string $key): array
{
    $row = get_integration($key);
    $arr = json_decode((string) ($row['config_json'] ?? ''), true);
    return is_array($arr) ? $arr : [];
}

/**
 * Entegrasyonu kaydeder. $secrets boş anahtarları (kullanıcı doldurmadıysa)
 * mevcut değeri korur; secret'lar şifrelenir.
 */
function save_integration(string $key, array $data): bool
{
    $def = integration_def($key);
    if (!$def || !empty($def['readonly'])) { return false; }

    // Secret birleştir (boş → eskiyi koru)
    $existing = integration_secrets($key);
    $secrets = $existing;
    foreach (array_keys($def['secret_fields']) as $f) {
        $val = trim((string) ($data['secret'][$f] ?? ''));
        if ($val !== '') { $secrets[$f] = $val; }
    }
    $secretEnc = null;
    if ($secrets) {
        if (!vault_is_configured()) { return false; } // şifreleme anahtarı yoksa secret yazma
        $secretEnc = vault_encrypt(json_encode($secrets, JSON_UNESCAPED_UNICODE));
    }

    // Config
    $config = [];
    foreach ($def['config_fields'] as $cf => $type) {
        $config[$cf] = $type === 'bool' ? (isset($data['config'][$cf]) ? 1 : 0) : trim((string) ($data['config'][$cf] ?? ''));
    }

    try {
        db()->prepare(
            'INSERT INTO integrations (int_key, name, is_active, api_url, username, secret_enc, config_json)
             VALUES (:k,:n,:a,:url,:u,:s,:c)
             ON DUPLICATE KEY UPDATE name=VALUES(name), is_active=VALUES(is_active), api_url=VALUES(api_url),
                 username=VALUES(username), secret_enc=COALESCE(VALUES(secret_enc), secret_enc), config_json=VALUES(config_json)'
        )->execute([
            ':k' => $key, ':n' => $def['name'], ':a' => !empty($data['is_active']) ? 1 : 0,
            ':url' => !empty($def['api_url']) ? trim((string) ($data['api_url'] ?? '')) : null,
            ':u' => !empty($def['username']) ? trim((string) ($data['username'] ?? '')) : null,
            ':s' => $secretEnc, ':c' => json_encode($config, JSON_UNESCAPED_UNICODE),
        ]);
        return true;
    } catch (Throwable $e) { log_error('save_integration: ' . $e->getMessage()); return false; }
}

/** Test sonucunu kaydeder (last_test_at, last_error). */
function integration_record_test(string $key, bool $ok, string $message): void
{
    try {
        db()->prepare('UPDATE integrations SET last_test_at = NOW(), last_error = :err WHERE int_key = :k')
            ->execute([':err' => $ok ? null : mb_substr($message, 0, 500), ':k' => $key]);
    } catch (Throwable $e) { log_error('integration_record_test: ' . $e->getMessage()); }
    integration_log($key, 'test', $ok ? 'success' : 'failed', $message);
}

/** Entegrasyon log kaydı (secret YAZILMAZ). */
function integration_log(string $key, string $action, string $status, string $message): void
{
    try {
        db()->prepare('INSERT INTO integration_logs (int_key, action, status, message, user_id) VALUES (:k,:a,:s,:m,:u)')
            ->execute([':k' => $key, ':a' => $action, ':s' => $status, ':m' => mb_substr($message, 0, 500), ':u' => current_user_id()]);
    } catch (Throwable $e) { log_error('integration_log: ' . $e->getMessage()); }
}

function integration_logs(string $key, int $limit = 20): array
{
    try {
        $st = db()->prepare('SELECT l.*, u.full_name FROM integration_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.int_key = :k ORDER BY l.id DESC LIMIT ' . max(1, min(100, $limit)));
        $st->execute([':k' => $key]);
        return $st->fetchAll();
    } catch (Throwable $e) { log_error('integration_logs: ' . $e->getMessage()); return []; }
}

/**
 * Bağlantı testi. Gerçek kimlik doğrulama yalnızca T-Soft'ta yapılır (mevcut).
 * Diğerleri: URL erişilebilirliği / gerekli alan kontrolü (placeholder).
 * @return array{ok:bool, message:string}
 */
function integration_test(string $key): array
{
    $def = integration_def($key);
    if (!$def) { return ['ok' => false, 'message' => 'Tanımsız entegrasyon.']; }

    $result = match ($def['test_type']) {
        'tsoft'  => integration_test_tsoft(),
        'url'    => integration_test_url($key),
        default  => integration_test_fields($key, $def),
    };
    integration_record_test($key, $result['ok'], $result['message']);
    return $result;
}

function integration_test_tsoft(): array
{
    if (!function_exists('tsoft_login')) { require_once __DIR__ . '/tsoft.php'; }
    $r = tsoft_login();
    return !empty($r['ok'])
        ? ['ok' => true, 'message' => 'T-Soft oturumu başarıyla açıldı.']
        : ['ok' => false, 'message' => (string) ($r['error'] ?? 'T-Soft oturumu açılamadı.')];
}

function integration_test_url(string $key): array
{
    $row = get_integration($key);
    $url = trim((string) ($row['api_url'] ?? ''));
    if ($url === '') { return ['ok' => false, 'message' => 'API URL tanımlı değil.']; }
    if (!function_exists('curl_init')) { return ['ok' => false, 'message' => 'Sunucuda cURL etkin değil.']; }
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '') { return ['ok' => false, 'message' => 'Bağlantı kurulamadı: ' . $err]; }
        return ['ok' => true, 'message' => 'Adrese ulaşıldı (HTTP ' . $code . '). Not: Bu yalnızca erişilebilirlik testidir; gerçek kimlik doğrulama API dokümanı geldiğinde eklenecektir.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Bağlantı hatası.'];
    }
}

function integration_test_fields(string $key, array $def): array
{
    $secrets = integration_secrets($key);
    $missing = [];
    foreach ($def['secret_fields'] as $f => $label) {
        if (trim((string) ($secrets[$f] ?? '')) === '') { $missing[] = $label; }
    }
    if ($missing) { return ['ok' => false, 'message' => 'Eksik alan(lar): ' . implode(', ', $missing)]; }
    return ['ok' => true, 'message' => 'Gerekli bilgiler tanımlı. Not: Gerçek ödeme/işlem testi, canlı akış entegre edildiğinde yapılacaktır.'];
}
