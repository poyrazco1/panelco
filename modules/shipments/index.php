<?php
declare(strict_types=1);

/**
 * modules/shipments/index.php — Sevkiyat Takibi = ROTA MERKEZİ.
 * Üstte 4 özet kart, altında sekmeler (Bugünkü / Tüm / Toplama / Tamamlanan).
 * Rota oluşturma ve raporlar ayrı sayfalara linklidir. Sürücü yalnızca kendi
 * rotalarını görür. Ayar/tanım kalabalığı bu ekrana konmaz.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.view');

$manage = ship_can_manage();
$tab = (string) ($_GET['tab'] ?? 'today');
$allowedTabs = ['today', 'all', 'collection', 'completed'];
if (!in_array($tab, $allowedTabs, true)) { $tab = 'today'; }

$sum = ship_route_center_summary();
$drivers = $manage ? ship_driver_options() : [];
$fDriver = $manage ? (int) ($_GET['driver_user_id'] ?? 0) : 0;

// Sekmeye göre rota listesi
$filter = [];
if ($fDriver) { $filter['driver_user_id'] = $fDriver; }
if ($tab === 'today') { $filter['today'] = true; }
elseif ($tab === 'completed') { $filter['completed'] = true; }
$routes = ($tab === 'collection') ? [] : get_routes($filter);

// Toplama sekmesi: içinde toplama işlemi olan durakların bulunduğu rotalar
$collectionStops = ($tab === 'collection') ? ship_stop_report(['collection_only' => true]) : [];

/** Rota ilerleme kartı. */
function ship_render_route_card(array $r, bool $manage): void
{
    $total = (int) $r['stop_total']; $done = (int) $r['stop_done']; $problem = (int) $r['stop_problem'];
    $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
    ?>
    <div class="route-card">
        <div class="route-card-top">
            <div class="route-card-title">
                <a href="<?= e(url('modules/shipments/route-view.php?id=' . (int) $r['id'])) ?>"><strong><?= e((string) $r['route_name']) ?></strong></a>
                <span class="badge <?= e(ship_route_status_class((string) $r['status'])) ?>"><?= e(ship_route_status_label((string) $r['status'])) ?></span>
            </div>
            <div class="route-card-meta">
                <span><?= icon('calendar-days', 'icon-xs') ?> <?= e($r['route_date'] ? fmt_date((string) $r['route_date']) : '—') ?></span>
                <span><?= icon('user-check', 'icon-xs') ?> <?= e($r['driver_name'] !== '' ? $r['driver_name'] : 'Atanmadı') ?></span>
            </div>
        </div>
        <div class="route-progress">
            <div class="route-progress-bar"><span style="width:<?= $pct ?>%"></span></div>
            <div class="route-progress-text">
                <span><strong><?= $done ?>/<?= $total ?></strong> durak tamamlandı</span>
                <?php if ($problem > 0): ?><span class="route-problem"><?= icon('triangle-alert', 'icon-xs') ?> <?= $problem ?> sorunlu</span><?php endif; ?>
            </div>
        </div>
        <div class="route-card-actions">
            <?php if ($total > 0): ?><a class="btn btn-sm" href="<?= e(url('modules/shipments/route-map.php?id=' . (int) $r['id'])) ?>" target="_blank" rel="noopener"><?= icon('route') ?>Haritada Aç</a><?php endif; ?>
            <a class="btn btn-sm" href="<?= e(url('modules/shipments/route-view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye') ?>Detay</a>
            <?php if ($manage && can('shipments.edit')): ?><a class="btn btn-sm" href="<?= e(url('modules/shipments/route-create.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
            <a class="btn btn-sm btn-primary" href="<?= e(url('modules/shipments/route-driver.php?id=' . (int) $r['id'])) ?>"><?= icon('truck') ?>Sevkiyatçı Ekranı</a>
        </div>
    </div>
    <?php
}

layout_top('Sevkiyat Takibi', 'shipments');
?>
<div class="page-head">
    <h1 class="page-title">Sevkiyat Takibi</h1>
    <div class="page-actions">
        <?php if (can('shipment_addresses.view')): ?><a class="btn btn-sm" href="<?= e(url('modules/shipments/addresses.php')) ?>"><?= icon('warehouse') ?>Adresler</a><?php endif; ?>
        <?php if (can('shipments.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/shipments/route-create.php')) ?>"><?= icon('plus') ?>Rota Oluştur</a><?php endif; ?>
    </div>
</div>
<?= render_flashes() ?>
<?php if (!$manage): ?><div class="alert alert-info">Yalnızca size atanmış rotaları görüyorsunuz. Görevlerinizi <strong>Sevkiyatçı Ekranı</strong>ndan yürütebilirsiniz.</div><?php endif; ?>

<!-- Özet kartlar -->
<div class="ship-summary">
    <div class="ship-sum-card"><span class="ship-sum-ic icon-circle"><?= icon('truck', 'icon-sm') ?></span><span class="ship-sum-body"><span class="ship-sum-val"><?= (int) $sum['today_routes'] ?></span><span class="ship-sum-lbl">Bugünkü Rotalar</span></span></div>
    <div class="ship-sum-card"><span class="ship-sum-ic icon-circle"><?= icon('clock', 'icon-sm') ?></span><span class="ship-sum-body"><span class="ship-sum-val"><?= (int) $sum['pending_stops'] ?></span><span class="ship-sum-lbl">Bekleyen Duraklar</span></span></div>
    <div class="ship-sum-card"><span class="ship-sum-ic icon-circle"><?= icon('check-circle', 'icon-sm') ?></span><span class="ship-sum-body"><span class="ship-sum-val"><?= (int) $sum['done_stops'] ?></span><span class="ship-sum-lbl">Tamamlanan Duraklar</span></span></div>
    <div class="ship-sum-card ship-sum-warn"><span class="ship-sum-ic icon-circle"><?= icon('triangle-alert', 'icon-sm') ?></span><span class="ship-sum-body"><span class="ship-sum-val"><?= (int) $sum['problem_stops'] ?></span><span class="ship-sum-lbl">Sorunlu Duraklar</span></span></div>
</div>

<!-- Sekmeler -->
<?php $tabUrl = static fn(string $t) => e(url('modules/shipments/index.php?tab=' . $t . ($fDriver ? '&driver_user_id=' . $fDriver : ''))); ?>
<div class="tab-row">
    <a class="tab-chip<?= $tab === 'today' ? ' is-active' : '' ?>" href="<?= $tabUrl('today') ?>">Bugünkü Rotalar</a>
    <a class="tab-chip<?= $tab === 'all' ? ' is-active' : '' ?>" href="<?= $tabUrl('all') ?>">Tüm Rotalar</a>
    <?php if (can('shipments.create')): ?><a class="tab-chip" href="<?= e(url('modules/shipments/route-create.php')) ?>"><?= icon('plus', 'icon-xs') ?> Rota Oluştur</a><?php endif; ?>
    <a class="tab-chip<?= $tab === 'collection' ? ' is-active' : '' ?>" href="<?= $tabUrl('collection') ?>">Toplama İşleri</a>
    <a class="tab-chip<?= $tab === 'completed' ? ' is-active' : '' ?>" href="<?= $tabUrl('completed') ?>">Tamamlananlar</a>
    <?php if (can('shipments.reports')): ?><a class="tab-chip" href="<?= e(url('modules/shipments/reports.php')) ?>"><?= icon('file-text', 'icon-xs') ?> Raporlar</a><?php endif; ?>
</div>

<?php if ($manage && $drivers): ?>
<form method="get" action="<?= e(url('modules/shipments/index.php')) ?>" class="toolbar">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="form-group"><label for="driver_user_id">Sevkiyatçı</label>
        <select id="driver_user_id" name="driver_user_id" onchange="this.form.submit()">
            <option value="0">Tümü</option>
            <?php foreach ($drivers as $uid => $dn): ?><option value="<?= (int) $uid ?>"<?= $fDriver === (int) $uid ? ' selected' : '' ?>><?= e($dn) ?></option><?php endforeach; ?>
        </select>
    </div>
</form>
<?php endif; ?>

<?php if ($tab === 'collection'): ?>
    <?php if (!$collectionStops): ?>
        <div class="empty-state empty-compact"><p>Toplama işi bulunmuyor.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Tarih</th><th>Rota</th><th>Firma</th><th>Sevkiyatçı</th><th>Durum</th><th>Not</th><th>Foto</th></tr></thead>
            <tbody>
            <?php foreach ($collectionStops as $s): ?>
                <tr>
                    <td class="nowrap"><?= e($s['route_date'] ? fmt_date((string) $s['route_date']) : '—') ?></td>
                    <td><a href="<?= e(url('modules/shipments/route-view.php?id=' . (int) $s['route_id'])) ?>"><?= e((string) $s['route_name']) ?></a></td>
                    <td><?= e((string) ($s['company_name'] ?? '—')) ?></td>
                    <td><?= e($s['driver_name'] !== '' ? $s['driver_name'] : '—') ?></td>
                    <td><span class="badge <?= e(ship_stop_status_class((string) $s['status'])) ?>"><?= e(ship_stop_status_label((string) $s['status'])) ?></span></td>
                    <td><?= e((string) ($s['driver_note'] ?? $s['stop_note'] ?? '')) ?: '—' ?></td>
                    <td><?= (int) $s['photo_count'] > 0 ? '<span class="badge badge-success">Var</span>' : '<span class="badge badge-muted">Yok</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
<?php else: ?>
    <?php if (!$routes): ?>
        <div class="empty-state">
            <?= icon('truck', 'icon-lg') ?>
            <p><?= $tab === 'today' ? 'Bugün planlanmış rota yok.' : ($tab === 'completed' ? 'Tamamlanmış rota yok.' : 'Rota bulunmuyor.') ?></p>
            <?php if (can('shipments.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/shipments/route-create.php')) ?>"><?= icon('plus') ?>Yeni Rota Oluştur</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="route-list">
            <?php foreach ($routes as $r) { ship_render_route_card($r, $manage); } ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php layout_bottom();
