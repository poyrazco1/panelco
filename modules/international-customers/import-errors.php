<?php
declare(strict_types=1);
/** modules/international-customers/import-errors.php — Hatalı satırları CSV indir. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers-io.php';
auth_boot();
require_permission('international_customers.import');

$importId = (int) ($_GET['id'] ?? 0);
$rows = [];
try {
    $st = db()->prepare('SELECT row_no, reason, raw FROM international_customer_import_errors WHERE import_id = :id ORDER BY row_no ASC');
    $st->execute([':id' => $importId]);
    foreach ($st->fetchAll() as $r) {
        $raw = json_decode((string) ($r['raw'] ?? '[]'), true);
        $rawStr = is_array($raw) ? implode(' | ', array_map('strval', $raw)) : (string) $r['raw'];
        $rows[] = [(string) $r['row_no'], (string) $r['reason'], $rawStr];
    }
} catch (Throwable $e) { log_error('import-errors: ' . $e->getMessage()); }

ic_csv_stream(['satir_no', 'hata', 'ham_veri'], $rows, 'ice-aktarim-hatalari-' . $importId . '.csv');
exit;
