<?php
declare(strict_types=1);
/** modules/forms/template-delete.php — Şablonu pasife al (soft delete). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.templates.manage');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/forms/templates.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
if (form_center_delete_template($id, current_user_id())) { flash('success', 'Şablon silindi (soft).'); }
else { flash('error', 'Silinemedi.'); }
http_response_code(303);
redirect('modules/forms/templates.php');
