<?php
declare(strict_types=1);
/** modules/international-customers/sample-csv.php — Örnek içe aktarım CSV'si (UTF-8 BOM). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers-io.php';
auth_boot();
require_permission('international_customers.import');
ic_csv_stream(ic_csv_headers(), ic_csv_sample_rows(), 'yurtdisi-musteri-ornek.csv');
exit;
