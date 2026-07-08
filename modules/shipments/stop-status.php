<?php
declare(strict_types=1);

/**
 * modules/shipments/stop-status.php — Durak durumu güncelle (+ sürücü notu).
 * Durak güncellenir, shipment_status_logs'a yazılır, rota genel durumu otomatik
 * hesaplanır. Sürücü yalnızca kendi rotasının durağını güncelleyebilir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.status');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/shipments/index.php'); }
csrf_check();

$stopId = (int) ($_POST['stop_id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$note   = trim((string) ($_POST['driver_note'] ?? ''));
$return = (string) ($_POST['return'] ?? '');

$stop = get_route_stop($stopId);
if (!$stop) { flash('error', 'Durak bulunamadı veya erişim yetkiniz yok.'); redirect('modules/shipments/index.php'); }

if (set_stop_status($stopId, $status, $note !== '' ? $note : null, current_user_id())) {
    log_activity('shipment_stop_status', 'shipment_stop', $stopId, null, 'success',
        'Durak durumu: ' . ship_stop_status_label($status) . ' (rota #' . (int) $stop['route_id'] . ')');
    flash('success', 'Durak durumu güncellendi: ' . ship_stop_status_label($status));
} else {
    flash('error', 'Durum güncellenemedi.');
}

$target = $return === 'driver'
    ? 'modules/shipments/route-driver.php?id=' . (int) $stop['route_id']
    : 'modules/shipments/route-view.php?id=' . (int) $stop['route_id'];
http_response_code(303);
redirect($target . '#stop-' . $stopId);
