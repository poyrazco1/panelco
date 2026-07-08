<?php
declare(strict_types=1);

/**
 * modules/shipments/route-assign.php — Rotaya sürücü ata / rota durumunu değiştir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.assign');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/shipments/index.php'); }
csrf_check();

$routeId = (int) ($_POST['route_id'] ?? 0);
$route = get_route($routeId);
if (!$route) { flash('error', 'Rota bulunamadı.'); redirect('modules/shipments/index.php'); }

$action = (string) ($_POST['action'] ?? 'assign');
$uid = current_user_id();

if ($action === 'status') {
    $status = (string) ($_POST['status'] ?? '');
    if (set_route_status($routeId, $status, trim((string) ($_POST['note'] ?? '')) ?: null, $uid)) {
        flash('success', 'Rota durumu güncellendi: ' . ship_route_status_label($status));
    } else { flash('error', 'Rota durumu güncellenemedi.'); }
} else {
    $driver = (int) ($_POST['driver_user_id'] ?? 0);
    if (assign_route_driver($routeId, $driver, $uid)) {
        flash('success', $driver > 0 ? 'Sürücü atandı.' : 'Sürücü kaldırıldı.');
    } else { flash('error', 'Atama yapılamadı.'); }
}

http_response_code(303);
redirect('modules/shipments/route-view.php?id=' . $routeId);
