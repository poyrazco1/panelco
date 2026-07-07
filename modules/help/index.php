<?php
declare(strict_types=1);

/** modules/help/index.php — Yardım Merkezi: arama + kategori + liste. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/help.php';

auth_boot();
require_permission('help.view');

$manage = can('help.edit') || can('help.create');
$f = ['category' => (string) ($_GET['category'] ?? ''), 'search' => trim((string) ($_GET['q'] ?? ''))];
$rows = get_help_articles($f, !$manage ? true : false);
$counts = help_category_counts(!$manage);

layout_top('Yardım Merkezi', 'help');
?>
<div class="page-head"><h1 class="page-title">Yardım Merkezi</h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/help/categories.php')) ?>"><?= icon('list-checks') ?>Kategoriler</a>
    <?php if (can('help.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/help/create.php')) ?>"><?= icon('plus') ?>Yeni Konu</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>

<form method="get" action="<?= e(url('modules/help/index.php')) ?>" class="toolbar" style="margin-bottom:14px">
    <?php if ($f['category'] !== ''): ?><input type="hidden" name="category" value="<?= e($f['category']) ?>"><?php endif; ?>
    <div class="form-group help-suggest" style="flex:1 1 340px"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Örn: teklif nasıl oluşturulur, favicon nasıl yüklenir" autocomplete="off"><div id="helpSuggest"></div></div>
    <div class="form-group"><button type="submit" class="btn btn-primary btn-sm"><?= icon('search') ?>Ara</button></div>
</form>
<script>
(function () {
    var input = document.getElementById('q'), box = document.getElementById('helpSuggest');
    var url = <?= json_encode(url('modules/help/search.php'), JSON_UNESCAPED_UNICODE) ?>;
    var viewUrl = <?= json_encode(url('modules/help/view.php'), JSON_UNESCAPED_UNICODE) ?>;
    if (!input || !box) { return; }
    var t = null;
    function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
    input.addEventListener('input', function () {
        clearTimeout(t);
        var q = input.value.trim();
        if (q.length < 2) { box.innerHTML = ''; return; }
        t = setTimeout(function () {
            fetch(url + '?q=' + encodeURIComponent(q)).then(function(r){return r.json();}).then(function(d){
                if (!d.ok || !d.items || !d.items.length) { box.innerHTML = ''; return; }
                var html = '<div class="help-suggest-box">';
                d.items.forEach(function(it){ html += '<a href="' + viewUrl + '?id=' + it.id + '">' + esc(it.title) + ' <span class="hs-cat">· ' + esc(it.category) + '</span></a>'; });
                box.innerHTML = html + '</div>';
            }).catch(function(){ box.innerHTML = ''; });
        }, 200);
    });
    document.addEventListener('click', function (e) { if (!input.parentNode.contains(e.target)) { box.innerHTML = ''; } });
})();
</script>

<div class="tab-row">
    <a class="tab-chip<?= $f['category'] === '' ? ' is-active' : '' ?>" href="<?= e(url('modules/help/index.php')) ?>">Tümü <span class="tab-count"><?= (int) $counts['all'] ?></span></a>
    <?php foreach (help_categories() as $k => $l): if (empty($counts[$k])) continue; ?>
        <a class="tab-chip<?= $f['category'] === $k ? ' is-active' : '' ?>" href="<?= e(url('modules/help/index.php?category=' . $k)) ?>"><?= e($l) ?> <span class="tab-count"><?= (int) $counts[$k] ?></span></a>
    <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Aramanıza uygun yardım konusu bulunamadı.</div></div></div>
<?php else: ?>
<div class="help-list">
    <?php foreach ($rows as $r): ?>
        <a class="help-item" href="<?= e(url('modules/help/view.php?id=' . (int) $r['id'])) ?>">
            <span class="help-item-cat"><?= e(help_category_label((string) $r['category'])) ?></span>
            <span class="help-item-title"><?= e($r['title']) ?><?php if ($manage && (int) $r['is_active'] === 0): ?> <span class="badge badge-muted">Pasif</span><?php endif; ?></span>
            <?php if (!empty($r['short_desc'])): ?><span class="help-item-desc"><?= e((string) $r['short_desc']) ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php layout_bottom();
