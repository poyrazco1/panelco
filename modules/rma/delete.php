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
    delete_rma_record($id, current_user_id());
    flash('success', 'Kayıt başarıyla silindi.');
} catch (Throwable $e) {
    flash('error', 'Kayıt silinirken bir sorun oluştu. Detaylar log dosyasına yazıldı.');
}
http_response_code(303);
redirect('modules/rma/index.php');
