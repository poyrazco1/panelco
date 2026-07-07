<?php
declare(strict_types=1);

/**
 * modules/tsoft-products/view.php — Tek T-Soft ürün detayı (kod ile).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/tsoft.php';

auth_boot();
require_permission('tsoft_products.view');

$code = trim((string) ($_GET['code'] ?? ''));
$product = null;
$error = null;
if ($code !== '') {
    $res = tsoft_search_products($code, 'code', 5);
    if (!$res['ok']) {
        $error = (string) $res['error'];
    } else {
        foreach ($res['products'] as $p) {
            if ((string) $p['code'] === $code) { $product = $p; break; }
        }
        if ($product === null && $res['products']) { $product = $res['products'][0]; }
    }
}

layout_top('T-Soft Ürün', 'tsoft_products');
$row = static fn(string $l, $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head">
    <h1 class="page-title">T-Soft Ürün</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/tsoft-products/index.php')) ?>">← Ürün Arama</a></div>
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php elseif ($product === null): ?>
    <div class="card"><div class="card-body"><div class="empty">Ürün bulunamadı.</div></div></div>
<?php else: ?>
    <div class="card" style="max-width:820px">
        <div class="card-header"><h2><?= e((string) $product['name']) ?></h2></div>
        <div class="card-body">
            <?php if (!empty($product['image_url'])): ?>
                <div class="settings-media-preview"><img src="<?= e((string) $product['image_url']) ?>" alt="" style="max-height:120px"></div>
            <?php endif; ?>
            <div class="dl">
                <?= $row('Ürün kodu', $product['code']) ?>
                <?= $row('Barkod', $product['barcode']) ?>
                <?= $row('Marka', $product['brand']) ?>
                <?= $row('Kategori', $product['category']) ?>
                <?= $row('Model', $product['model']) ?>
                <?= $row('Fiyat', trim((string) $product['price'] . ' ' . (string) $product['currency'])) ?>
                <?= $row('Stok', $product['stock_amount']) ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
layout_bottom();
