<?php
declare(strict_types=1);
/** modules/shipments/addresses.php — Sevkiyat adresleri listesi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipment_addresses.view');
$f = [
    'search'   => trim((string) ($_GET['q'] ?? '')),
    'city'     => trim((string) ($_GET['city'] ?? '')),
    'district' => trim((string) ($_GET['district'] ?? '')),
    'type'     => (string) ($_GET['type'] ?? ''),
    'active'   => isset($_GET['active']) && $_GET['active'] !== '' ? (string) $_GET['active'] : '',
];
$rows = get_shipment_addresses($f);
layout_top('Sevkiyat Adresleri', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Sevkiyat Adresleri</h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Sevkiyatlar</a>
    <?php if (can('shipment_addresses.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/shipments/address-create.php')) ?>"><?= icon('plus') ?>Yeni Adres</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>
<form method="get" action="<?= e(url('modules/shipments/addresses.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Firma, yetkili, telefon, ilçe"></div>
    <div class="form-group"><label for="city">İl</label><input type="text" id="city" name="city" value="<?= e($f['city']) ?>"></div>
    <div class="form-group"><label for="district">İlçe</label><input type="text" id="district" name="district" value="<?= e($f['district']) ?>"></div>
    <div class="form-group"><label for="active">Durum</label><select id="active" name="active"><option value="">Tümü</option><option value="1"<?= $f['active'] === '1' ? ' selected' : '' ?>>Aktif</option><option value="0"<?= $f['active'] === '0' ? ' selected' : '' ?>>Pasif</option></select></div>
    <div class="form-group"><label for="type">Tip</label><select id="type" name="type"><option value="">Tümü</option>
        <?php foreach (shipment_address_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>
<?php if (!$rows): ?><div class="card"><div class="card-body"><div class="empty">Adres bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Firma</th><th>Yetkili</th><th class="nowrap">Telefon</th><th>İl / İlçe</th><th>Tip</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $ml = shipment_maps_link($r); ?>
        <tr>
            <td><strong><?= e($r['company_name']) ?></strong></td>
            <td><?= e((string) ($r['contact_name'] ?? '')) ?: '—' ?></td>
            <td class="nowrap"><?= e((string) ($r['phone'] ?? '')) ?: '—' ?></td>
            <td><?= e(trim((string) ($r['city'] ?? '') . ' / ' . (string) ($r['district'] ?? ''), ' /')) ?: '—' ?></td>
            <td><?= e(shipment_address_types()[$r['address_type']] ?? $r['address_type']) ?></td>
            <td><?= (int) $r['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
            <td class="nowrap">
                <?php if ($ml): ?><a class="btn btn-xs" href="<?= e($ml) ?>" target="_blank" rel="noopener" title="Haritada aç"><?= icon('route', 'icon-xs') ?></a><?php endif; ?>
                <?php if (can('shipment_addresses.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/shipments/address-edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
