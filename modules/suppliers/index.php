<?php
declare(strict_types=1);

/**
 * modules/suppliers/index.php — Tedarikçi listesi + arama.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/suppliers.php';

auth_boot();
require_permission('suppliers.view');

$f = [
    'active' => (string) ($_GET['active'] ?? ''),
    'search' => trim((string) ($_GET['q'] ?? '')),
];
$rows = get_suppliers($f);

layout_top('Tedarikçiler', 'suppliers');
?>
<div class="page-head">
    <h1 class="page-title">Tedarikçiler</h1>
    <div class="page-actions">
        <?php if (can('suppliers.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/suppliers/create.php')) ?>"><?= icon('plus') ?>Yeni Tedarikçi</a><?php endif; ?>
    </div>
</div>

<?= render_flashes() ?>

<form method="get" action="<?= e(url('modules/suppliers/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Firma, yetkili, telefon, ürün grubu"></div>
    <div class="form-group"><label for="active">Durum</label>
        <select id="active" name="active"><option value="">Tümü</option>
            <option value="1"<?= $f['active'] === '1' ? ' selected' : '' ?>>Aktif</option>
            <option value="0"<?= $f['active'] === '0' ? ' selected' : '' ?>>Pasif</option>
        </select>
    </div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Tedarikçi bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Firma</th><th>Yetkili</th><th class="nowrap">Telefon</th><th>Ürün Grupları</th><th>Para</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= e(url('modules/suppliers/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['company_name']) ?></strong></a><?php if ($r['code']): ?><div class="small muted"><?= e($r['code']) ?></div><?php endif; ?></td>
                    <td><?= e((string) ($r['contact_name'] ?? '')) ?: '—' ?></td>
                    <td class="nowrap"><?= e((string) ($r['phone'] ?? '')) ?: '—' ?></td>
                    <td><?= e((string) ($r['product_groups'] ?? '')) ?: '—' ?></td>
                    <td><?= e((string) $r['currency']) ?></td>
                    <td><?= (int) $r['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                    <td class="nowrap">
                        <a class="btn btn-xs" href="<?= e(url('modules/suppliers/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
                        <?php if (can('suppliers.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/suppliers/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php layout_bottom();
