<?php
declare(strict_types=1);

/** modules/maintenance/export.php — Bakım Takipleri CSV (Excel/UTF-8 BOM) (§14). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service_maintenance.php';

auth_boot();
if (!can_maint_view()) { require_permission('maintenance.view'); }

$uid   = current_user_id() ?? 0;
$scopeUser = can_maint_view_all() ? null : $uid;

$f = [
    'search'   => trim((string) ($_GET['search'] ?? '')),
    'customer' => trim((string) ($_GET['customer'] ?? '')),
    'phone'    => trim((string) ($_GET['phone'] ?? '')),
    'email'    => trim((string) ($_GET['email'] ?? '')),
    'brand'    => trim((string) ($_GET['brand'] ?? '')),
    'model'    => trim((string) ($_GET['model'] ?? '')),
    'serial'   => trim((string) ($_GET['serial'] ?? '')),
    'status'   => (string) ($_GET['status'] ?? ''),
    'assigned' => (int) ($_GET['assigned'] ?? 0),
    'channel'  => (string) ($_GET['channel'] ?? ''),
    'due_from' => (string) ($_GET['due_from'] ?? ''),
    'due_to'   => (string) ($_GET['due_to'] ?? ''),
    'flag'     => (string) ($_GET['flag'] ?? ''),
];
$rows = smaint_reminder_list($f, ['scope_user' => $scopeUser, 'limit' => 5000]);

log_activity('maintenance_export', 'maintenance', null, null, 'success', count($rows) . ' kayıt CSV');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="bakim-takipleri-' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Servis No', 'Müşteri/Firma', 'Yetkili', 'Telefon', 'WhatsApp', 'E-posta', 'Marka', 'Model',
               'Seri No', 'Teslim Tarihi', 'Bakım Tarihi', 'Kalan/Geçen Gün', 'Durum', 'Kanal', 'Personel',
               'Son İletişim', 'Sonraki İletişim'], ';');
foreach ($rows as $r) {
    $days = smaint_days_to_due((string) $r['maintenance_due_date']);
    fputcsv($out, [
        (string) ($r['reference_code'] ?? ''),
        (string) ($r['company_name'] ?: $r['customer_name'] ?? ''),
        (string) ($r['contact_name'] ?? ''),
        (string) ($r['phone'] ?? ''), (string) ($r['whatsapp'] ?? ''), (string) ($r['email'] ?? ''),
        (string) ($r['brand_name'] ?? ''), (string) ($r['device_model'] ?? ''), (string) ($r['serial_no'] ?? ''),
        substr((string) ($r['delivery_date'] ?? ''), 0, 10), substr((string) $r['maintenance_due_date'], 0, 10),
        $days === null ? '' : (string) $days,
        smaint_status_label((string) $r['status']),
        smaint_channels()[(string) ($r['preferred_channel'] ?? '')] ?? '',
        (string) ($r['assignee_name'] ?? ''),
        (string) ($r['last_contact_at'] ?? ''), (string) ($r['next_contact_at'] ?? ''),
    ], ';');
}
fclose($out);
exit;
