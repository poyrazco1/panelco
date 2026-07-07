<?php
declare(strict_types=1);

/** modules/suppliers/edit.php — Tedarikçi düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/suppliers.php';

auth_boot();
require_permission('suppliers.edit');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$s  = get_supplier_by_id($id);
if (!$s) { flash('error', 'Tedarikçi bulunamadı.'); redirect('modules/suppliers/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = supplier_fields_from_input($_POST);
    $errors = supplier_validate($d);
    if (!$errors) {
        if (update_supplier($id, $d, current_user_id())) {
            log_activity('supplier_edit', 'supplier', $id, null, 'success', 'Tedarikçi güncellendi: ' . $d['company_name']);
            flash('success', 'Tedarikçi güncellendi.');
            redirect('modules/suppliers/view.php?id=' . $id);
        }
        $errors[] = 'Güncelleme başarısız.';
    }
    $s = array_merge($s, $d);
}

layout_top('Tedarikçi Düzenle', 'suppliers');
?>
<div class="page-head">
    <h1 class="page-title">Tedarikçi Düzenle</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/suppliers/view.php?id=' . $id)) ?>">← Detay</a></div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/suppliers/edit.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_supplier-form.php'; ?>
    <div class="form-actions" style="max-width:900px">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/suppliers/view.php?id=' . $id)) ?>">Vazgeç</a>
        <?php if (can('suppliers.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/suppliers/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?>
    </div>
</form>
<?php layout_bottom();
