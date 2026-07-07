<?php
declare(strict_types=1);

/**
 * modules/rma/export.php
 * İade-değişim kayıtlarını CSV olarak dışa aktarır (Excel uyumlu, UTF-8 BOM).
 * Mevcut filtreler GET ile taşınır. Salt-okunur (state değiştirmez).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/rma.php';

auth_boot();
require_permission('rma');

$f = [
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to'   => (string) ($_GET['date_to'] ?? ''),
    'customer'  => trim((string) ($_GET['customer'] ?? '')),
    'phone'     => trim((string) ($_GET['phone'] ?? '')),
    'platform'  => (string) ($_GET['platform'] ?? ''),
    'type'      => (string) ($_GET['type'] ?? ''),
    'supplier'  => trim((string) ($_GET['supplier'] ?? '')),
    'status'    => (string) ($_GET['status'] ?? ''),
    'tracking'  => trim((string) ($_GET['tracking'] ?? '')),
    'model'     => trim((string) ($_GET['model'] ?? '')),
    'search'    => trim((string) ($_GET['q'] ?? '')),
];

$rows = get_rma_records($f);

$filename = 'iade-degisim-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// Excel'in UTF-8 tanıması için BOM
fwrite($out, "\xEF\xBB\xBF");

// Başlıklar (mevcut tablo başlıklarıyla aynı)
fputcsv($out, rma_csv_headers(), ';');

foreach ($rows as $r) {
    fputcsv($out, rma_record_to_row($r), ';');
}

fclose($out);
exit;
