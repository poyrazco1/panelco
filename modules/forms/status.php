<?php
declare(strict_types=1);
/** modules/forms/status.php — Form kaydı durum güncelle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.submissions.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/forms/submissions.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
if (form_center_update_submission_status($id, $status, trim((string) ($_POST['note'] ?? '')) ?: null, current_user_id())) {
    flash('success', 'Durum güncellendi: ' . form_status_label($status));
} else { flash('error', 'Durum güncellenemedi.'); }
http_response_code(303);
redirect('modules/forms/submission-view.php?id=' . $id);
