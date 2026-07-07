<?php
declare(strict_types=1);

/**
 * modules/settings/cache.php — Ön bellek temizleme.
 * Yalnızca güvenli, whitelist'lenmiş geçici klasörlerdeki dosyaları siler.
 * config.php, includes, database, logs, aktif session ve uploads'a DOKUNMAZ.
 * POST + CSRF + yetki.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/csrf.php';

auth_boot();
require_permission('settings');

/** Temizlenebilir güvenli klasörler (APP_ROOT'a göre). */
function cache_safe_dirs(): array
{
    return [
        'cache'        => 'Uygulama önbelleği (cache/)',
        'tmp'          => 'Geçici dosyalar (tmp/)',
        'cache/api'    => 'API önbelleği (cache/api/)',
        'cache/images' => 'Görsel önbelleği (cache/images/)',
    ];
}

/** Bir klasördeki dosyaları güvenle siler (alt klasörlere ve .htaccess/.gitkeep'e dokunmaz). */
function cache_clear_dir(string $rel): int
{
    $base = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $target = APP_ROOT . '/' . $rel;
    if (!is_dir($target)) { return 0; }
    $real = str_replace('\\', '/', (string) realpath($target));
    // Güvenlik: hedef APP_ROOT içinde olmalı ve kritik klasör OLMAMALI
    $forbidden = ['/includes', '/database', '/logs', '/config.php', '/uploads', '/api', '/modules', '/assets'];
    foreach ($forbidden as $fb) {
        if ($real === $base . $fb || strpos($real, $base . $fb . '/') === 0) { return 0; }
    }
    if (strpos($real, $base) !== 0) { return 0; }

    $count = 0;
    foreach ((array) @scandir($target) as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.htaccess' || $entry === '.gitkeep') { continue; }
        $path = $target . '/' . $entry;
        if (is_file($path)) { if (@unlink($path)) { $count++; } }
    }
    return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $total = 0;
    foreach (array_keys(cache_safe_dirs()) as $rel) {
        $total += cache_clear_dir($rel);
    }
    if (function_exists('opcache_reset')) { @opcache_reset(); }
    log_activity('cache_clear', 'system', null, null, 'success', 'Ön bellek temizlendi: ' . $total . ' dosya');
    flash('success', $total . ' önbellek dosyası temizlendi. Aktif oturumunuz ve veriler etkilenmedi.');
    http_response_code(303);
    redirect('modules/settings/cache.php');
}

layout_top('Ön Bellek Temizle', 'settings');
?>
<div class="page-head">
    <h1 class="page-title">Ön Bellek Temizle</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div>
</div>
<?= render_flashes() ?>
<div class="card" style="max-width:620px"><div class="card-body">
    <p>Aşağıdaki geçici klasörlerdeki dosyalar temizlenir. <strong>Aktif oturumunuz, config.php, includes, database, logs ve yüklenen dosyalar etkilenmez.</strong></p>
    <ul style="margin:12px 0 16px;padding-left:20px;color:var(--muted);font-size:13.5px">
        <?php foreach (cache_safe_dirs() as $rel => $label): ?><li><?= e($label) ?></li><?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(url('modules/settings/cache.php')) ?>">
        <?= csrf_field() ?>
        <div class="form-actions"><button type="submit" class="btn btn-primary"><?= icon('refresh-cw') ?>Ön Belleği Temizle</button></div>
    </form>
</div></div>
<?php layout_bottom();
