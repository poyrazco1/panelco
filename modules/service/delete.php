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
    delete_service_record($id, current_user_id());
    flash('success', 'Kayıt başarıyla silindi.');
} catch (Throwable $e) {
    flash('error', 'Kayıt silinirken bir sorun oluştu. Detaylar log dosyasına yazıldı.');
}
http_response_code(303);
redirect('modules/service/index.php');
