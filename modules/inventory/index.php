<?php
declare(strict_types=1);

/** modules/inventory/index.php — Envanter listesi + filtre + özet. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/inventory.php';

auth_boot();
require_permission('inventory.view');

$f = [
    'category' => (string) ($_GET['category'] ?? ''),
    'status'   => (string) ($_GET['status'] ?? ''),
    'search'   => trim((string) ($_GET['q'] ?? '')),
];
$rows = get_inventory_items($f);
$counts = inventory_counts();

layout_top('Envanter / Demirbaş', 'inventory');
?>
<div class="page-head"><h1 class="page-title">Envanter / Demirbaş</h1><div class="page-actions">
    <?php if (can('inventory.export')): ?><a class="btn btn-sm" href="<?= e(url('modules/inventory/export.php')) ?>"><?= icon('download') ?>CSV</a><?php endif; ?>
    <?php if (can('inventory.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/inventory/create.php')) ?>"><?= icon('plus') ?>Yeni Demirbaş</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>

<form method="get" action="<?= e(url('modules/inventory/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Ad, marka, model, seri no, lokasyon"></div>
    <div class="form-group"><label for="category">Kategori</label><select id="category" name="category"><option value="">Tümü</option>
        <?php foreach (inventory_categories() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['category'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="status">Durum</label><select id="status" name="status"><option value="">Tümü</option>
        <?php foreach (inventory_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$rows): ?><div class="card"><div class="card-body"><div class="empty">Demirbaş bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Ad</th><th>Kategori</th><th>Marka / Model</th><th>Seri No</th><th>Zimmet</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('modules/inventory/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['name']) ?></strong></a></td>
            <td><?= e(inventory_category_label((string) $r['category'])) ?></td>
            <td><?= e(trim((string) ($r['brand'] ?? '') . ' ' . (string) ($r['model'] ?? ''))) ?: '—' ?></td>
            <td><?= e((string) ($r['serial_no'] ?? '')) ?: '—' ?></td>
            <td><?= e((string) ($r['assigned_name'] ?? '')) ?: '—' ?></td>
            <td><span class="badge <?= e(inventory_status_class((string) $r['status'])) ?>"><?= e(inventory_status_label((string) $r['status'])) ?></span></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/inventory/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
            <?php if (can('inventory.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/inventory/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
