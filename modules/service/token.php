<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';
auth_boot();
require_permission('service');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(303); redirect('modules/service/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$do = (string) ($_POST['do'] ?? '');
try {
    if ($do === 'regenerate') { service_regenerate_token($id); flash('success', 'Takip linki yenilendi.'); }
    elseif ($do === 'disable') { service_toggle_public($id, false); flash('success', 'Public takip kapatıldı.'); }
    elseif ($do === 'enable') { service_toggle_public($id, true); flash('success', 'Public takip açıldı.'); }
    else { flash('error', 'Geçersiz işlem.'); }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}
http_response_code(303);
redirect('modules/service/view.php?id=' . $id);
