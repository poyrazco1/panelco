<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';
auth_boot();
require_permission('service');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(303); redirect('modules/service/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
try {
    service_set_approval(
        $id,
        (string) ($_POST['approval_status'] ?? 'pending'),
        (string) ($_POST['approval_method'] ?? '') ?: null,
        trim((string) ($_POST['approval_note'] ?? '')) ?: null
    );
    flash('success', 'Müşteri onayı kaydedildi.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}
http_response_code(303);
redirect('modules/service/view.php?id=' . $id);
