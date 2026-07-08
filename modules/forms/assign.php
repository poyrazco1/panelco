<?php
declare(strict_types=1);
/** modules/forms/assign.php — Form kaydını kullanıcıya ata. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.submissions.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/forms/submissions.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$assignee = (int) ($_POST['assigned_user_id'] ?? 0);
if (form_center_assign_submission($id, $assignee, current_user_id())) {
    flash('success', $assignee ? 'Kayıt atandı.' : 'Atama kaldırıldı.');
} else { flash('error', 'Atama yapılamadı.'); }
http_response_code(303);
redirect('modules/forms/submission-view.php?id=' . $id);
