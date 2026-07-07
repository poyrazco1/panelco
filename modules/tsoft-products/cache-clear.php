<?php
declare(strict_types=1);

/**
 * modules/tsoft-products/cache-clear.php
 * T-Soft ürün önbelleği. Şu an ürünler her istekte CANLI çekilir (yerel kalıcı
 * önbellek tutulmaz); bu ekran PHP opcode önbelleğini tazeler ve durumu bildirir.
 * POST + CSRF + yetki.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';

auth_boot();
require_permission('tsoft_products.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cleared = 0;
    if (function_exists('opcache_reset')) { @opcache_reset(); $cleared++; }
    log_activity('tsoft_cache_clear', 'tsoft', null, null, 'success', 'T-Soft önbellek tazelendi');
    flash('success', 'T-Soft ürünleri canlı çekilir; yerel önbellek tutulmadığından temizlenecek kalıcı veri yok. PHP önbelleği tazelendi.');
    redirect('modules/tsoft-products/index.php');
}

layout_top('T-Soft Önbellek', 'tsoft_products');
?>
<div class="page-head"><h1 class="page-title">T-Soft Önbellek Temizle</h1></div>
<div class="card" style="max-width:560px">
    <div class="card-body">
        <p>T-Soft ürün verileri her aramada API'den <strong>canlı</strong> çekilir; panelde kalıcı ürün önbelleği tutulmaz. Yine de PHP opcode önbelleğini tazelemek için aşağıdaki butonu kullanabilirsiniz.</p>
        <form method="post" action="<?= e(url('modules/tsoft-products/cache-clear.php')) ?>">
            <?= csrf_field() ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= icon('refresh-cw') ?>Önbelleği Tazele</button>
                <a class="btn" href="<?= e(url('modules/tsoft-products/index.php')) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>
<?php layout_bottom();
