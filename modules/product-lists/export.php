<?php
declare(strict_types=1);
/** modules/product-lists/export.php — Ürün listesi kalemleri CSV (UTF-8 BOM). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/product-lists.php';
auth_boot();
require_permission('product_lists.export');

$id = (int) ($_GET['id'] ?? 0);
$pl = pl_get($id);
if (!$pl) { flash('error', 'Liste bulunamadı.'); redirect('modules/product-lists/index.php'); }
$items = pl_items($id);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $pl['list_no']) . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Sira', 'Urun', 'SKU', 'Marka', 'Miktar', 'Birim', 'Fiyat', 'ParaBirimi', 'Not'], ';');
foreach ($items as $i => $it) {
    fputcsv($out, [
        $i + 1, (string) $it['name'], (string) ($it['sku'] ?? ''), (string) ($it['brand'] ?? ''),
        (string) ($it['qty'] ?? ''), (string) ($it['unit'] ?? ''), (string) ($it['price'] ?? ''),
        (string) ($it['currency'] ?: $pl['currency'] ?? ''), (string) ($it['note'] ?? ''),
    ], ';');
}
fclose($out);
exit;
