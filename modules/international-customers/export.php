<?php
declare(strict_types=1);
/**
 * modules/international-customers/export.php — Yurtdışı müşteri CSV dışa aktarım.
 * Aktif filtreleri VE (verilmişse) seçili satırları dikkate alır.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers-io.php';
auth_boot();
require_permission('international_customers.export');

$arr = static fn(string $k): array => isset($_GET[$k]) ? array_values(array_filter((array) $_GET[$k], static fn($v) => $v !== '')) : [];

$f = [
    'search'               => trim((string) ($_GET['q'] ?? '')),
    'country'              => $arr('country'),
    'city'                 => trim((string) ($_GET['city'] ?? '')),
    'company_role'         => $arr('company_role'),
    'status_id'            => $arr('status_id'),
    'company_type_id'      => $arr('company_type_id'),
    'category_id'          => $arr('category_id'),
    'brand'                => trim((string) ($_GET['brand'] ?? '')),
    'data_source'          => trim((string) ($_GET['data_source'] ?? '')),
    'event_name'           => trim((string) ($_GET['event_name'] ?? '')),
    'contact_permission'   => (string) ($_GET['contact_permission'] ?? ''),
    'has_email'            => !empty($_GET['has_email']) ? 1 : 0,
    'has_phone'            => !empty($_GET['has_phone']) ? 1 : 0,
    'include_blacklist'    => !empty($_GET['include_blacklist']) ? 1 : 0,
    'include_uninterested' => !empty($_GET['include_uninterested']) ? 1 : 0,
    'created_from'         => trim((string) ($_GET['created_from'] ?? '')),
    'created_to'           => trim((string) ($_GET['created_to'] ?? '')),
    'ids'                  => $arr('ids'),
];

$rows = ic_list($f, 5000);
$out = [];
foreach ($rows as $c) { $out[] = ic_customer_to_csv_row($c); }

log_activity('intl_customer_export', 'international_customer', null, null, 'success', 'Yurtdışı müşteri CSV dışa aktarıldı: ' . count($out) . ' kayıt');
ic_csv_stream(ic_csv_headers(), $out, 'yurtdisi-musteriler-' . date('Y-m-d') . '.csv');
exit;
