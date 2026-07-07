<?php
declare(strict_types=1);
/** modules/help/categories.php — Kategori listesi + konu sayıları. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/help.php';
auth_boot();
require_permission('help.view');
$manage = can('help.edit') || can('help.create');
$counts = help_category_counts(!$manage);
layout_top('Yardım Kategorileri', 'help');
?>
<div class="page-head"><h1 class="page-title">Yardım Kategorileri</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/help/index.php')) ?>">← Yardım Merkezi</a></div></div>
<div class="settings-grid">
    <?php foreach (help_categories() as $k => $l): ?>
        <a class="set-card" href="<?= e(url('modules/help/index.php?category=' . $k)) ?>">
            <span class="set-ic icon-circle"><?= icon('help-circle', 'icon-lg') ?></span>
            <span class="set-body"><span class="set-title"><?= e($l) ?></span><span class="set-desc"><?= (int) ($counts[$k] ?? 0) ?> konu</span></span>
        </a>
    <?php endforeach; ?>
</div>
<?php layout_bottom();
