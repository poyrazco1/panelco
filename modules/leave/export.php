<?php
declare(strict_types=1);

/**
 * modules/leave/export.php
 * type=annual   → Yıllık izin takibi (personel bazlı bakiye) CSV
 * type=requests → İzin talepleri CSV
 * Excel uyumlu (UTF-8 BOM). Salt-okunur; filtreler GET ile taşınır.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

$type = (string) ($_GET['type'] ?? 'annual');
$num = static fn($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

if ($type === 'requests') {
    $f = [
        'personnel_id' => (int) ($_GET['personnel_id'] ?? 0),
        'department'   => (string) ($_GET['department'] ?? ''),
        'leave_type_id'=> (int) ($_GET['leave_type_id'] ?? 0),
        'status'       => (string) ($_GET['status'] ?? ''),
        'approver_id'  => (int) ($_GET['approver_id'] ?? 0),
        'date_from'    => (string) ($_GET['date_from'] ?? ''),
        'date_to'      => (string) ($_GET['date_to'] ?? ''),
        'this_month'   => !empty($_GET['this_month']),
        'this_year'    => !empty($_GET['this_year']),
        'search'       => trim((string) ($_GET['q'] ?? '')),
    ];
    $rows = get_leave_requests($f);
    $filename = 'izin-talepleri-' . date('Ymd-His') . '.csv';
    $header = ['Talep No', 'Personel', 'Departman', 'İzin Türü', 'Başlangıç', 'Bitiş', 'Gün', 'Durum', 'Açıklama', 'Talep Eden', 'Onaylayan', 'Oluşturulma'];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $r) {
        fputcsv($out, [
            (string) $r['request_no'],
            (string) ($r['personnel_name'] ?? ''),
            (string) ($r['department'] ?? ''),
            (string) ($r['type_name'] ?? ''),
            (string) $r['start_date'],
            (string) $r['end_date'],
            $num($r['calculated_days']),
            leave_status_label((string) $r['status']),
            (string) ($r['description'] ?? ''),
            (string) ($r['requester_name'] ?? ''),
            (string) ($r['approver_name'] ?? ''),
            (string) $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// Varsayılan: yıllık izin takibi
$f = [
    'department' => (string) ($_GET['department'] ?? ''),
    'search'     => trim((string) ($_GET['q'] ?? '')),
    'low_balance'=> !empty($_GET['low_balance']),
    'this_month' => !empty($_GET['this_month']),
    'this_year'  => !empty($_GET['this_year']),
];
$rows = get_all_personnel_leave_summaries($f);
$filename = 'yillik-izin-takibi-' . date('Ymd-His') . '.csv';
$header = ['Personel', 'Personel Kodu', 'Departman', 'İşe Giriş', 'Hak Edilen', 'Devreden', 'Manuel', 'Kullanılan', 'Onay Bekleyen', 'Kalan', 'Son İzin'];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $header);
foreach ($rows as $row) {
    $p = $row['personnel']; $s = $row['summary'];
    fputcsv($out, [
        (string) $p['full_name'],
        (string) ($p['personnel_code'] ?? ''),
        (string) ($p['department'] ?? ''),
        (string) ($p['hire_date'] ?? ''),
        $num($s['entitled']),
        $num($s['carry_over']),
        $num($s['manual']),
        $num($s['used']),
        $num($s['pending']),
        $num($s['remaining']),
        (string) ($s['last_leave_date'] ?? ''),
    ]);
}
fclose($out);
exit;
