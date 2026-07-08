<?php
declare(strict_types=1);

/**
 * modules/settings/index.php
 * Genel Ayarlar — kategorili, aramalı ayar merkezi.
 *
 * Kart içerikleri burada TANIMLANMAZ; TEK KAYNAK includes/navigation.php
 * → nav_settings_catalog(). Bu sayfa yalnızca yetki süzme + render + arama
 * mantığını içerir. Sidebar ile aynı kaynaktan beslenir.
 *
 * Aktif (çalışan) ayarlar kategori kategori üstte; "Yakında" olan taslak
 * kartlar sayfayı kalabalık göstermesin diye altta ayrı bir collapse alanında.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/navigation.php';

auth_boot();
require_permission('settings');

/** Bir kartın durumunu belirle: url yoksa veya status='soon' ise "yakında". */
$cardIsSoon = static function (array $c): bool {
    return ($c['status'] ?? '') === 'soon' || empty($c['url']);
};
/** Kullanıcı bu kartı görebilir mi? (yetki yoksa hiç gösterilmez) */
$cardVisible = static function (array $c): bool {
    return empty($c['perm']) || can((string) $c['perm']);
};
/** Karttaki aranabilir metni üret (başlık + açıklama + kategori + anahtar kelime). */
$cardSearch = static function (array $c, string $group): string {
    return mb_strtolower(trim(
        ($c['title'] ?? '') . ' ' . ($c['desc'] ?? '') . ' ' . $group . ' ' . ($c['kw'] ?? '')
    ), 'UTF-8');
};

$catalog = nav_settings_catalog();

// Kartları aktif / yakında olarak ayır (yalnızca yetkili olanlar).
$activeGroups = [];   // [group => ['icon'=>, 'cards'=>[]]]
$soonGroups   = [];
$soonCount    = 0;
foreach ($catalog as $groupName => $section) {
    $icon = $section['icon'] ?? 'settings';
    foreach ($section['cards'] as $c) {
        if (!$cardVisible($c)) { continue; }
        if ($cardIsSoon($c)) {
            $soonGroups[$groupName]['icon'] = $icon;
            $soonGroups[$groupName]['cards'][] = $c;
            $soonCount++;
        } else {
            $activeGroups[$groupName]['icon'] = $icon;
            $activeGroups[$groupName]['cards'][] = $c;
        }
    }
}

/** Tek bir ayar kartını basar. */
$renderCard = static function (array $c, string $group, bool $soon) use ($cardSearch): void {
    $tag  = $soon ? 'div' : 'a';
    $href = (!$soon && !empty($c['url'])) ? ' href="' . e(url($c['url'])) . '"' : '';
    ?>
    <<?= $tag ?> class="set-card<?= $soon ? ' is-soon' : '' ?>"<?= $href ?> data-search="<?= e($cardSearch($c, $group)) ?>">
        <span class="set-ic icon-circle"><?= icon($c['icon'] ?? 'settings', 'icon-lg') ?></span>
        <span class="set-body">
            <span class="set-title">
                <?= e($c['title']) ?>
                <?php if ($soon): ?><span class="set-badge set-badge-soon">Yakında</span>
                <?php else: ?><span class="set-badge set-badge-active">Aktif</span><?php endif; ?>
            </span>
            <span class="set-desc"><?= e($c['desc'] ?? '') ?></span>
        </span>
        <?php if (!$soon): ?><span class="set-go" aria-hidden="true"><?= icon('chevron-left', 'icon-sm') ?></span><?php endif; ?>
    </<?= $tag ?>>
    <?php
};

layout_top('Genel Ayarlar', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Genel Ayarlar</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('dashboard.php')) ?>">← Geri</a>
    </div>
</div>

<p class="muted settings-intro">Tanım, ayar, entegrasyon, şablon ve sistem yapılandırması buradadır. Aramak için yazın; kategori ve açıklamalarda arar.</p>

<div class="settings-toolbar">
    <span class="settings-search-wrap">
        <?= icon('search', 'icon-sm settings-search-ic') ?>
        <input type="search" id="settingsSearch" class="settings-search" placeholder="Ayar ara: logo, kargo, servis, iade, personel, entegrasyon…" autocomplete="off" aria-label="Ayar ara">
    </span>
</div>

<div id="settingsResults">
    <?php foreach ($activeGroups as $groupName => $section): ?>
        <section class="settings-group" data-group="<?= e(mb_strtolower($groupName, 'UTF-8')) ?>">
            <h2 class="settings-group-title">
                <span class="settings-group-ic"><?= icon($section['icon'] ?? 'settings', 'icon-sm') ?></span>
                <?= e($groupName) ?>
            </h2>
            <div class="settings-grid">
                <?php foreach ($section['cards'] as $c) { $renderCard($c, $groupName, false); } ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<div id="settingsEmpty" class="settings-empty" hidden>Aradığınız ayara uygun sonuç bulunamadı.</div>

<?php if ($soonCount > 0): ?>
<details class="settings-soon" id="settingsSoon">
    <summary class="settings-soon-summary">
        <?= icon('clock', 'icon-sm') ?>
        <span>Yakında Gelecek Ayarlar</span>
        <span class="settings-soon-count"><?= (int) $soonCount ?></span>
    </summary>
    <div class="settings-soon-body">
        <?php foreach ($soonGroups as $groupName => $section): ?>
            <section class="settings-group" data-group="<?= e(mb_strtolower($groupName, 'UTF-8')) ?>">
                <h3 class="settings-soon-group-title"><?= e($groupName) ?></h3>
                <div class="settings-grid">
                    <?php foreach ($section['cards'] as $c) { $renderCard($c, $groupName, true); } ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<script>
(function(){
    var input = document.getElementById('settingsSearch');
    var results = document.getElementById('settingsResults');
    var empty = document.getElementById('settingsEmpty');
    var soon = document.getElementById('settingsSoon');
    if (!input) return;
    function norm(s){ return (s||'').toString().toLowerCase(); }

    function filterContainer(container, q){
        var any = false;
        container.querySelectorAll('.settings-group').forEach(function(group){
            var groupVisible = false;
            group.querySelectorAll('.set-card').forEach(function(card){
                var hit = q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
                card.style.display = hit ? '' : 'none';
                if (hit) groupVisible = true;
            });
            group.style.display = groupVisible ? '' : 'none';
            if (groupVisible) any = true;
        });
        return any;
    }

    input.addEventListener('input', function(){
        var q = norm(input.value).trim();
        var anyActive = filterContainer(results, q);
        var anySoon = false;
        if (soon) {
            anySoon = filterContainer(soon, q);
            // Arama yapılırken eşleşen "yakında" kartları görünür kılmak için aç
            soon.open = q !== '' && anySoon;
            soon.style.display = (q !== '' && !anySoon) ? 'none' : '';
        }
        empty.hidden = anyActive || anySoon;
    });
})();
</script>

<?php
layout_bottom();
