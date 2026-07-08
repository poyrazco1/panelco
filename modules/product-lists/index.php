<?php
declare(strict_types=1);
/** modules/product-lists/index.php — Ürün listeleri (satış/talep). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/product-lists.php';
auth_boot();
require_permission('product_lists.view');

$f = ['type' => (string) ($_GET['type'] ?? ''), 'search' => trim((string) ($_GET['q'] ?? ''))];
$rows = pl_list($f);
layout_top('Ürün Listeleri', 'product_lists');
?>
<div class="page-head">
    <h1 class="page-title">Ürün Listeleri</h1>
    <div class="page-actions">
        <?php if (can('product_lists.create')): ?>
            <a class="btn btn-sm" href="<?= e(url('modules/product-lists/create.php?type=request')) ?>"><?= icon('plus') ?>Talep Listesi</a>
            <a class="btn btn-primary btn-sm" href="<?= e(url('modules/product-lists/create.php?type=sale')) ?>"><?= icon('plus') ?>Satış Listesi</a>
        <?php endif; ?>
    </div>
</div>
<?= render_flashes() ?>
<div class="tab-row">
    <a class="tab-chip<?= $f['type'] === '' ? ' is-active' : '' ?>" href="<?= e(url('modules/product-lists/index.php')) ?>">Tümü</a>
    <?php foreach (pl_types() as $k => $l): ?>
        <a class="tab-chip<?= $f['type'] === $k ? ' is-active' : '' ?>" href="<?= e(url('modules/product-lists/index.php?type=' . $k)) ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
</div>
<form method="get" action="<?= e(url('modules/product-lists/index.php')) ?>" class="toolbar">
    <?php if ($f['type'] !== ''): ?><input type="hidden" name="type" value="<?= e($f['type']) ?>"><?php endif; ?>
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Başlık, liste no"></div>
    <div class="form-group"><button class="btn btn-sm"><?= icon('search') ?>Ara</button></div>
</form>
<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Liste bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Liste No / Başlık</th><th>Tip</th><th>Para Birimi</th><th>Geçerlilik</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('modules/product-lists/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['title']) ?></strong></a><div class="small muted"><?= e($r['list_no']) ?></div></td>
            <td><span class="badge <?= $r['list_type'] === 'sale' ? 'badge-success' : 'badge-info' ?>"><?= e(pl_type_label((string) $r['list_type'])) ?></span></td>
            <td><?= e((string) ($r['currency'] ?? '')) ?: '—' ?></td>
            <td><?= e((string) ($r['valid_until'] ?? '')) ?: '—' ?></td>
            <td class="nowrap">
                <a class="btn btn-xs" href="<?= e(url('modules/product-lists/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
                <?php if (can('product_lists.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/product-lists/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
