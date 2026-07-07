<?php
declare(strict_types=1);

/** modules/reconciliation/edit.php — Mutabakat düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation.php';
require_once __DIR__ . '/../../includes/customers.php';

auth_boot();
require_permission('reconciliation.edit');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$r  = get_reconciliation($id);
if (!$r) { flash('error', 'Mutabakat bulunamadı.'); redirect('modules/reconciliation/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = recon_fields_from_input($_POST);
    $errors = recon_validate($d);
    if (!$errors) {
        if (update_reconciliation($id, $d, current_user_id())) {
            log_activity('reconciliation_edit', 'reconciliation', $id, null, 'success', 'Mutabakat güncellendi');
            flash('success', 'Mutabakat güncellendi.');
            redirect('modules/reconciliation/view.php?id=' . $id);
        }
        $errors[] = 'Güncelleme başarısız.';
    }
    $r = array_merge($r, $d);
}

$customers = customers_for_select();
layout_top('Mutabakat Düzenle', 'reconciliation');
?>
<div class="page-head"><h1 class="page-title">Mutabakat Düzenle · <?= e((string) $r['recon_no']) ?></h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/reconciliation/view.php?id=' . $id)) ?>">← Detay</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/reconciliation/edit.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_recon-form.php'; ?>
    <div class="form-actions" style="max-width:820px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/reconciliation/view.php?id=' . $id)) ?>">Vazgeç</a>
    <?php if (can('reconciliation.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/reconciliation/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?></div>
</form>
<?php layout_bottom();
