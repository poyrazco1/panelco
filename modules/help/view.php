<?php
declare(strict_types=1);

/** modules/help/view.php — Yardım konusu detayı (adım adım + ilgili modüle git). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/help.php';

auth_boot();
require_permission('help.view');

$id = (int) ($_GET['id'] ?? 0);
$a  = get_help_article($id);
$manage = can('help.edit');
if (!$a || ((int) $a['is_active'] === 0 && !$manage)) { flash('error', 'Yardım konusu bulunamadı.'); redirect('modules/help/index.php'); }

$steps = help_steps_lines($a['steps'] ?? '');
$modUrl = help_module_url($a['related_module'] ?? null);

layout_top('Yardım: ' . $a['title'], 'help');
?>
<div class="page-head"><h1 class="page-title"><?= e($a['title']) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/help/index.php?category=' . rawurlencode((string) $a['category']))) ?>">← <?= e(help_category_label((string) $a['category'])) ?></a>
    <?php if (can('help.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/help/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>

<div class="card help-article" style="max-width:820px"><div class="card-body">
    <div class="help-meta">
        <span class="badge badge-info"><?= e(help_category_label((string) $a['category'])) ?></span>
        <?php if (!empty($a['screen_path'])): ?><span class="help-screen"><?= icon('layout-dashboard', 'icon-xs') ?> <?= e((string) $a['screen_path']) ?></span><?php endif; ?>
    </div>
    <?php if (!empty($a['short_desc'])): ?><p class="help-short"><?= e((string) $a['short_desc']) ?></p><?php endif; ?>
    <?php if (!empty($a['content'])): ?><div class="help-content"><?= nl2br(e((string) $a['content'])) ?></div><?php endif; ?>

    <?php if ($steps): ?>
        <h2 class="help-sec">Adım adım</h2>
        <ol class="help-steps">
            <?php foreach ($steps as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if (!empty($a['tags'])): ?>
        <div class="help-tags"><?php foreach (array_filter(array_map('trim', explode(',', (string) $a['tags']))) as $t): ?><span class="field-chip"><?= e($t) ?></span><?php endforeach; ?></div>
    <?php endif; ?>

    <?php if ($modUrl): ?>
        <div style="margin-top:16px"><a class="btn btn-primary btn-sm" href="<?= e($modUrl) ?>"><?= icon('chevron-left', 'icon-xs') ?> İlgili modüle git</a></div>
    <?php endif; ?>
</div></div>
<?php layout_bottom();
