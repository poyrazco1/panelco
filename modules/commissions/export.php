<?php
declare(strict_types=1);

/** modules/commissions/export.php — Prim listesini CSV (Excel/UTF-8 BOM) dışa aktarır. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/commissions.php';

auth_boot();
require_permission('commissions.export');

$seeAll = commission_can_see_all();
$f = [
    'personnel_id' => $seeAll ? (int) ($_GET['personnel_id'] ?? 0) : 0,
    'date_from'    => (string) ($_GET['date_from'] ?? ''),
    'date_to'      => (string) ($_GET['date_to'] ?? ''),
];
$rows = get_commissions($f);

$filename = 'primler-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // Excel UTF-8 BOM
fputcsv($out, ['Personel', 'Dönem Başlangıç', 'Dönem Bitiş', 'Satış', 'Kâr', 'Prim Oranı %', 'Sabit Prim', 'Hedef', 'Hedef %', 'Hesaplanan Prim', 'Para Birimi', 'Açıklama'], ';');
foreach ($rows as $r) {
    fputcsv($out, [
        (string) ($r['personnel_name'] ?? ''),
        (string) ($r['period_start'] ?? ''),
        (string) ($r['period_end'] ?? ''),
        fmt_money((float) $r['sales_amount']),
        fmt_money((float) $r['profit_amount']),
        (string) $r['commission_rate'],
        fmt_money((float) $r['fixed_commission']),
        fmt_money((float) $r['target_amount']),
        (string) $r['target_ratio'],
        fmt_money((float) $r['calculated_commission']),
        (string) $r['currency'],
        (string) ($r['description'] ?? ''),
    ], ';');
}
fclose($out);
exit;
