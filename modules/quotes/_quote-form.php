<?php
/**
 * modules/quotes/_quote-form.php
 * Ortak teklif formu (başlık + T-Soft ürün arama + satırlar + toplam).
 * Beklenen: $q (teklif+items dizisi), $customers (seçim listesi), $vatDefault.
 */
$q = isset($q) && is_array($q) ? $q : [];
$items = $q['items'] ?? [];
$val = static fn(string $k, string $def = ''): string => e((string) ($q[$k] ?? $def));
$cur = (string) ($q['currency'] ?? 'TRY');
$vatMode = (string) ($q['vat_mode'] ?? 'excl');
$discType = (string) ($q['discount_type'] ?? 'none');
$customers = $customers ?? [];
$vatDefault = isset($vatDefault) ? (string) $vatDefault : '20';
?>
<div class="card" style="max-width:980px">
    <div class="card-header"><h2>Teklif Bilgileri</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label for="customer_id">Müşteri seç</label>
                <select id="customer_id" name="customer_id">
                    <option value="0">— Manuel giriş —</option>
                    <?php foreach ($customers as $cst): ?>
                        <option value="<?= (int) $cst['id'] ?>"
                            data-name="<?= e($cst['company_name']) ?>" data-contact="<?= e((string) ($cst['contact_name'] ?? '')) ?>"
                            data-phone="<?= e((string) ($cst['phone'] ?? '')) ?>" data-email="<?= e((string) ($cst['email'] ?? '')) ?>"
                            <?= (int) ($q['customer_id'] ?? 0) === (int) $cst['id'] ? ' selected' : '' ?>><?= e($cst['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="field-hint"><a href="<?= e(url('modules/customers/create.php')) ?>" target="_blank">+ Yeni müşteri ekle</a></div>
            </div>
            <div class="form-group"><label for="customer_name">Firma / Müşteri adı *</label><input type="text" id="customer_name" name="customer_name" value="<?= $val('customer_name') ?>" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="contact_name">Yetkili kişi</label><input type="text" id="contact_name" name="contact_name" value="<?= $val('contact_name') ?>"></div>
            <div class="form-group"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= $val('phone') ?>" inputmode="tel"></div>
            <div class="form-group"><label for="email">E-posta</label><input type="email" id="email" name="email" value="<?= $val('email') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="quote_date">Teklif tarihi</label><input type="date" id="quote_date" name="quote_date" value="<?= $val('quote_date', date('Y-m-d')) ?>"></div>
            <div class="form-group"><label for="valid_until">Geçerlilik tarihi</label><input type="date" id="valid_until" name="valid_until" value="<?= $val('valid_until') ?>"></div>
            <div class="form-group"><label for="currency">Para birimi</label>
                <select id="currency" name="currency"><?php foreach (quote_currencies() as $cv => $cl): ?><option value="<?= e($cv) ?>"<?= $cur === $cv ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label for="status">Durum</label>
                <select id="status" name="status"><?php foreach (quote_statuses() as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= (string) ($q['status'] ?? 'draft') === $sv ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select>
            </div>
        </div>
    </div>
</div>

<div class="card" style="max-width:980px">
    <div class="card-header"><h2>Ürün / Hizmet Satırları</h2></div>
    <div class="card-body">
        <div class="ts-search">
            <input type="text" id="tsSearchInput" placeholder="T-Soft'ta ürün ara (ad, kod, barkod)" autocomplete="off">
            <select id="tsSearchBy"><option value="all">Tümü</option><option value="name">Ad</option><option value="code">Kod</option><option value="barcode">Barkod</option></select>
            <button type="button" class="btn btn-sm" id="tsSearchBtn"><?= icon('search') ?>T-Soft'ta Ara</button>
        </div>
        <div id="tsResults" class="ts-results"></div>

        <div class="table-wrap">
            <table class="table quote-lines">
                <thead>
                    <tr><th style="width:110px">Kod</th><th>Ürün adı</th><th style="width:120px">Marka</th><th style="width:80px">Adet</th><th style="width:110px">B.Fiyat</th><th style="width:80px">KDV %</th><th style="width:80px">İsk %</th><th style="width:110px" class="nowrap">Tutar</th><th style="width:40px"></th></tr>
                </thead>
                <tbody id="lineRows">
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><input type="text" name="item_code[]" value="<?= e((string) ($it['product_code'] ?? '')) ?>"><input type="hidden" name="item_barcode[]" value="<?= e((string) ($it['barcode'] ?? '')) ?>"></td>
                            <td><input type="text" name="item_name[]" value="<?= e((string) ($it['name'] ?? '')) ?>" required></td>
                            <td><input type="text" name="item_brand[]" value="<?= e((string) ($it['brand'] ?? '')) ?>"></td>
                            <td><input type="text" name="item_qty[]" value="<?= e((string) ($it['qty'] ?? 1)) ?>" inputmode="decimal"></td>
                            <td><input type="text" name="item_price[]" value="<?= e((string) ($it['unit_price'] ?? 0)) ?>" inputmode="decimal"></td>
                            <td><input type="text" name="item_vat[]" value="<?= e((string) ($it['vat_rate'] ?? $vatDefault)) ?>" inputmode="decimal"></td>
                            <td><input type="text" name="item_disc[]" value="<?= e((string) ($it['discount'] ?? 0)) ?>" inputmode="decimal"></td>
                            <td class="nowrap"><span class="line-total">0,00</span></td>
                            <td><button type="button" class="btn btn-xs line-remove"><?= icon('x', 'icon-xs') ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm" id="addLineBtn"><?= icon('plus') ?>Satır ekle</button>

        <div class="quote-totals">
            <div class="form-row" style="max-width:420px;margin-left:auto">
                <div class="form-group"><label for="vat_mode">KDV</label>
                    <select id="vat_mode" name="vat_mode"><option value="excl"<?= $vatMode === 'excl' ? ' selected' : '' ?>>Hariç</option><option value="incl"<?= $vatMode === 'incl' ? ' selected' : '' ?>>Dahil</option></select>
                </div>
                <div class="form-group"><label for="discount_type">İskonto</label>
                    <select id="discount_type" name="discount_type"><option value="none"<?= $discType === 'none' ? ' selected' : '' ?>>Yok</option><option value="percent"<?= $discType === 'percent' ? ' selected' : '' ?>>Yüzde %</option><option value="amount"<?= $discType === 'amount' ? ' selected' : '' ?>>Tutar</option></select>
                </div>
                <div class="form-group"><label for="discount_value">İskonto değeri</label><input type="text" id="discount_value" name="discount_value" value="<?= $val('discount_value', '0') ?>" inputmode="decimal"></div>
            </div>
            <table class="quote-total-table">
                <tr><td>Ara Toplam</td><td id="sumSubtotal">0,00</td></tr>
                <tr><td>İskonto</td><td id="sumDiscount">0,00</td></tr>
                <tr><td>KDV</td><td id="sumVat">0,00</td></tr>
                <tr class="grand"><td>Genel Toplam</td><td id="sumGrand">0,00</td></tr>
            </table>
        </div>
    </div>
</div>

<div class="card" style="max-width:980px">
    <div class="card-header"><h2>Notlar & Koşullar</h2></div>
    <div class="card-body">
        <div class="form-group"><label for="notes">Notlar</label><textarea id="notes" name="notes" rows="2"><?= $val('notes') ?></textarea></div>
        <div class="form-group"><label for="terms">Şartlar ve koşullar</label><textarea id="terms" name="terms" rows="3"><?= $val('terms') ?></textarea></div>
    </div>
</div>

<template id="lineTemplate">
    <tr>
        <td><input type="text" name="item_code[]"><input type="hidden" name="item_barcode[]"></td>
        <td><input type="text" name="item_name[]" required></td>
        <td><input type="text" name="item_brand[]"></td>
        <td><input type="text" name="item_qty[]" value="1" inputmode="decimal"></td>
        <td><input type="text" name="item_price[]" value="0" inputmode="decimal"></td>
        <td><input type="text" name="item_vat[]" value="<?= e($vatDefault) ?>" inputmode="decimal"></td>
        <td><input type="text" name="item_disc[]" value="0" inputmode="decimal"></td>
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
        var map = { customer_name: 'name', contact_name: 'contact', phone: 'phone', email: 'email' };
        Object.keys(map).forEach(function (f) {
            var el = document.getElementById(f); var v = o.getAttribute('data-' + map[f]);
            if (el && v) { el.value = v; }
        });
    });
})();
</script>
