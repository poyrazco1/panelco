<?php
declare(strict_types=1);

/**
 * modules/shipments/route-map.php — Rota için Google Maps directions linkine yönlendirir.
 * API kullanılmaz; başlangıç + sıralı duraklar + bitiş ile link üretilir.
 * Link buradan üretildiği için liste/detay ekranları hafif kalır.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.view');

$id = (int) ($_GET['id'] ?? 0);
$route = get_route($id);
if (!$route) { flash('error', 'Rota bulunamadı.'); redirect('modules/shipments/index.php'); }

$stops = get_route_stops($id);
$map = ship_route_maps_link($route, $stops);
if (empty($map['url'])) {
    flash('error', 'Bu rota için harita linki üretilemedi (adres/konum bilgisi yok).');
    redirect('modules/shipments/route-view.php?id=' . $id);
}

header('Location: ' . $map['url']);
exit;
