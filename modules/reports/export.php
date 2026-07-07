<?php
declare(strict_types=1);

/** modules/reports/export.php — Seçilen raporu CSV (Excel/UTF-8 BOM) dışa aktarır. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reports.php';
require_once __DIR__ . '/../../includes/quotes.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/service.php';
require_once __DIR__ . '/../../includes/rma.php';
require_once __DIR__ . '/../../includes/inventory.php';

auth_boot();
require_permission('reports.export');

$key = (string) ($_GET['report'] ?? '');
$defs = report_definitions();
if (!isset($defs[$key]) || !can($defs[$key]['perm'])) {
    http_response_code(403);
    exit('Bu işlem için yetkiniz bulunmuyor.');
}
$f = [
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to'   => (string) ($_GET['date_to'] ?? ''),
    'status'    => (string) ($_GET['status'] ?? ''),
];
$result = report_run($key, $f);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="rapor-' . $key . '-' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, array_keys($result['columns']), ';');
foreach ($result['rows'] as $row) {
    $line = [];
    foreach ($result['columns'] as $label => $field) {
        $line[] = report_format($field, $row[$field] ?? '', $result['def']);
    }
    fputcsv($out, $line, ';');
}
fclose($out);
exit;
