<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/rma.php';
auth_boot();
require_permission('rma');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(303); redirect('modules/rma/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
try {
    rma_change_status($id, (string) ($_POST['new_status'] ?? ''), trim((string) ($_POST['note'] ?? '')) ?: null);
    flash('success', 'Durum güncellendi.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}
http_response_code(303);
redirect('modules/rma/view.php?id=' . $id);
