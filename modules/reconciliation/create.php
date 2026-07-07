<?php
declare(strict_types=1);

/** modules/reconciliation/create.php — Yeni mutabakat. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation.php';
require_once __DIR__ . '/../../includes/customers.php';

auth_boot();
require_permission('reconciliation.create');

$errors = [];
$r = ['currency' => 'TRY', 'agreement' => 'pending', 'recon_date' => date('Y-m-d')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $r = recon_fields_from_input($_POST);
    $errors = recon_validate($r);
    if (!$errors) {
        $id = create_reconciliation($r, current_user_id());
        if ($id > 0) {
            log_activity('reconciliation_create', 'reconciliation', $id, null, 'success', 'Mutabakat oluşturuldu');
            flash('success', 'Mutabakat oluşturuldu.');
            redirect('modules/reconciliation/view.php?id=' . $id);
        }
        $errors[] = 'Mutabakat kaydedilemedi.';
    }
}

$customers = customers_for_select();
layout_top('Yeni Mutabakat', 'reconciliation');
?>
<div class="page-head"><h1 class="page-title">Yeni Mutabakat</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/reconciliation/index.php')) ?>">← Mutabakat</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/reconciliation/create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_recon-form.php'; ?>
    <div class="form-actions" style="max-width:820px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/reconciliation/index.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
