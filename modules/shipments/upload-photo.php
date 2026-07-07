<?php
declare(strict_types=1);
/** modules/shipments/upload-photo.php — Teslimat/toplama fotoğrafı yükle (atanan sevkiyatçı/yönetici). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipments.photo_upload');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/shipments/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$s = get_shipment($id); // görünürlük kontrolü
if (!$s) { flash('error', 'Sevkiyat bulunamadı veya yetkiniz yok.'); redirect('modules/shipments/index.php'); }
$type = (string) ($_POST['photo_type'] ?? 'delivery');
if (!in_array($type, ['delivery', 'collection'], true)) { $type = 'delivery'; }
$up = shipment_handle_photo('photo');
if ($up['error']) { flash('error', $up['error']); redirect('modules/shipments/view.php?id=' . $id); }
if (shipment_add_photo($id, (string) $up['path'], $type, current_user_id())) {
    log_activity('shipment_photo', 'shipment', $id, (string) $s['shipment_no'], 'success', 'Fotoğraf yüklendi (' . $type . ')');
    flash('success', 'Fotoğraf yüklendi.');
} else { flash('error', 'Fotoğraf kaydedilemedi.'); }
redirect('modules/shipments/view.php?id=' . $id);
