<?php
declare(strict_types=1);
/**
 * includes/layout-sidebar.php — sol menü (yetkiye göre süzülür).
 *
 * Menü İÇERİĞİ artık burada tanımlanmaz; TEK KAYNAK includes/navigation.php
 * → nav_sidebar_groups(). Bu dosya yalnızca yetki süzme + render + aktiflik
 * mantığını içerir. Böylece aynı menü bilgisi farklı dosyalarda tekrarlanmaz.
 *
 * Aktif menü, sayfanın modül anahtarına göre DEĞİL, çalışan dosyanın gerçek
 * göreli yoluna göre belirlenir. Böylece aynı anda tek menü aktif olur.
 */

require_once __DIR__ . '/navigation.php';

// Çalışan sayfanın APP_ROOT'a göre göreli yolu (ör. modules/service/index.php)
$curScript = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
$rootPath  = str_replace('\\', '/', (string) realpath(APP_ROOT));
if ($curScript !== '' && $rootPath !== '' && strpos($curScript, $rootPath) === 0) {
    $curRel = ltrim(substr($curScript, strlen($rootPath)), '/');
} else {
    $curRel = basename($curScript);
}

/** match kuralına göre bu öğe aktif mi? (dışlamalar önce, sonra exact/prefix) */
$navActiveMatch = static function (array $m) use ($curRel): bool {
    foreach ($m['not'] ?? [] as $nx)        { if ($curRel === $nx) { return false; } }
    foreach ($m['not_prefix'] ?? [] as $np) { if ($np !== '' && strpos($curRel, $np) === 0) { return false; } }
    foreach ($m['exact'] ?? [] as $p)       { if ($curRel === $p) { return true; } }
    foreach ($m['prefix'] ?? [] as $p)      { if ($p !== '' && strpos($curRel, $p) === 0) { return true; } }
    return false;
};

// Yetkiye göre süz: grubu yalnızca altında yetkili öğe varsa göster.
$groups = [];
foreach (nav_sidebar_groups() as $groupName => $items) {
    $shown = [];
    foreach ($items as $it) {
        if (can((string) $it['perm'])) { $shown[] = $it; }
    }
    if ($shown) { $groups[$groupName] = $shown; }
}
?>
<aside class="sidebar" id="sidebar" aria-label="Ana menü">
    <div class="sidebar-brand">
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-text"><?= e(SITE_NAME) ?></span>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <?php foreach ($groups as $groupName => $items): ?>
                <li class="nav-group"><?= e($groupName) ?></li>
                <?php foreach ($items as $it): ?>
                    <?php $isActive = $navActiveMatch($it['match'] ?? []); ?>
                    <li>
                        <a href="<?= e(url($it['path'])) ?>" class="nav-link<?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                            <?= icon($it['icon'] ?? 'settings') ?>
                            <span class="nav-text"><?= e($it['title']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="sidebar-foot"><span class="muted small">v1.0</span></div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
