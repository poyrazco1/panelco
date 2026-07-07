<?php
/**
 * modules/orders/_order-form.php — Ortak sipariş formu (quotes.js ile çalışır).
 * Beklenen: $o (sipariş+items), $customers, $vatDefault.
 */
$o = isset($o) && is_array($o) ? $o : [];
$items = $o['items'] ?? [];
$val = static fn(string $k, string $def = ''): string => e((string) ($o[$k] ?? $def));
$cur = (string) ($o['currency'] ?? 'TRY');
$customers = $customers ?? [];
$vatDefault = isset($vatDefault) ? (string) $vatDefault : '20';
?>
<div class="card" style="max-width:980px">
    <div class="card-header"><h2>Sipariş Bilgileri</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="customer_id">Müşteri seç</label>
                <select id="customer_id" name="customer_id">
                    <option value="0">— Manuel giriş —</option>
                    <?php foreach ($customers as $cst): ?>
                        <option value="<?= (int) $cst['id'] ?>" data-name="<?= e($cst['company_name']) ?>"
                            <?= (int) ($o['customer_id'] ?? 0) === (int) $cst['id'] ? ' selected' : '' ?>><?= e($cst['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="customer_name">Müşteri adı *</label><input type="text" id="customer_name" name="customer_name" value="<?= $val('customer_name') ?>" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="order_date">Sipariş tarihi</label><input type="date" id="order_date" name="order_date" value="<?= $val('order_date', date('Y-m-d')) ?>"></div>
            <div class="form-group"><label for="currency">Para birimi</label>
                <select id="currency" name="currency"><?php foreach (quote_currencies() as $cv => $cl): ?><option value="<?= e($cv) ?>"<?= $cur === $cv ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label for="status">Sipariş durumu</label>
                <select id="status" name="status"><?php foreach (order_statuses() as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= (string) ($o['status'] ?? 'draft') === $sv ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select>
            </div>
        </div>
    </div>
</div>

<div class="card" style="max-width:980px">
    <div class="card-header"><h2>Ürün Satırları</h2></div>
    <div class="card-body">
        <div class="ts-search">
            <input type="text" id="tsSearchInput" placeholder="T-Soft'ta ürün ara (ad, kod, barkod)" autocomplete="off">
            <select id="tsSearchBy"><option value="all">Tümü</option><option value="name">Ad</option><option value="code">Kod</option><option value="barcode">Barkod</option></select>
            <button type="button" class="btn btn-sm" id="tsSearchBtn"><?= icon('search') ?>T-Soft'ta Ara</button>
        </div>
        <div id="tsResults" class="ts-results"></div>
        <div class="table-wrap">
            <table class="table quote-lines">
                <thead><tr><th style="width:110px">Kod</th><th>Ürün adı</th><th style="width:120px">Marka</th><th style="width:80px">Adet</th><th style="width:110px">B.Fiyat</th><th style="width:80px">KDV %</th><th style="width:110px" class="nowrap">Tutar</th><th style="width:40px"></th></tr></thead>
                <tbody id="lineRows">
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><input type="text" name="item_code[]" value="<?= e((string) ($it['product_code'] ?? '')) ?>"><input type="hidden" name="item_barcode[]" value="<?= e((string) ($it['barcode'] ?? '')) ?>"><input type="hidden" name="item_disc[]" value="0"></td>
                            <td><input type="text" name="item_name[]" value="<?= e((string) ($it['name'] ?? '')) ?>" required></td>
                            <td><input type="text" name="item_brand[]" value="<?= e((string) ($it['brand'] ?? '')) ?>"></td>
                            <td><input type="text" name="item_qty[]" value="<?= e((string) ($it['qty'] ?? 1)) ?>" inputmode="decimal"></td>
                            <td><input type="text" name="item_price[]" value="<?= e((string) ($it['unit_price'] ?? 0)) ?>" inputmode="decimal"></td>
                            <td><input type="text" name="item_vat[]" value="<?= e((string) ($it['vat_rate'] ?? $vatDefault)) ?>" inputmode="decimal"></td>
                            <td class="nowrap"><span class="line-total">0,00</span></td>
                            <td><button type="button" class="btn btn-xs line-remove"><?= icon('x', 'icon-xs') ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm" id="addLineBtn"><?= icon('plus') ?>Satır ekle</button>
        <div class="quote-totals">
            <table class="quote-total-table" style="margin-left:auto">
                <tr><td>Ara Toplam</td><td id="sumSubtotal">0,00</td></tr>
                <tr><td>KDV</td><td id="sumVat">0,00</td></tr>
                <tr class="grand"><td>Genel Toplam</td><td id="sumGrand">0,00</td></tr>
            </table>
        </div>
    </div>
</div>

<div class="card" style="max-width:980px">
    <div class="card-header"><h2>Kargo & Ödeme</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="cargo_company">Kargo firması</label><input type="text" id="cargo_company" name="cargo_company" value="<?= $val('cargo_company') ?>"></div>
            <div class="form-group"><label for="tracking_no">Kargo takip no</label><input type="text" id="tracking_no" name="tracking_no" value="<?= $val('tracking_no') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="payment_method">Ödeme yöntemi</label><input type="text" id="payment_method" name="payment_method" value="<?= $val('payment_method') ?>" placeholder="Havale, kart, kapıda…"></div>
            <div class="form-group"><label for="payment_status">Ödeme durumu</label>
                <select id="payment_status" name="payment_status"><?php foreach (order_payment_statuses() as $pv => $pl): ?><option value="<?= e($pv) ?>"<?= (string) ($o['payment_status'] ?? 'pending') === $pv ? ' selected' : '' ?>><?= e($pl) ?></option><?php endforeach; ?></select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="shipping_address">Teslimat adresi</label><textarea id="shipping_address" name="shipping_address" rows="2"><?= $val('shipping_address') ?></textarea></div>
            <div class="form-group"><label for="billing_address">Fatura adresi</label><textarea id="billing_address" name="billing_address" rows="2"><?= $val('billing_address') ?></textarea></div>
        </div>
        <div class="form-group"><label for="notes">Notlar</label><textarea id="notes" name="notes" rows="2"><?= $val('notes') ?></textarea></div>
    </div>
</div>

<template id="lineTemplate">
    <tr>
        <td><input type="text" name="item_code[]"><input type="hidden" name="item_barcode[]"><input type="hidden" name="item_disc[]" value="0"></td>
        <td><input type="text" name="item_name[]" required></td>
        <td><input type="text" name="item_brand[]"></td>
        <td><input type="text" name="item_qty[]" value="1" inputmode="decimal"></td>
        <td><input type="text" name="item_price[]" value="0" inputmode="decimal"></td>
        <td><input type="text" name="item_vat[]" value="<?= e($vatDefault) ?>" inputmode="decimal"></td>
        <td class="nowrap"><span class="line-total">0,00</span></td>
        <td><button type="button" class="btn btn-xs line-remove"><?= icon('x', 'icon-xs') ?></button></td>
    </tr>
</template>

<script>
(function () {
    var sel = document.getElementById('customer_id');
    if (!sel) { return; }
    sel.addEventListener('change', function () {
        var o = sel.options[sel.selectedIndex];
        if (!o || o.value === '0') { return; }
        var n = document.getElementById('customer_name'); var v = o.getAttribute('data-name');
        if (n && v) { n.value = v; }
    });
})();
</script>
