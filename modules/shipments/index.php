<?php
declare(strict_types=1);
/** modules/shipments/index.php — Sevkiyat listesi + özet + filtre (sevkiyatçı yalnız kendi). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipments.view');
$seeAll = shipment_can_see_all();
$f = [
    'status' => (string) ($_GET['status'] ?? ''), 'type' => (string) ($_GET['type'] ?? ''),
    'courier_id' => $seeAll ? (int) ($_GET['courier_id'] ?? 0) : 0,
    'date_from' => (string) ($_GET['date_from'] ?? ''), 'date_to' => (string) ($_GET['date_to'] ?? ''),
    'search' => trim((string) ($_GET['q'] ?? '')),
];
$rows = get_shipments($f);
$sum = $seeAll ? shipment_summary() : null;
$couriers = $seeAll ? get_personnel_options(false) : [];
layout_top('Sevkiyat Takibi', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Sevkiyat Takibi</h1><div class="page-actions">
    <?php if (can('shipment_addresses.view')): ?><a class="btn btn-sm" href="<?= e(url('modules/shipments/addresses.php')) ?>"><?= icon('route') ?>Adresler</a><?php endif; ?>
    <?php if (can('shipments.route_plan')): ?><a class="btn btn-sm" href="<?= e(url('modules/shipments/route-plan.php')) ?>"><?= icon('route') ?>Rota Planı</a><?php endif; ?>
    <?php if (can('shipments.collection_view')): ?><a class="btn btn-sm" href="<?= e(url('modules/shipments/collection.php')) ?>"><?= icon('package-check') ?>Toplama</a><?php endif; ?>
    <?php if (can('shipments.reports')): ?><a class="btn btn-sm" href="<?= e(url('modules/shipments/reports.php')) ?>"><?= icon('file-text') ?>Raporlar</a><?php endif; ?>
    <?php if (can('shipments.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/shipments/create.php')) ?>"><?= icon('plus') ?>Yeni Sevkiyat</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>
<?php if (!$seeAll): ?><div class="alert alert-info">Yalnızca size atanan sevkiyatları görüyorsunuz.</div><?php endif; ?>
<?php if ($sum): ?>
<div class="rma-stats">
    <div class="rma-stat"><div class="rs-label">Toplam</div><div class="rs-value"><?= (int) $sum['total'] ?></div></div>
    <div class="rma-stat is-closed"><div class="rs-label">Tamamlanan</div><div class="rs-value"><?= (int) $sum['completed'] ?></div></div>
    <div class="rma-stat is-loss"><div class="rs-label">Başarısız</div><div class="rs-value"><?= (int) $sum['failed'] ?></div></div>
    <div class="rma-stat is-open"><div class="rs-label">Bekleyen</div><div class="rs-value"><?= (int) $sum['pending'] ?></div></div>
    <div class="rma-stat"><div class="rs-label">Toplama</div><div class="rs-value"><?= (int) $sum['collections'] ?></div></div>
</div>
<?php endif; ?>
<form method="get" action="<?= e(url('modules/shipments/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Sevkiyat no, müşteri"></div>
    <div class="form-group"><label for="status">Durum</label><select id="status" name="status"><option value="">Tümü</option>
        <?php foreach (shipment_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="type">Tip</label><select id="type" name="type"><option value="">Tümü</option>
        <?php foreach (shipment_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <?php if ($seeAll): ?>
    <div class="form-group"><label for="courier_id">Sevkiyatçı</label><select id="courier_id" name="courier_id"><option value="0">Tümü</option>
        <?php foreach ($couriers as $cid => $cn): ?><option value="<?= (int) $cid ?>"<?= $f['courier_id'] === (int) $cid ? ' selected' : '' ?>><?= e($cn) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>
<?php if (!$rows): ?><div class="card"><div class="card-body"><div class="empty">Sevkiyat bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>No</th><th>Müşteri</th><th>İl / İlçe</th><th>Tip</th><th>Sevkiyatçı</th><th>Öncelik</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('modules/shipments/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['shipment_no']) ?></strong></a></td>
            <td><?= e((string) ($r['customer_name'] ?? '')) ?: '—' ?></td>
            <td><?= e(trim((string) ($r['addr_city'] ?? '') . ' / ' . (string) ($r['addr_district'] ?? ''), ' /')) ?: '—' ?></td>
            <td><?= e(shipment_type_label((string) $r['shipment_type'])) ?></td>
            <td><?= e((string) ($r['courier_name'] ?? '')) ?: '—' ?></td>
            <td><?php $p = (string) $r['priority']; ?><span class="badge <?= $p === 'critical' ? 'badge-danger' : ($p === 'urgent' ? 'badge-leave' : 'badge-muted') ?>"><?= e(shipment_priorities()[$p] ?? $p) ?></span></td>
            <td><span class="badge <?= e(shipment_status_class((string) $r['status'])) ?>"><?= e(shipment_status_label((string) $r['status'])) ?></span></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/shipments/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
