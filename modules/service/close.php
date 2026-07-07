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
    service_change_status($id, 'closed', 'Süreç kapatıldı');
    flash('success', 'Servis kaydı kapatıldı.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}
http_response_code(303);
redirect('modules/service/view.php?id=' . $id);
