<?php
declare(strict_types=1);
/** modules/forms/approve.php — Form kaydını onayla. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.approve');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/forms/approvals.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
if (form_center_approve_submission($id, trim((string) ($_POST['note'] ?? '')) ?: null, current_user_id())) {
    log_activity('form_approve', 'form_submission', $id, null, 'success', 'Form onaylandı');
    flash('success', 'Kayıt onaylandı.');
} else { flash('error', 'Onaylanamadı.'); }
http_response_code(303);
redirect('modules/forms/submission-view.php?id=' . $id);
