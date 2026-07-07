<?php
declare(strict_types=1);

/**
 * api/tsoft-search.php — Teklif/Sipariş/Ürün modülleri için T-Soft ürün arama (AJAX).
 * POST + login + CSRF. Yetki: quotes/orders/tsoft_products modüllerinden biri.
 * JSON döner. T-Soft erişilemezse ok=false + kullanıcı-dostu hata (uydurma veri yok).
 */
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/tsoft.php';

auth_boot();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$fail = static function (string $m, int $c = 400): void {
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $fail('Yalnızca POST.', 405); }
if (!is_logged_in()) { $fail('Oturum bulunamadı.', 403); }
if (!csrf_verify($_POST['_csrf'] ?? '')) { $fail('Oturum doğrulaması başarısız.', 419); }

// Ürün aramayı kullanan modüllerden en az birine yetki gerekir.
if (!can('quotes.view') && !can('orders.view') && !can('tsoft_products.view')) {
    $fail('Bu işlem için yetkiniz bulunmuyor.', 403);
}

$query = trim((string) ($_POST['q'] ?? ''));
$by    = (string) ($_POST['by'] ?? 'all');
if (!in_array($by, ['all', 'code', 'barcode', 'name'], true)) { $by = 'all'; }
if (mb_strlen($query) < 2) { $fail('En az 2 karakter girin.', 422); }

$res = tsoft_search_products($query, $by, 20);
if (!$res['ok']) {
    // Bağlantı/erişim hatası — teknik olmayan net mesaj.
    echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'T-Soft ürün araması yapılamadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// İstemciye sade alanlar döndür (raw gönderilmez)
$out = [];
foreach ($res['products'] as $p) {
    $out[] = [
        'code'    => (string) ($p['code'] ?? ''),
        'barcode' => (string) ($p['barcode'] ?? ''),
        'name'    => (string) ($p['name'] ?? ''),
        'brand'   => (string) ($p['brand'] ?? ''),
        'price'   => (string) ($p['price'] ?? ''),
        'currency'=> (string) ($p['currency'] ?? ''),
        'stock'   => (string) ($p['stock_amount'] ?? ''),
    ];
}
echo json_encode(['ok' => true, 'products' => $out, 'count' => count($out)], JSON_UNESCAPED_UNICODE);
