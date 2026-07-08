<?php
declare(strict_types=1);

/**
 * modules/forms/index.php — Form Merkezi ana ekranı (form kataloğu).
 * Arama + kategori + iç/dış + aktif/pasif filtreleri; form kartları.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';

auth_boot();
require_permission('forms.view');

$manage = form_center_can_manage() || can('forms.templates.manage') || can('forms.edit');

$f = [
    'search'      => trim((string) ($_GET['q'] ?? '')),
    'category_id' => (int) ($_GET['category_id'] ?? 0),
    'form_type'   => (string) ($_GET['form_type'] ?? ''),
    'active'      => isset($_GET['active']) && $_GET['active'] !== '' ? (string) $_GET['active'] : '',
];
// Yönetici değilse yalnızca aktif formları görsün
if (!$manage) { $f['only_active'] = true; }
$templates = form_center_templates($f);
$categories = form_center_categories(true);

layout_top('Form Merkezi', 'forms');
?>
<div class="page-head">
    <h1 class="page-title">Form Merkezi</h1>
    <div class="page-actions">
        <?php if (can('forms.submissions.view')): ?><a class="btn btn-sm" href="<?= e(url('modules/forms/submissions.php')) ?>"><?= icon('clipboard-list') ?>Form Kayıtları</a><?php endif; ?>
        <?php if (can('forms.templates.manage')): ?><a class="btn btn-sm" href="<?= e(url('modules/forms/templates.php')) ?>"><?= icon('file-pen') ?>Şablonlar</a><?php endif; ?>
    </div>
</div>
<?= render_flashes() ?>

<form method="get" action="<?= e(url('modules/forms/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Form adı, açıklama"></div>
    <div class="form-group"><label for="category_id">Kategori</label><select id="category_id" name="category_id"><option value="0">Tümü</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $f['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="form_type">Tip</label><select id="form_type" name="form_type"><option value="">Tümü</option>
        <?php foreach (form_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['form_type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <?php if ($manage): ?>
    <div class="form-group"><label for="active">Durum</label><select id="active" name="active"><option value="">Tümü</option><option value="1"<?= $f['active'] === '1' ? ' selected' : '' ?>>Aktif</option><option value="0"<?= $f['active'] === '0' ? ' selected' : '' ?>>Pasif</option></select></div>
    <?php endif; ?>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$templates): ?>
    <div class="empty-state"><?= icon('file-pen', 'icon-lg') ?><p>Gösterilecek form bulunmuyor.</p>
        <?php if (can('forms.templates.manage')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/forms/template-create.php')) ?>"><?= icon('plus') ?>Yeni Form Şablonu</a><?php endif; ?>
    </div>
<?php else: ?>
<div class="form-catalog">
    <?php foreach ($templates as $t):
        $tid = (int) $t['id'];
        $isExternal = in_array((string) $t['form_type'], ['external', 'both'], true);
        $isInternal = in_array((string) $t['form_type'], ['internal', 'both'], true);
    ?>
    <div class="form-cat-card<?= (int) $t['is_active'] !== 1 ? ' is-passive' : '' ?>">
        <div class="form-cat-head">
            <span class="form-cat-ic icon-circle"><?= icon('file-pen', 'icon-sm') ?></span>
            <div class="form-cat-title">
                <strong><?= e((string) $t['form_name']) ?></strong>
                <span class="form-cat-cat"><?= e((string) ($t['category_name'] ?? 'Kategorisiz')) ?></span>
            </div>
        </div>
        <div class="form-cat-badges">
            <span class="badge <?= $isExternal ? 'badge-info' : 'badge-muted' ?>"><?= e(form_type_label((string) $t['form_type'])) ?></span>
            <?php if (!empty($t['requires_approval'])): ?><span class="badge badge-leave">Onay Gerekli</span><?php endif; ?>
            <span class="badge <?= (int) $t['is_active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $t['is_active'] === 1 ? 'Aktif' : 'Pasif' ?></span>
        </div>
        <?php if (!empty($t['description'])): ?><p class="form-cat-desc"><?= e((string) $t['description']) ?></p><?php endif; ?>
        <div class="form-cat-actions">
            <?php if ($isInternal && can('forms.submit') && (int) $t['is_active'] === 1): ?>
                <a class="btn btn-sm btn-primary" href="<?= e(url('modules/forms/submit.php?id=' . $tid)) ?>"><?= icon('file-pen') ?>Formu Aç</a>
            <?php endif; ?>
            <?php if (can('forms.submissions.view')): ?><a class="btn btn-sm" href="<?= e(url('modules/forms/submissions.php?template_id=' . $tid)) ?>"><?= icon('clipboard-list') ?>Kayıtları Gör</a><?php endif; ?>
            <?php if ($isExternal && can('forms.external.manage')): ?><a class="btn btn-sm" href="<?= e(url('modules/forms/external-link-create.php?template_id=' . $tid)) ?>"><?= icon('link') ?>Dış Link</a><?php endif; ?>
            <?php if (can('forms.templates.manage')): ?><a class="btn btn-sm" href="<?= e(url('modules/forms/template-edit.php?id=' . $tid)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php layout_bottom();
