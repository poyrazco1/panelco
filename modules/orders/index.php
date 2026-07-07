<?php
declare(strict_types=1);
/** modules/orders/index.php — Sipariş listesi + özet + filtre. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/orders.php';
auth_boot();
require_permission('orders.view');

$f = ['status' => (string) ($_GET['status'] ?? ''), 'search' => trim((string) ($_GET['q'] ?? ''))];
$rows = get_orders($f);
$sum = order_summary();
$statuses = order_statuses();
layout_top('Siparişler', 'orders');
?>
<div class="page-head"><h1 class="page-title">Siparişler</h1><div class="page-actions">
<?php if (can('orders.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/orders/create.php')) ?>"><?= icon('plus') ?>Yeni Sipariş</a><?php endif; ?></div></div>
<?= render_flashes() ?>
<div class="rma-stats">
    <div class="rma-stat"><div class="rs-label">Toplam</div><div class="rs-value"><?= (int) $sum['total'] ?></div></div>
    <div class="rma-stat is-open"><div class="rs-label">Açık</div><div class="rs-value"><?= (int) $sum['open'] ?></div></div>
    <div class="rma-stat is-closed"><div class="rs-label">Teslim</div><div class="rs-value"><?= (int) $sum['delivered'] ?></div></div>
</div>
<form method="get" action="<?= e(url('modules/orders/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Sipariş no, müşteri, takip no"></div>
    <div class="form-group"><label for="status">Durum</label><select id="status" name="status"><option value="">Tümü</option>
        <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>
<?php if (!$rows): ?><div class="card"><div class="card-body"><div class="empty">Sipariş bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Sipariş No</th><th>Müşteri</th><th class="nowrap">Tarih</th><th class="nowrap">Tutar</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('modules/orders/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['order_no']) ?></strong></a></td>
            <td><?= e($r['customer_name']) ?></td>
            <td class="nowrap"><?= e((string) ($r['order_date'] ?? '—')) ?></td>
            <td class="nowrap"><?= e(fmt_money((float) $r['grand_total']) . ' ' . quote_currency_symbol((string) $r['currency'])) ?></td>
            <td><span class="badge <?= e(order_status_class((string) $r['status'])) ?>"><?= e(order_status_label((string) $r['status'])) ?></span></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/orders/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
            <?php if (can('orders.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/orders/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
