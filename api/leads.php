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

/* -------------------------------------------------------------------------
 |  TARAMA MODU (opsiyonel, geriye dönük uyumlu):
 |  scan_id gönderilirse tarama sayaçları + kalite filtreleri + zengin alanlar
 |  uygulanır. scan_id yoksa DAVRANIŞ AYNEN ESKİSİ GİBİDİR.
 * ---------------------------------------------------------------------- */
$scanId = (int) ($body['scan_id'] ?? 0);
$scan = null; $filters = [];
if ($scanId > 0) {
    require_once __DIR__ . '/../includes/lead-scan.php';
    $scan = lead_scan_find($scanId);
    if ($scan) {
        $filters = (array) ($scan['config']['filters'] ?? []);
        lead_scan_bump($scanId, 'found');
    } else { $scanId = 0; } // geçersiz scan → normal moda düş
}

$phone = trim((string) $data['phone']);
$rating = isset($body['google_rating']) && $body['google_rating'] !== '' ? (float) $body['google_rating'] : null;
$reviews = isset($body['review_count']) && $body['review_count'] !== '' ? (int) $body['review_count'] : null;

// Kalite filtreleri (yalnızca tarama modunda; sonuç sayaçlarını doğru tutmak için)
if ($scanId > 0) {
    $minRating = (float) ($scan['config']['min_rating'] ?? 0);
    $minReviews = (int) ($scan['config']['min_reviews'] ?? 0);
    $skip = static function (string $why) use ($scanId, $ip) {
        lead_scan_bump($scanId, $why === 'duplicate' ? 'duplicate' : 'skipped');
        leads_api_log($ip, 'ok');
        echo json_encode(['ok' => true, 'id' => 0, 'result' => $why], JSON_UNESCAPED_UNICODE);
        exit;
    };
    try {
        if (!empty($filters['dedupe_phone']) && $phone !== '') {
            $c = db()->prepare('SELECT COUNT(*) FROM leads WHERE is_deleted = 0 AND phone = :p'); $c->execute([':p' => $phone]);
            if ((int) $c->fetchColumn() > 0) { $skip('duplicate'); }
        }
        if (!empty($filters['dedupe_company'])) {
            $c = db()->prepare('SELECT COUNT(*) FROM leads WHERE is_deleted = 0 AND company_name = :n'); $c->execute([':n' => $company]);
            if ((int) $c->fetchColumn() > 0) { $skip('duplicate'); }
        }
        if (!empty($filters['skip_blacklist'])) {
            $c = db()->prepare("SELECT COUNT(*) FROM leads WHERE is_deleted = 0 AND status = 'blacklist' AND (company_name = :n OR (phone <> '' AND phone = :p))");
            $c->execute([':n' => $company, ':p' => $phone]);
            if ((int) $c->fetchColumn() > 0) { $skip('blacklist'); }
        }
    } catch (Throwable $e) { log_error('scan dedupe: ' . $e->getMessage()); }
    if (!empty($filters['has_phone']) && $phone === '') { $skip('no_phone'); }
    if ($minRating > 0 && ($rating === null || $rating < $minRating)) { $skip('low_rating'); }
    if ($minReviews > 0 && ($reviews === null || $reviews < $minReviews)) { $skip('low_reviews'); }
}

$id = create_lead($data, null);
if ($id <= 0) {
    if ($scanId > 0) { lead_scan_bump($scanId, 'error'); }
    $fail('Kayıt oluşturulamadı.', 500, $ip);
}

// Zengin alanları + tarama bağlantısını ekle (çekirdek create_lead'e dokunmadan)
if ($scanId > 0 || $rating !== null || $reviews !== null) {
    try {
        db()->prepare('UPDATE leads SET google_rating = :gr, review_count = :rc, address = :addr, has_website = :hw,
                       package = :pkg, priority = :prio, scan_id = :sid WHERE id = :id')
            ->execute([
                ':gr' => $rating, ':rc' => $reviews,
                ':addr' => trim((string) ($body['address'] ?? '')) ?: null,
                ':hw' => ((string) $data['website'] !== '' ? 1 : 0),
                ':pkg' => $scan ? ($scan['package'] ?: null) : (trim((string) ($body['package'] ?? '')) ?: null),
                ':prio' => $scan ? (string) ($scan['config']['priority'] ?? 'normal') : (string) ($body['priority'] ?? 'normal'),
                ':sid' => $scanId ?: null, ':id' => $id,
            ]);
    } catch (Throwable $e) { log_error('lead enrich: ' . $e->getMessage()); }
    if ($scanId > 0) {
        lead_scan_bump($scanId, 'saved');
        // hedefe ulaşıldıysa taramayı tamamla
        $prog = lead_scan_progress($scanId);
        if ((int) $prog['target'] > 0 && (int) $prog['saved'] >= (int) $prog['target'] && $prog['status'] === 'running') {
            lead_scan_set_status($scanId, 'done', null);
        }
    }
}

leads_api_log($ip, 'ok');
echo json_encode(['ok' => true, 'id' => $id, 'result' => 'saved'], JSON_UNESCAPED_UNICODE);
