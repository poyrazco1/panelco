<?php
declare(strict_types=1);

/** modules/forms/templates.php — Form şablonları yönetimi (liste). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';

auth_boot();
require_permission('forms.templates.manage');

$rows = form_center_templates(['active' => (string) ($_GET['active'] ?? '')]);

layout_top('Form Şablonları', 'settings');
?>
<div class="page-head">
    <h1 class="page-title">Form Şablonları</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('modules/forms/template-create.php')) ?>"><?= icon('plus') ?>Yeni Şablon</a>
    </div>
</div>
<?= render_flashes() ?>

<?php if (!$rows): ?>
<div class="empty-state empty-compact"><p>Henüz form şablonu yok.</p></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Form Adı</th><th>Kategori</th><th>Tip</th><th>Onay</th><th>Alan Sayısı</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t): $fc = count(form_center_fields($t)); ?>
        <tr>
            <td><strong><?= e((string) $t['form_name']) ?></strong><div class="small muted"><?= e((string) $t['form_key']) ?></div></td>
            <td><?= e((string) ($t['category_name'] ?? '—')) ?></td>
            <td><span class="badge <?= in_array((string) $t['form_type'], ['external','both'], true) ? 'badge-info' : 'badge-muted' ?>"><?= e(form_type_label((string) $t['form_type'])) ?></span></td>
            <td><?= !empty($t['requires_approval']) ? '<span class="badge badge-leave">Gerekli</span>' : '—' ?></td>
            <td><?= (int) $fc ?></td>
            <td><?= (int) $t['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
            <td class="nowrap">
                <a class="btn btn-xs" href="<?= e(url('modules/forms/template-edit.php?id=' . (int) $t['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a>
                <?php if (in_array((string) $t['form_type'], ['external','both'], true) && can('forms.external.manage')): ?>
                    <a class="btn btn-xs" href="<?= e(url('modules/forms/external-link-create.php?template_id=' . (int) $t['id'])) ?>"><?= icon('link', 'icon-xs') ?></a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
