<?php
/** modules/shipments/_shipment-form.php — sevkiyat form alanları. Beklenen: $s, $addresses, $couriers */
$s = isset($s) && is_array($s) ? $s : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($s[$k] ?? $def));
$addresses = $addresses ?? [];
$couriers = $couriers ?? [];
$type = (string) ($s['shipment_type'] ?? 'delivery');
$rel = (string) ($s['related_type'] ?? 'manual');
$prio = (string) ($s['priority'] ?? 'normal');
?>
<div class="card" style="max-width:900px"><div class="card-header"><h2>Sevkiyat Bilgileri</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group"><label for="customer_name">Müşteri / firma</label><input type="text" id="customer_name" name="customer_name" value="<?= $val('customer_name') ?>"></div>
        <div class="form-group"><label for="address_id">Sevkiyat adresi</label>
            <select id="address_id" name="address_id"><option value="0">— Seçiniz —</option>
                <?php foreach ($addresses as $ad): ?><option value="<?= (int) $ad['id'] ?>"<?= (int) ($s['address_id'] ?? 0) === (int) $ad['id'] ? ' selected' : '' ?>><?= e($ad['company_name'] . ' — ' . (string) ($ad['city'] ?? '')) ?></option><?php endforeach; ?>
            </select>
            <div class="field-hint"><a href="<?= e(url('modules/shipments/address-create.php')) ?>" target="_blank">+ Yeni adres ekle</a></div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="shipment_date">Sevkiyat tarihi</label><input type="date" id="shipment_date" name="shipment_date" value="<?= $val('shipment_date', date('Y-m-d')) ?>"></div>
        <div class="form-group"><label for="planned_date">Planlanan teslim</label><input type="date" id="planned_date" name="planned_date" value="<?= $val('planned_date') ?>"></div>
        <div class="form-group"><label for="shipment_type">Sevkiyat tipi</label>
            <select id="shipment_type" name="shipment_type"><?php foreach (shipment_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="related_type">İlgili kayıt tipi</label>
            <select id="related_type" name="related_type"><?php foreach (shipment_related_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $rel === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="related_ref">İlgili kayıt no</label><input type="text" id="related_ref" name="related_ref" value="<?= $val('related_ref') ?>" placeholder="SIP-2026-0001"></div>
        <div class="form-group"><label for="priority">Öncelik</label>
            <select id="priority" name="priority"><?php foreach (shipment_priorities() as $k => $l): ?><option value="<?= e($k) ?>"<?= $prio === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="courier_id">Sevkiyatçı personel</label>
            <select id="courier_id" name="courier_id"><option value="0">— Atanmadı —</option>
                <?php foreach ($couriers as $cid => $cname): ?><option value="<?= (int) $cid ?>"<?= (int) ($s['courier_id'] ?? 0) === (int) $cid ? ' selected' : '' ?>><?= e($cname) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="vehicle_info">Araç bilgisi</label><input type="text" id="vehicle_info" name="vehicle_info" value="<?= $val('vehicle_info') ?>" placeholder="Plaka / araç"></div>
    </div>
    <div class="form-group"><label for="description">Açıklama</label><textarea id="description" name="description" rows="2"><?= $val('description') ?></textarea></div>
    <div class="form-group"><label for="manager_note">Yönetici notu (sevkiyatçıya gösterilmez)</label><textarea id="manager_note" name="manager_note" rows="2"><?= $val('manager_note') ?></textarea></div>
</div></div>
