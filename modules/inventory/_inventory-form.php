<?php
/** modules/inventory/_inventory-form.php — ortak demirbaş form alanları. Beklenen: $it, $people */
$it = isset($it) && is_array($it) ? $it : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($it[$k] ?? $def));
$people = $people ?? [];
$cat = (string) ($it['category'] ?? 'other');
$status = (string) ($it['status'] ?? 'active');
?>
<div class="card" style="max-width:900px"><div class="card-header"><h2>Demirbaş Bilgileri</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group"><label for="name">Demirbaş adı *</label><input type="text" id="name" name="name" value="<?= $val('name') ?>" required></div>
        <div class="form-group"><label for="category">Kategori</label>
            <select id="category" name="category"><?php foreach (inventory_categories() as $k => $l): ?><option value="<?= e($k) ?>"<?= $cat === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="status">Durum</label>
            <select id="status" name="status"><?php foreach (inventory_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="brand">Marka</label><input type="text" id="brand" name="brand" value="<?= $val('brand') ?>"></div>
        <div class="form-group"><label for="model">Model</label><input type="text" id="model" name="model" value="<?= $val('model') ?>"></div>
        <div class="form-group"><label for="serial_no">Seri numarası</label><input type="text" id="serial_no" name="serial_no" value="<?= $val('serial_no') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="purchase_date">Satın alma tarihi</label><input type="date" id="purchase_date" name="purchase_date" value="<?= $val('purchase_date') ?>"></div>
        <div class="form-group"><label for="invoice_no">Fatura no</label><input type="text" id="invoice_no" name="invoice_no" value="<?= $val('invoice_no') ?>"></div>
        <div class="form-group"><label for="warranty_end">Garanti bitiş</label><input type="date" id="warranty_end" name="warranty_end" value="<?= $val('warranty_end') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="assigned_personnel_id">Zimmetli personel</label>
            <select id="assigned_personnel_id" name="assigned_personnel_id"><option value="0">— Yok —</option>
                <?php foreach ($people as $pid => $pname): ?><option value="<?= (int) $pid ?>"<?= (int) ($it['assigned_personnel_id'] ?? 0) === (int) $pid ? ' selected' : '' ?>><?= e($pname) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="location">Lokasyon</label><input type="text" id="location" name="location" value="<?= $val('location') ?>" placeholder="Ofis, depo, şube…"></div>
    </div>
    <div class="form-group"><label for="description">Açıklama</label><textarea id="description" name="description" rows="2"><?= $val('description') ?></textarea></div>
    <div class="form-group"><label for="inventory_file">Dosya / fatura (pdf, jpg, png — max 5 MB)</label>
        <input type="file" id="inventory_file" name="inventory_file" accept=".pdf,.jpg,.jpeg,.png,.webp">
        <?php if (!empty($it['file_path']) && is_file(APP_ROOT . '/' . ltrim((string) $it['file_path'], '/'))): ?>
            <div class="field-hint">Mevcut dosya: <a href="<?= e(asset(ltrim((string) $it['file_path'], '/'))) ?>" target="_blank">görüntüle</a> (yeni dosya yüklerseniz değişir)</div>
        <?php endif; ?>
    </div>
</div></div>
