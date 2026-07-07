<?php
declare(strict_types=1);
/** modules/shipments/complete.php — Sevkiyatı tamamla (teslim/toplama). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipments.complete');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/shipments/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$s = get_shipment($id);
if (!$s) { flash('error', 'Sevkiyat bulunamadı veya yetkiniz yok.'); redirect('modules/shipments/index.php'); }
$note = trim((string) ($_POST['note'] ?? ''));
$finalStatus = (string) $s['shipment_type'] === 'collection' ? 'collected' : 'delivered';
if (set_shipment_status($id, $finalStatus, $note, current_user_id())) {
    log_activity('shipment_complete', 'shipment', $id, (string) $s['shipment_no'], 'success', 'Sevkiyat tamamlandı: ' . shipment_status_label($finalStatus));
    flash('success', 'Sevkiyat tamamlandı olarak işaretlendi.');
} else { flash('error', 'İşlem başarısız.'); }
redirect('modules/shipments/view.php?id=' . $id);
