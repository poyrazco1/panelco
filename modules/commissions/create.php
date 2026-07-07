<?php
declare(strict_types=1);

/** modules/commissions/create.php — Yeni prim. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/commissions.php';

auth_boot();
require_permission('commissions.create');

$errors = [];
$c = ['currency' => 'TRY'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $c = commission_fields_from_input($_POST);
    $errors = commission_validate($c);
    if (!$errors) {
        $id = create_commission($c, current_user_id());
        if ($id > 0) {
            log_activity('commission_create', 'commission', $id, null, 'success', 'Prim eklendi');
            flash('success', 'Prim kaydı eklendi.');
            redirect('modules/commissions/view.php?id=' . $id);
        }
        $errors[] = 'Prim kaydedilemedi.';
    }
}
$people = get_personnel_options(false);
layout_top('Yeni Prim', 'commissions');
?>
<div class="page-head"><h1 class="page-title">Yeni Prim</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/commissions/index.php')) ?>">← Primler</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/commissions/create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_commission-form.php'; ?>
    <div class="form-actions" style="max-width:820px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/commissions/index.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
