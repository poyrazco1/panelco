<?php
declare(strict_types=1);
/** modules/orders/print.php — Sipariş yazdır/PDF. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/forms.php';
auth_boot();
require_permission('orders.view');
$id = (int) ($_GET['id'] ?? 0);
$o = get_order($id);
if (!$o) { flash('error', 'Sipariş bulunamadı.'); redirect('modules/orders/index.php'); }
$sym = quote_currency_symbol((string) $o['currency']);
$fav = function_exists('pub_favicon_url') ? pub_favicon_url() : null;
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title>Sipariş <?= e((string) $o['order_no']) ?></title>
<?php if ($fav): ?><link rel="icon" href="<?= e($fav) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head><body>
<?php document_actions(); ?>
<div class="doc-sheet">
    <?php document_header(['title' => 'SİPARİŞ FORMU', 'number' => (string) $o['order_no'], 'date' => (string) ($o['order_date'] ?? '')]); ?>
    <div class="doc-party"><strong>Müşteri:</strong> <?= e($o['customer_name']) ?>
        <?php if (!empty($o['tracking_no'])): ?><br>Kargo: <?= e((string) $o['cargo_company']) ?> · Takip: <?= e((string) $o['tracking_no']) ?><?php endif; ?></div>
    <table class="table" style="margin-top:14px">
        <thead><tr><th>Kod</th><th>Ürün</th><th class="nowrap">Adet</th><th class="nowrap">B.Fiyat</th><th class="nowrap">Tutar</th></tr></thead>
        <tbody>
        <?php foreach (($o['items'] ?? []) as $it): ?>
            <tr><td class="nowrap"><?= e((string) ($it['product_code'] ?? '')) ?></td><td><?= e((string) $it['name']) ?></td>
            <td class="nowrap"><?= e(rtrim(rtrim((string) $it['qty'], '0'), '.')) ?></td><td class="nowrap"><?= e(fmt_money((float) $it['unit_price'])) ?></td>
            <td class="nowrap"><?= e(fmt_money((float) $it['line_total']) . ' ' . $sym) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <table class="quote-total-table" style="margin-left:auto;margin-top:12px">
        <tr><td>Ara Toplam</td><td><?= e(fmt_money((float) $o['subtotal']) . ' ' . $sym) ?></td></tr>
        <tr><td>KDV</td><td><?= e(fmt_money((float) $o['vat_total']) . ' ' . $sym) ?></td></tr>
        <tr class="grand"><td>Genel Toplam</td><td><?= e(fmt_money((float) $o['grand_total']) . ' ' . $sym) ?></td></tr>
    </table>
    <?php document_footer(); ?>
</div></body></html>
