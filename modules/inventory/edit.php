<?php
declare(strict_types=1);

/** modules/inventory/edit.php — Demirbaş düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/inventory.php';

auth_boot();
require_permission('inventory.edit');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$it = get_inventory_item($id);
if (!$it) { flash('error', 'Demirbaş bulunamadı.'); redirect('modules/inventory/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = inventory_fields_from_input($_POST);
    $errors = inventory_validate($d);
    $up = inventory_handle_upload('inventory_file');
    if ($up['error']) { $errors[] = $up['error']; }
    if (!$errors) {
        if (update_inventory_item($id, $d, current_user_id(), $up['path'])) {
            log_activity('inventory_edit', 'inventory', $id, null, 'success', 'Demirbaş güncellendi: ' . $d['name']);
            flash('success', 'Demirbaş güncellendi.');
            redirect('modules/inventory/view.php?id=' . $id);
        }
        $errors[] = 'Güncelleme başarısız.';
    }
    $it = array_merge($it, $d);
}
$people = get_personnel_options(false);
layout_top('Demirbaş Düzenle', 'inventory');
?>
<div class="page-head"><h1 class="page-title">Demirbaş Düzenle</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/inventory/view.php?id=' . $id)) ?>">← Detay</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/inventory/edit.php')) ?>" enctype="multipart/form-data" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_inventory-form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/inventory/view.php?id=' . $id)) ?>">Vazgeç</a>
    <?php if (can('inventory.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/inventory/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?></div>
</form>
<?php layout_bottom();
