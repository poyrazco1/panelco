<?php
declare(strict_types=1);

/** modules/leads/export.php — Lead listesi CSV (Excel/UTF-8 BOM). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';

auth_boot();
require_permission('leads.export');

$f = ['status' => (string) ($_GET['status'] ?? ''), 'search' => trim((string) ($_GET['q'] ?? ''))];
$rows = get_leads($f);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="leadler-' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Firma', 'Yetkili', 'Telefon', 'WhatsApp', 'E-posta', 'Web', 'Instagram', 'Sektör', 'İl', 'İlçe', 'Kaynak', 'Durum'], ';');
foreach ($rows as $r) {
    fputcsv($out, [
        (string) $r['company_name'], (string) ($r['contact_name'] ?? ''), (string) ($r['phone'] ?? ''),
        (string) ($r['whatsapp'] ?? ''), (string) ($r['email'] ?? ''), (string) ($r['website'] ?? ''),
        (string) ($r['instagram'] ?? ''), (string) ($r['sector'] ?? ''), (string) ($r['city'] ?? ''),
        (string) ($r['district'] ?? ''), (string) ($r['source'] ?? ''), lead_status_label((string) $r['status']),
    ], ';');
}
fclose($out);
exit;
