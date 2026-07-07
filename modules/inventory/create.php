<?php
declare(strict_types=1);

/** modules/inventory/create.php — Yeni demirbaş. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/inventory.php';

auth_boot();
require_permission('inventory.create');

$errors = [];
$it = ['status' => 'active', 'category' => 'other'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $it = inventory_fields_from_input($_POST);
    $errors = inventory_validate($it);
    $up = inventory_handle_upload('inventory_file');
    if ($up['error']) { $errors[] = $up['error']; }
    if (!$errors) {
        $id = create_inventory_item($it, current_user_id(), $up['path']);
        if ($id > 0) {
            log_activity('inventory_create', 'inventory', $id, null, 'success', 'Demirbaş eklendi: ' . $it['name']);
            flash('success', 'Demirbaş eklendi.');
            redirect('modules/inventory/view.php?id=' . $id);
        }
        $errors[] = 'Demirbaş kaydedilemedi.';
    }
}
$people = get_personnel_options(false);
layout_top('Yeni Demirbaş', 'inventory');
?>
<div class="page-head"><h1 class="page-title">Yeni Demirbaş</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/inventory/index.php')) ?>">← Envanter</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/inventory/create.php')) ?>" enctype="multipart/form-data" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_inventory-form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/inventory/index.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
