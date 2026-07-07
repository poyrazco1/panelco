<?php
declare(strict_types=1);

/**
 * api/leads.php — Chrome eklentisi için güvenli lead kaydetme endpoint'i.
 *
 * GÜVENLİK:
 *  - Token zorunlu (Authorization: Bearer <token> veya gövde 'token').
 *    Token app_settings.leads_api_token içinde saklanır; sabit-zamanlı doğrulama.
 *  - Rate limit: IP başına dakikada en fazla 30 istek.
 *  - Gelen veri doğrulanır (firma zorunlu, alanlar temizlenir/sınırlanır).
 *  - Yetkisiz/limit aşan istek reddedilir ve loglanır.
 *  - Bu endpoint OTURUM GEREKTİRMEZ (harici eklenti); yalnızca token ile çalışır.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/leads.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$ip = function_exists('client_ip') ? client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');

$fail = static function (string $m, int $c, string $ipArg): void {
    leads_api_log($ipArg, 'reject');
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $fail('Yalnızca POST.', 405, $ip); }

// Token: Authorization header veya gövde
$token = '';
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (stripos($auth, 'Bearer ') === 0) { $token = trim(substr($auth, 7)); }
if ($token === '') { $token = (string) ($_POST['token'] ?? ''); }

// JSON gövde de destekle
$body = $_POST;
if (empty($body) && stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($raw)) { $body = $raw; if ($token === '') { $token = (string) ($raw['token'] ?? ''); } }
}

if (leads_api_token() === '') { $fail('API henüz etkin değil.', 503, $ip); }
if (!leads_verify_api_token($token)) { $fail('Yetkisiz.', 401, $ip); }
if (!leads_api_rate_ok($ip, 30)) { $fail('Çok fazla istek. Lütfen sonra tekrar deneyin.', 429, $ip); }

// Doğrulama
$company = trim((string) ($body['company_name'] ?? $body['firma'] ?? ''));
if ($company === '' || mb_strlen($company) > 190) { $fail('Firma adı zorunludur.', 422, $ip); }

$data = lead_fields_from_input([
    'company_name' => $company,
    'contact_name' => (string) ($body['contact_name'] ?? $body['yetkili'] ?? ''),
    'phone'        => (string) ($body['phone'] ?? ''),
    'whatsapp'     => (string) ($body['whatsapp'] ?? ''),
    'email'        => (string) ($body['email'] ?? ''),
    'website'      => (string) ($body['website'] ?? ''),
    'instagram'    => (string) ($body['instagram'] ?? ''),
    'maps_url'     => (string) ($body['maps_url'] ?? $body['maps'] ?? ''),
    'sector'       => (string) ($body['sector'] ?? ''),
    'city'         => (string) ($body['city'] ?? ''),
    'district'     => (string) ($body['district'] ?? ''),
    'source'       => (string) ($body['source'] ?? 'chrome'),
    'notes'        => (string) ($body['notes'] ?? ''),
    'status'       => 'new',
]);
$errors = lead_validate($data);
if ($errors) { $fail(implode(' ', $errors), 422, $ip); }

$id = create_lead($data, null);
if ($id <= 0) { $fail('Kayıt oluşturulamadı.', 500, $ip); }

leads_api_log($ip, 'ok');
echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
