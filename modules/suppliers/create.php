<?php
declare(strict_types=1);

/** modules/suppliers/create.php — Yeni tedarikçi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/suppliers.php';

auth_boot();
require_permission('suppliers.create');

$errors = [];
$s = ['is_active' => 1, 'currency' => 'TRY'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $s = supplier_fields_from_input($_POST);
    $errors = supplier_validate($s);
    if (!$errors) {
        $id = create_supplier($s, current_user_id());
        if ($id > 0) {
            log_activity('supplier_create', 'supplier', $id, null, 'success', 'Tedarikçi eklendi: ' . $s['company_name']);
            flash('success', 'Tedarikçi eklendi.');
            redirect('modules/suppliers/view.php?id=' . $id);
        }
        $errors[] = 'Tedarikçi kaydedilemedi.';
    }
}

layout_top('Yeni Tedarikçi', 'suppliers');
?>
<div class="page-head">
    <h1 class="page-title">Yeni Tedarikçi</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/suppliers/index.php')) ?>">← Tedarikçiler</a></div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/suppliers/create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_supplier-form.php'; ?>
    <div class="form-actions" style="max-width:900px">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/suppliers/index.php')) ?>">Vazgeç</a>
    </div>
</form>
<?php layout_bottom();
