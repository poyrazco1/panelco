<?php
declare(strict_types=1);

/**
 * tsoft-test.php
 * T-Soft bağlantı/token/getProducts geçici test sayfası (Part 1A).
 * Yalnızca yetkili (settings izni) kullanıcıya açıktır. SQL/DB işlemi yapmaz.
 * API çağrıları yalnızca "Testi Çalıştır" (POST + CSRF) ile tetiklenir.
 */
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/tsoft.php';

auth_boot();
require_permission('settings');

$ran = false;
$login = null;
$prod = null;
$located = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ran = true;
    $login = tsoft_login();
    if (!empty($login['ok'])) {
        $prod = tsoft_get_products_raw(['token' => $login['token']]);
        if (!empty($prod['ok']) && !empty($prod['is_json'])) {
            $located = tsoft_locate_products($prod['data']);
        }
    }
}

layout_top('T-Soft Bağlantı Testi', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">T-Soft Bağlantı Testi <span class="badge badge-muted">Part 1A</span></h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('dashboard.php')) ?>"><?= icon('chevron-left') ?>Panele Dön</a></div>
</div>

<?= render_flashes() ?>

<div class="card">
    <div class="card-body">
        <p class="muted">Bu sayfa T-Soft REST API bağlantısını test eder: <code>auth/login</code> ile token alır, ardından bu token'ı <code>product/getProducts</code> metoduna göndererek ham yanıtı denetler. Ürünler bu aşamada <strong>veritabanına kaydedilmez</strong>. Token ekranda ve loglarda maskelidir.</p>
        <form method="post" action="<?= e(url('tsoft-test.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary"><?= icon('refresh-cw') ?>Testi Çalıştır</button>
        </form>
    </div>
</div>

<?php if ($ran): ?>
    <?php
        $loginOk = !empty($login['ok']);
        $prodOk = $loginOk && !empty($prod['ok']);
        $prodJson = $prodOk && !empty($prod['is_json']);
    ?>

    <div class="card">
        <div class="card-header"><h2>1) Login (token)</h2></div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr><th style="width:220px">Sonuç</th><td><?= $loginOk ? '<span class="badge badge-success">Başarılı</span>' : '<span class="badge badge-danger">Başarısız</span>' ?></td></tr>
                    <tr><th>Token (maskeli)</th><td><code><?= e($loginOk ? (string) $login['masked'] : '—') ?></code></td></tr>
                    <tr><th>HTTP durumu</th><td><?= (int) ($login['result']['http_status'] ?? 0) ?></td></tr>
                    <tr><th>Yanıt tipi</th><td><?= !empty($login['result']['is_json']) ? 'JSON' : 'Ham (JSON değil)' ?></td></tr>
                    <?php if (!$loginOk): ?>
                        <tr><th>Mesaj</th><td class="text-danger"><?= e((string) ($login['error'] ?? 'Bilinmeyen hata.')) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (!$loginOk): ?>
                <p class="field-hint">Güvenlik nedeniyle login ham yanıtı (token içerebilir) burada gösterilmez. Ayrıntılar için sunucudaki <code>logs/</code> kayıtlarına bakın. "Yetkisiz IP" mesajı görüyorsanız, sunucunuzun genel IP adresini T-Soft yönetim panelinde API erişimine ekleyin.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($loginOk): ?>
    <div class="card">
        <div class="card-header"><h2>2) getProducts (ham yanıt)</h2></div>
        <div class="card-body">
            <table class="table">
                <tbody>
                    <tr><th style="width:220px">İstek sonucu</th><td><?= $prodOk ? '<span class="badge badge-success">Yanıt alındı</span>' : '<span class="badge badge-danger">Yanıt alınamadı</span>' ?></td></tr>
                    <tr><th>HTTP durumu</th><td><?= (int) ($prod['http_status'] ?? 0) ?></td></tr>
                    <tr><th>Yanıt tipi</th><td><?= $prodJson ? 'JSON' : 'Ham (JSON değil)' ?></td></tr>
                    <?php if ($prod['tsoft_success'] !== null): ?>
                        <tr><th>T-Soft success</th><td><?= !empty($prod['tsoft_success']) ? 'true' : 'false' ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($prod['message'])): ?>
                        <tr><th>T-Soft mesajı</th><td><?= e((string) $prod['message']) ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Toplam ürün</th><td><?= ($located && $located['total'] !== null) ? (int) $located['total'] : 'Yanıtta bulunamadı' ?></td></tr>
                </tbody>
            </table>

            <?php if (!$prodOk): ?>
                <p class="text-danger"><?= e(tsoft_user_error($prod)) ?></p>
            <?php elseif ($prodJson && $located && !empty($located['products'])): ?>
                <h3 style="margin:16px 0 8px">İlk 3 ürün (ham JSON)</h3>
                <pre class="code-block"><?= e(json_encode(array_slice($located['products'], 0, 3), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
            <?php elseif ($prodJson): ?>
                <p class="muted">Yanıt JSON ancak ürün dizisi otomatik konumlandırılamadı. Yapıyı görmek için ilk 1500 karakter:</p>
                <pre class="code-block"><?= e(mb_substr((string) $prod['raw'], 0, 1500)) ?></pre>
            <?php else: ?>
                <p class="muted">Yanıt JSON değil. İlk 1500 karakter (ham):</p>
                <pre class="code-block"><?= e(mb_substr((string) $prod['raw'], 0, 1500)) ?></pre>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php
layout_bottom();
