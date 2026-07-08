<?php
/**
 * modules/product-lists/_form.php — Ortak ürün listesi formu (başlık + dinamik kalemler).
 * Beklenen: $pl (değerler), $items (kalem dizisi)
 */
$pl = isset($pl) && is_array($pl) ? $pl : [];
$items = isset($items) && is_array($items) ? $items : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($pl[$k] ?? $def));
$curType = (string) ($pl['list_type'] ?? 'sale');
if (!$items) { $items = [['name' => '', 'sku' => '', 'brand' => '', 'qty' => '', 'unit' => '', 'price' => '', 'currency' => '', 'note' => '']]; }
?>
<div class="card" style="max-width:900px"><div class="card-header"><h2>Liste Bilgileri</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group grow"><label for="title">Başlık *</label><input type="text" id="title" name="title" value="<?= $val('title') ?>" required></div>
        <div class="form-group"><label for="list_type">Tip</label>
            <select id="list_type" name="list_type"><?php foreach (pl_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $curType === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="currency">Para Birimi</label><input type="text" id="currency" name="currency" value="<?= $val('currency') ?>" placeholder="USD, EUR"></div>
        <div class="form-group"><label for="valid_until">Geçerlilik Tarihi</label><input type="date" id="valid_until" name="valid_until" value="<?= $val('valid_until') ?>"></div>
    </div>
    <div class="form-group"><label for="intro">Giriş / Açıklama</label><textarea id="intro" name="intro" rows="2"><?= $val('intro') ?></textarea></div>
    <div class="form-group"><label for="notes">Notlar</label><textarea id="notes" name="notes" rows="2"><?= $val('notes') ?></textarea></div>
</div></div>

<div class="card" style="max-width:900px"><div class="card-header"><h2>Kalemler</h2></div><div class="card-body">
    <div class="table-wrap"><table class="table" id="itemsTable">
        <thead><tr><th>Ürün *</th><th>SKU</th><th>Marka</th><th>Miktar</th><th>Birim</th><th>Fiyat</th><th>Para</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><input type="text" name="item_name[]" value="<?= e((string) ($it['name'] ?? '')) ?>"></td>
                <td><input type="text" name="item_sku[]" value="<?= e((string) ($it['sku'] ?? '')) ?>" style="width:90px"></td>
                <td><input type="text" name="item_brand[]" value="<?= e((string) ($it['brand'] ?? '')) ?>" style="width:100px"></td>
                <td><input type="text" name="item_qty[]" value="<?= e((string) ($it['qty'] ?? '')) ?>" style="width:70px" inputmode="decimal"></td>
                <td><input type="text" name="item_unit[]" value="<?= e((string) ($it['unit'] ?? '')) ?>" style="width:70px"></td>
                <td><input type="text" name="item_price[]" value="<?= e((string) ($it['price'] ?? '')) ?>" style="width:90px" inputmode="decimal"></td>
                <td><input type="text" name="item_currency[]" value="<?= e((string) ($it['currency'] ?? '')) ?>" style="width:70px"></td>
                <td class="nowrap"><button type="button" class="btn btn-xs" onclick="this.closest('tr').remove()">×</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <button type="button" class="btn btn-sm" id="addItemBtn"><?= icon('plus') ?>Kalem Ekle</button>
</div></div>
<script>
document.getElementById('addItemBtn').addEventListener('click', function () {
    var tb = document.querySelector('#itemsTable tbody');
    var tr = tb.rows[0].cloneNode(true);
    tr.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    tb.appendChild(tr);
});
</script>
