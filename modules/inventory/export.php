<?php
declare(strict_types=1);

/** modules/inventory/export.php — Envanter CSV (Excel/UTF-8 BOM). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/inventory.php';

auth_boot();
require_permission('inventory.export');

$f = [
    'category' => (string) ($_GET['category'] ?? ''),
    'status'   => (string) ($_GET['status'] ?? ''),
    'search'   => trim((string) ($_GET['q'] ?? '')),
];
$rows = get_inventory_items($f);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="envanter-' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Ad', 'Kategori', 'Marka', 'Model', 'Seri No', 'Satın Alma', 'Fatura No', 'Garanti Bitiş', 'Zimmet', 'Lokasyon', 'Durum', 'Açıklama'], ';');
foreach ($rows as $r) {
    fputcsv($out, [
        (string) $r['name'],
        inventory_category_label((string) $r['category']),
        (string) ($r['brand'] ?? ''),
        (string) ($r['model'] ?? ''),
        (string) ($r['serial_no'] ?? ''),
        (string) ($r['purchase_date'] ?? ''),
        (string) ($r['invoice_no'] ?? ''),
        (string) ($r['warranty_end'] ?? ''),
        (string) ($r['assigned_name'] ?? ''),
        (string) ($r['location'] ?? ''),
        inventory_status_label((string) $r['status']),
        (string) ($r['description'] ?? ''),
    ], ';');
}
fclose($out);
exit;
