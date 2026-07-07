<?php
declare(strict_types=1);

/**
 * tsoft-product-test.php
 * T-Soft getProducts yanıtını ayrıştırma + ürün alanlarını değişkenlere atama +
 * normalize testi (Part 1B). Yalnız yetkili (settings) kullanıcı. DB'ye yazmaz.
 * API çağrıları yalnız form gönderiminde (POST + CSRF) yapılır.
 */
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/tsoft.php';

auth_boot();
require_permission('settings');

$limit   = 10;
$start   = 0;
$columns = '';
$ran = false;
$login = null;
$prod = null;
$products = [];
$fieldKeys = [];
$parseError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ran = true;
    $limit   = max(1, min(100, (int) ($_POST['limit'] ?? 10)));
    $start   = max(0, (int) ($_POST['start'] ?? 0));
    $columns = trim((string) ($_POST['columns'] ?? ''));

    $login = tsoft_login();
    if (!empty($login['ok'])) {
        $params = ['token' => $login['token'], 'limit' => $limit, 'start' => $start];
        if ($columns !== '') { $params['columns'] = $columns; }
        $prod = tsoft_get_products_raw($params);
        if (!empty($prod['ok'])) {
            $products = tsoft_parse_products_response($prod);
            if ($products === []) {
                $parseError = 'Ürün listesi response içinde bulunamadı. Yanıt yapısı logs/ klasörüne yazıldı.';
            } else {
                $fieldKeys = tsoft_product_field_keys($products);
            }
        }
    }
}

// Tablo kolonları (alan adı varyasyonlarıyla)
$tableCols = [
    'ProductId'   => ['ProductId', 'productId', 'id'],
    'ProductCode' => ['ProductCode', 'productCode', 'StockCode'],
    'ProductName' => ['ProductName', 'productName', 'name'],
    'Barcode'     => ['Barcode', 'barcode'],
    'Brand'       => ['Brand', 'brand', 'BrandName'],
    'Category'    => ['Category', 'category', 'CategoryName'],
    'Price'       => ['Price', 'price'],
    'Currency'    => ['Currency', 'currency'],
    'StockAmount' => ['StockAmount', 'stockAmount', 'Stock'],
    'Image'       => ['ImageUrl', 'imageUrl', 'Image', 'image'],
    'Status'      => ['Status', 'status', 'IsActive', 'isActive'],
];

layout_top('T-Soft Ürün Testi', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">T-Soft Ürün Testi <span class="badge badge-muted">Part 1B</span></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('tsoft-test.php')) ?>"><?= icon('refresh-cw') ?>Bağlantı Testi</a>
        <a class="btn btn-sm" href="<?= e(url('dashboard.php')) ?>"><?= icon('chevron-left') ?>Panele Dön</a>
    </div>
</div>

<?= render_flashes() ?>

<div class="card">
    <div class="card-body">
        <p class="muted">Login token'ı ile <code>product/getProducts</code> çağrılır, yanıt ayrıştırılıp ürünler <code>foreach</code> ile gezilir ve alanları değişkenlere atanır. Ürünler bu partta <strong>veritabanına kaydedilmez</strong>. Token maskelidir.</p>
        <form method="post" action="<?= e(url('tsoft-product-test.php')) ?>" class="toolbar">
            <?= csrf_field() ?>
            <div class="form-group"><label for="f-limit">Limit (max 100)</label><input type="number" id="f-limit" name="limit" min="1" max="100" value="<?= (int) $limit ?>"></div>
            <div class="form-group"><label for="f-start">Start</label><input type="number" id="f-start" name="start" min="0" value="<?= (int) $start ?>"></div>
            <div class="form-group" style="min-width:280px;flex:1"><label for="f-columns">Columns (opsiyonel, virgülle)</label><input type="text" id="f-columns" name="columns" value="<?= e($columns) ?>" placeholder="ProductId,ProductCode,ProductName,Barcode"></div>
            <div class="form-group"><button type="submit" class="btn btn-primary"><?= icon('refresh-cw') ?>Ürünleri Getir</button></div>
        </form>
    </div>
</div>

<?php if ($ran): ?>
    <?php $loginOk = !empty($login['ok']); $prodOk = $loginOk && !empty($prod['ok']); ?>

    <div class="card">
        <div class="card-header"><h2>Özet</h2></div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr><th style="width:220px">Login</th><td><?= $loginOk ? '<span class="badge badge-success">Başarılı</span>' : '<span class="badge badge-danger">Başarısız</span>' ?> <code><?= e($loginOk ? (string) $login['masked'] : '—') ?></code></td></tr>
                    <tr><th>getProducts HTTP</th><td><?= (int) ($prod['http_status'] ?? 0) ?></td></tr>
                    <tr><th>Yanıt tipi</th><td><?= !empty($prod['is_json']) ? 'JSON' : 'Ham' ?></td></tr>
                    <tr><th>Ürün sayısı (bu sayfada)</th><td><?= count($products) ?></td></tr>
                    <?php if (!empty($prod['message'])): ?><tr><th>T-Soft mesajı</th><td><?= e((string) $prod['message']) ?></td></tr><?php endif; ?>
                </tbody>
            </table>

            <?php if (!$loginOk): ?>
                <p class="text-danger"><?= e((string) ($login['error'] ?? 'Token alınamadı.')) ?></p>
            <?php elseif (!$prodOk): ?>
                <p class="text-danger"><?= e(tsoft_user_error($prod)) ?></p>
            <?php elseif ($parseError !== ''): ?>
                <p class="text-danger"><?= e($parseError) ?></p>
                <p class="muted">Yanıtın ilk 1500 karakteri (yapı incelemesi için):</p>
                <pre class="code-block"><?= e(mb_substr((string) $prod['raw'], 0, 1500)) ?></pre>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($prodOk && !empty($products)): ?>
        <!-- Dinamik alan keşfi -->
        <div class="card">
            <div class="card-header"><h2>İlk ürünün tüm alanları (<?= count($fieldKeys) ?>)</h2></div>
            <div class="card-body">
                <?php if (!empty($fieldKeys)): ?>
                    <div class="chip-wrap">
                        <?php foreach ($fieldKeys as $k): ?><span class="field-chip"><?= e((string) $k) ?></span><?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Alan bulunamadı.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- İlk 10 ürün tablosu -->
        <div class="card">
            <div class="card-header"><h2>İlk 10 ürün</h2></div>
            <div class="card-body">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><?php foreach ($tableCols as $label => $_): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($products, 0, 10) as $item): ?>
                            <?php if (!is_array($item)) { continue; } ?>
                            <tr>
                                <?php foreach ($tableCols as $label => $keys): $val = tsoft_scalar(tsoft_pick($item, $keys)); ?>
                                    <td><?= $val === '' ? '<span class="muted">—</span>' : e(mb_strlen($val) > 60 ? mb_substr($val, 0, 60) . '…' : $val) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- İlk ürün: değişken ataması + normalize -->
        <?php
            $item = $products[0];
            // Ürün alanlarını açıkça değişkenlere ata (spec map)
            $productId   = $item['ProductId']   ?? null;
            $productCode = $item['ProductCode'] ?? null;
            $productName = $item['ProductName'] ?? null;
            $barcode     = $item['Barcode']     ?? null;
            $brand       = $item['Brand']       ?? null;
            $price       = $item['Price']       ?? null;
            $stockAmount = $item['StockAmount'] ?? null;
            $normalized = tsoft_normalize_product($item);
        ?>
        <div class="grid grid-2">
            <div class="card">
                <div class="card-header"><h2>İlk ürün — atanan değişkenler (örnek)</h2></div>
                <div class="card-body">
                    <table class="table">
                        <tbody>
                            <tr><th>$productId</th><td><?= e(tsoft_scalar($productId)) ?></td></tr>
                            <tr><th>$productCode</th><td><?= e(tsoft_scalar($productCode)) ?></td></tr>
                            <tr><th>$productName</th><td><?= e(tsoft_scalar($productName)) ?></td></tr>
                            <tr><th>$barcode</th><td><?= e(tsoft_scalar($barcode)) ?></td></tr>
                            <tr><th>$brand</th><td><?= e(tsoft_scalar($brand)) ?></td></tr>
                            <tr><th>$price</th><td><?= e(tsoft_scalar($price)) ?></td></tr>
                            <tr><th>$stockAmount</th><td><?= e(tsoft_scalar($stockAmount)) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>tsoft_normalize_product() çıktısı</h2></div>
                <div class="card-body">
                    <pre class="code-block"><?= e(json_encode(array_diff_key($normalized, ['raw' => 1]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                    <p class="field-hint">Ham veri <code>raw</code> altında da saklanır (burada kısaltıldı).</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
layout_bottom();
