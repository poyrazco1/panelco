<?php
declare(strict_types=1);

/**
 * modules/tsoft-products/index.php
 * T-Soft ürün arama (ad / kod / barkod). Sunucu tarafı arama + AJAX canlı arama.
 * T-Soft erişilemezse kullanıcı-dostu hata gösterilir; uydurma veri üretilmez.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/tsoft.php';

auth_boot();
require_permission('tsoft_products.view');

$q  = trim((string) ($_GET['q'] ?? ''));
$by = (string) ($_GET['by'] ?? 'all');
if (!in_array($by, ['all', 'code', 'barcode', 'name'], true)) { $by = 'all'; }

$result = null;
if ($q !== '') {
    $result = tsoft_search_products($q, $by, 20);
}

layout_top('T-Soft Ürünler', 'tsoft_products');
?>
<div class="page-head">
    <h1 class="page-title">T-Soft Ürünler</h1>
    <div class="page-actions">
        <?php if (can('tsoft_products.view')): ?><a class="btn btn-sm" href="<?= e(url('modules/tsoft-products/cache-clear.php')) ?>"><?= icon('refresh-cw') ?>Önbellek</a><?php endif; ?>
    </div>
</div>

<?= render_flashes() ?>

<form method="get" action="<?= e(url('modules/tsoft-products/index.php')) ?>" class="toolbar" id="tsoftSearchForm">
    <div class="form-group" style="flex:1 1 320px"><label for="q">Ürün ara</label><input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Ürün adı, ürün kodu veya barkod" autocomplete="off"></div>
    <div class="form-group"><label for="by">Kriter</label>
        <select id="by" name="by">
            <option value="all"<?= $by === 'all' ? ' selected' : '' ?>>Tümü</option>
            <option value="name"<?= $by === 'name' ? ' selected' : '' ?>>Ürün adı</option>
            <option value="code"<?= $by === 'code' ? ' selected' : '' ?>>Ürün kodu</option>
            <option value="barcode"<?= $by === 'barcode' ? ' selected' : '' ?>>Barkod</option>
        </select>
    </div>
    <div class="form-group"><button type="submit" class="btn btn-primary btn-sm"><?= icon('search') ?>Ara</button></div>
</form>

<div id="tsoftResults">
<?php if ($result !== null): ?>
    <?php if (!$result['ok']): ?>
        <div class="alert alert-error"><?= e((string) $result['error']) ?></div>
    <?php elseif (!$result['products']): ?>
        <div class="card"><div class="card-body"><div class="empty">Ürün bulunamadı.</div></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kod</th><th>Barkod</th><th>Ürün Adı</th><th>Marka</th><th class="nowrap">Fiyat</th><th class="nowrap">Stok</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($result['products'] as $p): ?>
                        <tr>
                            <td class="nowrap"><?= e((string) $p['code']) ?></td>
                            <td class="nowrap"><?= e((string) $p['barcode']) ?></td>
                            <td><?= e((string) $p['name']) ?></td>
                            <td><?= e((string) $p['brand']) ?></td>
                            <td class="nowrap"><?= e(trim((string) $p['price'] . ' ' . (string) $p['currency'])) ?: '—' ?></td>
                            <td class="nowrap"><?= e((string) $p['stock_amount']) ?: '—' ?></td>
                            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/tsoft-products/view.php?code=' . rawurlencode((string) $p['code']))) ?>"><?= icon('eye', 'icon-xs') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="card"><div class="card-body"><div class="empty">Aramak için ürün adı, kod veya barkod girin.</div></div></div>
<?php endif; ?>
</div>

<?php
layout_bottom();
