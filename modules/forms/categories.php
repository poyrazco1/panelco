<?php
declare(strict_types=1);

/** modules/forms/categories.php — Form kategorileri yönetimi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';

auth_boot();
require_permission('forms.categories.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        form_center_delete_category((int) ($_POST['id'] ?? 0));
        flash('success', 'Kategori silindi (soft).');
    } else {
        $ok = form_center_save_category($_POST, (int) ($_POST['id'] ?? 0) ?: null, current_user_id());
        flash($ok ? 'success' : 'error', $ok ? 'Kategori kaydedildi.' : 'Kaydedilemedi (ad zorunlu).');
    }
    http_response_code(303);
    redirect('modules/forms/categories.php');
}

$rows = form_center_categories();
$edit = null;
if (($eid = (int) ($_GET['edit'] ?? 0)) > 0) { $edit = form_center_category_find($eid); }

layout_top('Form Kategorileri', 'settings');
?>
<div class="page-head"><h1 class="page-title">Form Kategorileri</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div></div>
<?= render_flashes() ?>

<div class="route-detail-grid">
    <div class="card"><div class="card-header"><strong><?= $edit ? 'Kategori Düzenle' : 'Yeni Kategori' ?></strong></div><div class="card-body">
        <form method="post" action="<?= e(url('modules/forms/categories.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
            <div class="form-group"><label for="name">Ad *</label><input type="text" id="name" name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" required></div>
            <div class="form-group"><label for="description">Açıklama</label><input type="text" id="description" name="description" value="<?= e((string) ($edit['description'] ?? '')) ?>"></div>
            <div class="form-group"><label for="sort_order">Sıra</label><input type="number" id="sort_order" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="is_active" value="1"<?= (!$edit || (int) $edit['is_active'] === 1) ? ' checked' : '' ?>> Aktif</label></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary btn-sm">Kaydet</button><?php if ($edit): ?><a class="btn btn-sm" href="<?= e(url('modules/forms/categories.php')) ?>">Vazgeç</a><?php endif; ?></div>
        </form>
    </div></div>

    <div class="card"><div class="card-header"><strong>Kategoriler</strong></div><div class="card-body">
        <?php if (!$rows): ?><div class="empty">Kategori yok.</div>
        <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Ad</th><th>Sıra</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $c): ?>
                <tr>
                    <td><strong><?= e((string) $c['name']) ?></strong><?php if (!empty($c['description'])): ?><div class="small muted"><?= e((string) $c['description']) ?></div><?php endif; ?></td>
                    <td><?= (int) $c['sort_order'] ?></td>
                    <td><?= (int) $c['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                    <td class="nowrap">
                        <a class="btn btn-xs" href="<?= e(url('modules/forms/categories.php?edit=' . (int) $c['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a>
                        <form method="post" action="<?= e(url('modules/forms/categories.php')) ?>" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="btn btn-xs"><?= icon('trash-2', 'icon-xs') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div></div>
</div>
<?php layout_bottom();
