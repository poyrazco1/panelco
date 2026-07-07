<?php
declare(strict_types=1);

/**
 * modules/shipments/route-plan.php — Konuma göre rota planlama.
 * Tarih/sevkiyatçı/il/durum/tip filtresi + manuel sıra no + Google Maps rota linki.
 * Google Maps API şart değildir; adres/konumdan yönlendirme linki üretilir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.route_plan');

$f = [
    'route_date' => (string) ($_GET['route_date'] ?? date('Y-m-d')),
    'courier_id' => (int) ($_GET['courier_id'] ?? 0),
    'city'       => trim((string) ($_GET['city'] ?? '')),
    'status'     => (string) ($_GET['status'] ?? ''),
    'type'       => (string) ($_GET['type'] ?? ''),
];

// Sıra kaydetme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $planDate = (string) ($_POST['route_date'] ?? '') ?: null;
    foreach ((array) ($_POST['seq'] ?? []) as $sid => $seq) {
        $sid = (int) $sid; $seq = (int) $seq;
        try {
            db()->prepare('UPDATE shipments SET route_date = :rd, route_seq = :seq, route_time = :rt, updated_by = :uby WHERE id = :id AND is_deleted = 0')
                ->execute([':rd' => $planDate, ':seq' => $seq ?: null, ':rt' => trim((string) ($_POST['time'][$sid] ?? '')) ?: null, ':uby' => current_user_id(), ':id' => $sid]);
        } catch (Throwable $e) { log_error('route-plan save: ' . $e->getMessage()); }
    }
    try { db()->prepare('INSERT INTO shipment_route_plans (plan_date, courier_id, notes, created_by) VALUES (:d,:c,:n,:by)')
        ->execute([':d' => $planDate, ':c' => (int) ($_POST['courier_id'] ?? 0) ?: null, ':n' => trim((string) ($_POST['notes'] ?? '')) ?: null, ':by' => current_user_id()]); } catch (Throwable $e) {}
    log_activity('shipment_route_plan', 'shipment', null, null, 'success', 'Rota planı kaydedildi (' . (string) $planDate . ')');
    flash('success', 'Rota sıralaması kaydedildi.');
    http_response_code(303);
    redirect('modules/shipments/route-plan.php?' . http_build_query(array_filter($f)));
}

// Sıralı liste (route_date filtresi get_shipments'ta route_seq sıralar)
$listFilter = ['route_date' => $f['route_date']];
if ($f['courier_id']) { $listFilter['courier_id'] = $f['courier_id']; }
if ($f['status']) { $listFilter['status'] = $f['status']; }
if ($f['type']) { $listFilter['type'] = $f['type']; }
if ($f['city']) { $listFilter['city'] = $f['city']; }
$rows = get_shipments($listFilter);

// Google Maps çok-duraklı rota linki (sıraya göre)
$stops = [];
foreach ($rows as $r) { $stops[] = ['address' => $r['addr_text'] ?? '', 'district' => $r['addr_district'] ?? '', 'city' => $r['addr_city'] ?? '', 'lat' => '', 'lng' => '']; }
$routeLink = shipment_route_maps_link($stops);
$couriers = get_personnel_options(false);

layout_top('Rota Planlama', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Rota Planlama</h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Sevkiyatlar</a>
    <?php if ($routeLink): ?><a class="btn btn-primary btn-sm" href="<?= e($routeLink) ?>" target="_blank" rel="noopener"><?= icon('route') ?>Google Maps Rotası</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>

<form method="get" action="<?= e(url('modules/shipments/route-plan.php')) ?>" class="toolbar">
    <div class="form-group"><label for="route_date">Tarih</label><input type="date" id="route_date" name="route_date" value="<?= e($f['route_date']) ?>"></div>
    <div class="form-group"><label for="courier_id">Sevkiyatçı</label><select id="courier_id" name="courier_id"><option value="0">Tümü</option>
        <?php foreach ($couriers as $cid => $cn): ?><option value="<?= (int) $cid ?>"<?= $f['courier_id'] === (int) $cid ? ' selected' : '' ?>><?= e($cn) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="city">İl</label><input type="text" id="city" name="city" value="<?= e($f['city']) ?>"></div>
    <div class="form-group"><label for="type">Tip</label><select id="type" name="type"><option value="">Tümü</option>
        <?php foreach (shipment_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Getir</button></div>
</form>

<?php if (!$rows): ?><div class="card"><div class="card-body"><div class="empty">Bu kriterlere uygun sevkiyat yok. Sevkiyatları bu tarihe göre planlamak için önce sevkiyat oluşturun.</div></div></div>
<?php else: ?>
<form method="post" action="<?= e(url('modules/shipments/route-plan.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="route_date" value="<?= e($f['route_date']) ?>">
    <input type="hidden" name="courier_id" value="<?= (int) $f['courier_id'] ?>">
    <div class="table-wrap"><table class="table">
        <thead><tr><th style="width:80px">Sıra</th><th style="width:120px">Saat</th><th>Sevkiyat</th><th>Müşteri / Adres</th><th>Tip</th><th>Durum</th><th class="nowrap">Harita</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $i => $r): $ml = shipment_maps_link($r); ?>
            <tr>
                <td><input type="text" name="seq[<?= (int) $r['id'] ?>]" value="<?= e((string) ($r['route_seq'] ?? ($i + 1))) ?>" inputmode="numeric" style="width:60px"></td>
                <td><input type="text" name="time[<?= (int) $r['id'] ?>]" value="<?= e((string) ($r['route_time'] ?? '')) ?>" placeholder="10:00-11:00" style="width:110px"></td>
                <td><a href="<?= e(url('modules/shipments/view.php?id=' . (int) $r['id'])) ?>"><?= e($r['shipment_no']) ?></a></td>
                <td><?= e((string) ($r['customer_name'] ?? '')) ?><div class="small muted"><?= e(trim((string) ($r['addr_district'] ?? '') . ' ' . (string) ($r['addr_city'] ?? ''))) ?></div></td>
                <td><?= e(shipment_type_label((string) $r['shipment_type'])) ?></td>
                <td><span class="badge <?= e(shipment_status_class((string) $r['status'])) ?>"><?= e(shipment_status_label((string) $r['status'])) ?></span></td>
                <td class="nowrap"><?php if ($ml): ?><a class="btn btn-xs" href="<?= e($ml) ?>" target="_blank" rel="noopener"><?= icon('route', 'icon-xs') ?></a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="form-group" style="max-width:520px;margin-top:12px"><label for="notes">Plan notu</label><input type="text" id="notes" name="notes"></div>
    <div class="form-actions"><button type="submit" class="btn btn-primary"><?= icon('check-circle') ?>Sıralamayı Kaydet</button></div>
</form>
<?php endif; ?>
<?php layout_bottom();
