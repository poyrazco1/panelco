<?php
declare(strict_types=1);

/** modules/inventory/view.php — Demirbaş detayı. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/inventory.php';

auth_boot();
require_permission('inventory.view');

$id = (int) ($_GET['id'] ?? 0);
$it = get_inventory_item($id);
if (!$it) { flash('error', 'Demirbaş bulunamadı.'); redirect('modules/inventory/index.php'); }

layout_top('Demirbaş: ' . $it['name'], 'inventory');
$row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head"><h1 class="page-title"><?= e($it['name']) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/inventory/index.php')) ?>">← Envanter</a>
    <?php if (can('inventory.print')): ?><a class="btn btn-sm" href="javascript:window.print()"><?= icon('printer') ?>Yazdır</a><?php endif; ?>
    <?php if (can('inventory.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/inventory/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>
<div class="card" style="max-width:820px"><div class="card-header"><h2>Demirbaş Bilgileri</h2>
    <span class="badge <?= e(inventory_status_class((string) $it['status'])) ?>"><?= e(inventory_status_label((string) $it['status'])) ?></span></div>
    <div class="card-body"><div class="dl">
        <?= $row('Kategori', inventory_category_label((string) $it['category'])) ?>
        <?= $row('Marka', $it['brand']) ?>
        <?= $row('Model', $it['model']) ?>
        <?= $row('Seri no', $it['serial_no']) ?>
        <?= $row('Satın alma tarihi', $it['purchase_date']) ?>
        <?= $row('Fatura no', $it['invoice_no']) ?>
        <?= $row('Garanti bitiş', $it['warranty_end']) ?>
        <?= $row('Zimmetli personel', $it['assigned_name']) ?>
        <?= $row('Lokasyon', $it['location']) ?>
        <?= $row('Açıklama', $it['description']) ?>
        <?php if (!empty($it['file_path']) && is_file(APP_ROOT . '/' . ltrim((string) $it['file_path'], '/'))): ?>
            <div class="dl-row"><span class="dl-k">Dosya / fatura</span><span class="dl-v"><a href="<?= e(asset(ltrim((string) $it['file_path'], '/'))) ?>" target="_blank">Görüntüle / indir</a></span></div>
        <?php endif; ?>
    </div></div>
</div>
<?php layout_bottom();
