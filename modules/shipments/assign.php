<?php
declare(strict_types=1);
/** modules/shipments/assign.php — Sevkiyatçı atama (yalnız yetkili yönetici). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipments.assign');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/shipments/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$courierId = (int) ($_POST['courier_id'] ?? 0);
$s = get_shipment($id);
if (!$s) { flash('error', 'Sevkiyat bulunamadı.'); redirect('modules/shipments/index.php'); }
if (assign_shipment($id, $courierId, current_user_id())) {
    log_activity('shipment_assign', 'shipment', $id, (string) $s['shipment_no'], 'success', 'Sevkiyatçı atandı (#' . $courierId . ')');
    flash('success', 'Sevkiyatçı atandı.');
} else { flash('error', 'Atama başarısız.'); }
redirect('modules/shipments/view.php?id=' . $id);
