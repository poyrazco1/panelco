<?php
declare(strict_types=1);
/** modules/forms/reject.php — Form kaydını reddet. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.approve');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/forms/approvals.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));
if (form_center_reject_submission($id, $reason, current_user_id())) {
    log_activity('form_reject', 'form_submission', $id, null, 'success', 'Form reddedildi');
    flash('success', 'Kayıt reddedildi.');
} else { flash('error', 'Reddedilemedi.'); }
http_response_code(303);
redirect('modules/forms/submission-view.php?id=' . $id);
