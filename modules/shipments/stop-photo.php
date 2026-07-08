<?php
declare(strict_types=1);

/**
 * modules/shipments/stop-photo.php — Durak için teslimat/toplama kanıtı yükle.
 * uploads/shipment-proof/ altına güvenli kaydeder; shipment_proofs'a yazar.
 * Yalnızca izin verilen görsel tipleri ve boyut sınırı (Genel Ayarlar).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.photo_upload');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/shipments/index.php'); }
csrf_check();

$stopId = (int) ($_POST['stop_id'] ?? 0);
$note   = trim((string) ($_POST['note'] ?? ''));
$return = (string) ($_POST['return'] ?? '');

$stop = get_route_stop($stopId);
if (!$stop) { flash('error', 'Durak bulunamadı veya erişim yetkiniz yok.'); redirect('modules/shipments/index.php'); }

$res = ship_handle_proof('proof');
if (!$res['ok']) {
    flash('error', (string) $res['error']);
} elseif (ship_add_proof((int) $stop['route_id'], $stopId, $res, $note, current_user_id())) {
    log_activity('shipment_proof_upload', 'shipment_stop', $stopId, null, 'success', 'Kanıt fotoğrafı yüklendi (rota #' . (int) $stop['route_id'] . ')');
    flash('success', 'Fotoğraf yüklendi.');
} else {
    flash('error', 'Fotoğraf kaydedilemedi.');
}

$target = $return === 'driver'
    ? 'modules/shipments/route-driver.php?id=' . (int) $stop['route_id']
    : 'modules/shipments/route-view.php?id=' . (int) $stop['route_id'];
http_response_code(303);
redirect($target . '#stop-' . $stopId);
