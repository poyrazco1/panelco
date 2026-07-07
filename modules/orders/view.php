<?php
declare(strict_types=1);
/** modules/orders/view.php — Sipariş detayı + durum + yazdır. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/orders.php';
auth_boot();
require_permission('orders.view');
$id = (int) ($_GET['id'] ?? 0);
$o = get_order($id);
if (!$o) { flash('error', 'Sipariş bulunamadı.'); redirect('modules/orders/index.php'); }
$sym = quote_currency_symbol((string) $o['currency']);
$pay = order_payment_statuses();
layout_top('Sipariş ' . $o['order_no'], 'orders');
$row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head"><h1 class="page-title">Sipariş · <?= e((string) $o['order_no']) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/orders/index.php')) ?>">← Siparişler</a>
    <?php if (can('orders.print') || can('orders.pdf')): ?><a class="btn btn-sm" href="<?= e(url('modules/orders/print.php?id=' . $id)) ?>" target="_blank"><?= icon('printer') ?>Yazdır / PDF</a><?php endif; ?>
    <?php if (can('orders.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/orders/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>
<div class="card" style="max-width:980px"><div class="card-header"><h2><?= e($o['customer_name']) ?></h2>
    <span class="badge <?= e(order_status_class((string) $o['status'])) ?>"><?= e(order_status_label((string) $o['status'])) ?></span></div>
    <div class="card-body"><div class="dl">
        <?= $row('Sipariş tarihi', $o['order_date']) ?>
        <?= $row('Kargo firması', $o['cargo_company']) ?>
        <?= $row('Kargo takip no', $o['tracking_no']) ?>
        <?= $row('Ödeme yöntemi', $o['payment_method']) ?>
        <?= $row('Ödeme durumu', $pay[(string) $o['payment_status']] ?? (string) $o['payment_status']) ?>
        <?= $row('Teslimat adresi', $o['shipping_address']) ?>
        <?= $row('Fatura adresi', $o['billing_address']) ?>
    </div></div>
</div>
<div class="card" style="max-width:980px"><div class="card-header"><h2>Satırlar</h2></div><div class="card-body">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Kod</th><th>Ürün</th><th>Marka</th><th class="nowrap">Adet</th><th class="nowrap">B.Fiyat</th><th class="nowrap">KDV%</th><th class="nowrap">Tutar</th></tr></thead>
        <tbody>
        <?php foreach (($o['items'] ?? []) as $it): ?>
            <tr><td class="nowrap"><?= e((string) ($it['product_code'] ?? '')) ?></td><td><?= e((string) $it['name']) ?></td><td><?= e((string) ($it['brand'] ?? '')) ?></td>
            <td class="nowrap"><?= e(rtrim(rtrim((string) $it['qty'], '0'), '.')) ?></td><td class="nowrap"><?= e(fmt_money((float) $it['unit_price'])) ?></td>
            <td class="nowrap"><?= e(rtrim(rtrim((string) $it['vat_rate'], '0'), '.')) ?></td><td class="nowrap"><?= e(fmt_money((float) $it['line_total']) . ' ' . $sym) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <table class="quote-total-table" style="margin-left:auto">
        <tr><td>Ara Toplam</td><td><?= e(fmt_money((float) $o['subtotal']) . ' ' . $sym) ?></td></tr>
        <tr><td>KDV</td><td><?= e(fmt_money((float) $o['vat_total']) . ' ' . $sym) ?></td></tr>
        <tr class="grand"><td>Genel Toplam</td><td><?= e(fmt_money((float) $o['grand_total']) . ' ' . $sym) ?></td></tr>
    </table>
</div></div>
<?php if (trim((string) ($o['notes'] ?? '')) !== ''): ?><div class="card" style="max-width:980px"><div class="card-body"><p><strong>Notlar:</strong><br><?= nl2br(e((string) $o['notes'])) ?></p></div></div><?php endif; ?>
<?php if (can('orders.status')): ?>
<div class="card" style="max-width:980px"><div class="card-header"><h2>Durum</h2></div><div class="card-body">
    <form method="post" action="<?= e(url('modules/orders/status.php')) ?>" style="display:flex;gap:8px;align-items:flex-end">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-group" style="margin:0"><label for="new_status">Durumu değiştir</label>
            <select id="new_status" name="status"><?php foreach (order_statuses() as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= (string) $o['status'] === $sv ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select></div>
        <button type="submit" class="btn btn-sm">Güncelle</button>
    </form>
</div></div>
<?php endif; ?>
<?php layout_bottom();
