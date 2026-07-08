<?php
declare(strict_types=1);
/** modules/forms/export.php — Form kayıtlarını CSV dışa aktar. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.submissions.view');

$f = [
    'template_id' => (int) ($_GET['template_id'] ?? 0), 'category_id' => (int) ($_GET['category_id'] ?? 0),
    'status' => (string) ($_GET['status'] ?? ''), 'approval_status' => (string) ($_GET['approval_status'] ?? ''),
    'assigned_user_id' => (int) ($_GET['assigned_user_id'] ?? 0), 'form_type' => (string) ($_GET['form_type'] ?? ''),
    'date_from' => (string) ($_GET['date_from'] ?? ''), 'date_to' => (string) ($_GET['date_to'] ?? ''),
    'phone' => trim((string) ($_GET['phone'] ?? '')), 'customer' => trim((string) ($_GET['customer'] ?? '')),
];
$rows = form_center_submissions($f);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="form-kayitlari-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Talep No','Form','Gönderen','Firma','Müşteri','Telefon','E-posta','Durum','Onay','Atanan','Tarih'], ';');
foreach ($rows as $r) {
    fputcsv($out, [
        (string) $r['submission_no'], (string) $r['form_name'],
        (string) ($r['submitter_name'] ?? ($r['external_token_id'] ? 'Dış Form' : '')),
        (string) ($r['company_name'] ?? ''), (string) ($r['customer_name'] ?? ''),
        (string) ($r['phone'] ?? ''), (string) ($r['email'] ?? ''),
        form_status_label((string) $r['status']), form_approval_label((string) $r['approval_status']),
        (string) ($r['assignee_name'] ?? ''), (string) $r['created_at'],
    ], ';');
}
fclose($out); exit;
