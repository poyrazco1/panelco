<?php
declare(strict_types=1);

/** modules/commissions/edit.php — Prim düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/commissions.php';

auth_boot();
require_permission('commissions.edit');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$c  = get_commission($id);
if (!$c) { flash('error', 'Prim kaydı bulunamadı.'); redirect('modules/commissions/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = commission_fields_from_input($_POST);
    $errors = commission_validate($d);
    if (!$errors) {
        if (update_commission($id, $d, current_user_id())) {
            log_activity('commission_edit', 'commission', $id, null, 'success', 'Prim güncellendi');
            flash('success', 'Prim güncellendi.');
            redirect('modules/commissions/view.php?id=' . $id);
        }
        $errors[] = 'Güncelleme başarısız.';
    }
    $c = array_merge($c, $d);
}
$people = get_personnel_options(false);
layout_top('Prim Düzenle', 'commissions');
?>
<div class="page-head"><h1 class="page-title">Prim Düzenle</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/commissions/view.php?id=' . $id)) ?>">← Detay</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/commissions/edit.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_commission-form.php'; ?>
    <div class="form-actions" style="max-width:820px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/commissions/view.php?id=' . $id)) ?>">Vazgeç</a>
    <?php if (can('commissions.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/commissions/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?></div>
</form>
<?php layout_bottom();
