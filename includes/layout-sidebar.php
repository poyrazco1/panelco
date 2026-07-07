<?php
declare(strict_types=1);
/**
 * includes/layout-sidebar.php — sol menü (yetkiye göre süzülür).
 * Öğe: ['perm','etiket','path','ikon','match']. Grup: ['__group','Başlık','','',[]].
 *
 * Aktif menü, sayfanın MODÜL ANAHTARINA göre DEĞİL, çalışan dosyanın gerçek
 * göreli yoluna göre belirlenir. Böylece aynı anda tek menü aktif olur.
 */

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

$items = [
    ['__group', 'Genel', '', '', []],
    ['dashboard', 'Genel Bakış',  'dashboard.php',               'layout-dashboard',
        ['exact' => ['dashboard.php']]],
    ['currency',  'Kur Çevirici', 'modules/currency/index.php',  'coins',
        ['prefix' => ['modules/currency/']]],

    ['__group', 'Satış / Cari', '', '', []],
    ['customers', 'Müşteriler',   'modules/customers/index.php', 'users',
        ['prefix' => ['modules/customers/']]],
    ['suppliers', 'Tedarikçiler', 'modules/suppliers/index.php', 'warehouse',
        ['prefix' => ['modules/suppliers/']]],
    ['quotes', 'Teklifler', 'modules/quotes/index.php', 'file-text',
        ['prefix' => ['modules/quotes/']]],
    ['orders', 'Siparişler', 'modules/orders/index.php', 'clipboard-list',
        ['prefix' => ['modules/orders/']]],
    ['reconciliation', 'Mutabakat', 'modules/reconciliation/index.php', 'file-pen',
        ['prefix' => ['modules/reconciliation/']]],
    ['tsoft_products', 'T-Soft Ürünler', 'modules/tsoft-products/index.php', 'store',
        ['prefix' => ['modules/tsoft-products/']]],

    ['__group', 'Teknik Servis', '', '', []],
    ['service', 'Servis Kabul',          'modules/service/intake.php', 'clipboard-plus',
        ['exact' => ['modules/service/intake.php']]],
    ['service', 'Servis Kayıtları',      'modules/service/index.php',  'clipboard-list',
        ['prefix' => ['modules/service/'],
         'not'    => ['modules/service/intake.php'],
         'not_prefix' => ['modules/service/repairer', 'modules/service/_repairer',
                          'modules/service/statuses', 'modules/service/terms']]],
    ['rma', 'İade-Değişim Yönetimi',     'modules/rma/index.php',      'rotate-ccw',
        ['prefix' => ['modules/rma/']]],

    ['__group', 'İK / Personel', '', '', []],
    ['leave', 'Yıllık İzin Takibi', 'modules/leave/index.php', 'calendar-check',
        ['exact'  => ['modules/leave/index.php'],
         'prefix' => ['modules/leave/history.php', 'modules/leave/balance-adjust.php']]],
    ['leave', 'İzin Talepleri',     'modules/leave/requests.php', 'clipboard-list',
        ['prefix' => ['modules/leave/requests.php', 'modules/leave/request-']]],
    ['attendance', 'Puantaj',          'modules/attendance/index.php',   'calendar-days',
        ['exact'  => ['modules/attendance/index.php'],
         'prefix' => ['modules/attendance/print.php']]],
    ['attendance', 'Puantaj Raporları', 'modules/attendance/reports.php', 'file-text',
        ['prefix' => ['modules/attendance/reports.php']]],
    ['commissions', 'Primler', 'modules/commissions/index.php', 'calculator',
        ['prefix' => ['modules/commissions/']]],
    ['dashboard', 'Bildirim Merkezi',  'modules/notifications/index.php', 'bell',
        ['prefix' => ['modules/notifications/']]],

    ['__group', 'Varlık & Araçlar', '', '', []],
    ['inventory', 'Envanter / Demirbaş', 'modules/inventory/index.php', 'package-check',
        ['prefix' => ['modules/inventory/']]],
    ['reports', 'Raporlar', 'modules/reports/index.php', 'file-text',
        ['prefix' => ['modules/reports/']]],
    ['file_manager', 'Dosya Yöneticisi', 'modules/file-manager/index.php', 'file-down',
        ['prefix' => ['modules/file-manager/']]],
    ['password_vault', 'Şifre Kasası', 'modules/password-vault/index.php', 'shield',
        ['prefix' => ['modules/password-vault/']]],

    ['__group', 'Ayarlar', '', '', []],
    ['integrations', 'Entegrasyonlar', 'modules/integrations/index.php', 'plug',
        ['prefix' => ['modules/integrations/']]],
    ['settings', 'Genel Ayarlar', 'modules/settings/index.php', 'settings',
        ['prefix' => ['modules/settings/',
                      // Ayar niteliğindeki servis alt sayfaları da Genel Ayarlar'ı aktif etsin
                      'modules/service/repairer', 'modules/service/_repairer',
                      'modules/service/statuses', 'modules/service/terms']]],
];

// Grup başlığını yalnızca altında yetkili öğe varsa göster
$visible = [];
$n = count($items);
for ($i = 0; $i < $n; $i++) {
    $key = $items[$i][0];
    if ($key === '__group') {
        $has = false;
        for ($j = $i + 1; $j < $n && $items[$j][0] !== '__group'; $j++) {
            if (can($items[$j][0])) { $has = true; break; }
        }
        if ($has) { $visible[] = $items[$i]; }
    } elseif (can($key)) {
        $visible[] = $items[$i];
    }
}
?>
<aside class="sidebar" id="sidebar" aria-label="Ana menü">
    <div class="sidebar-brand">
        <span class="brand-mark" aria-hidden="true"></span>
        <span class="brand-text"><?= e(SITE_NAME) ?></span>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <?php foreach ($visible as $it): ?>
                <?php if ($it[0] === '__group'): ?>
                    <li class="nav-group"><?= e($it[1]) ?></li>
                <?php else: ?>
                    <?php $isActive = $navActiveMatch($it[4] ?? []); ?>
                    <li>
                        <a href="<?= e(url($it[2])) ?>" class="nav-link<?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                            <?= icon($it[3] ?? 'settings') ?>
                            <span class="nav-text"><?= e($it[1]) ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="sidebar-foot"><span class="muted small">v1.0</span></div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" hidden></div>
