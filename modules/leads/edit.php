<?php
declare(strict_types=1);
/** modules/leads/edit.php — Lead düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';
require_once __DIR__ . '/../../includes/personnel.php';
auth_boot();
require_permission('leads.edit');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$l = get_lead($id);
if (!$l) { flash('error', 'Lead bulunamadı.'); redirect('modules/leads/index.php'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = lead_fields_from_input($_POST);
    $errors = lead_validate($d);
    if (!$errors) {
        if (update_lead($id, $d, current_user_id())) { log_activity('lead_edit', 'lead', $id, null, 'success', 'Lead güncellendi'); flash('success', 'Lead güncellendi.'); redirect('modules/leads/view.php?id=' . $id); }
        $errors[] = 'Güncelleme başarısız.';
    }
    $l = array_merge($l, $d);
}
$people = get_personnel_options(false);
layout_top('Lead Düzenle', 'leads');
?>
<div class="page-head"><h1 class="page-title">Lead Düzenle</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/leads/view.php?id=' . $id)) ?>">← Detay</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/leads/edit.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_lead-form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/leads/view.php?id=' . $id)) ?>">Vazgeç</a>
    <?php if (can('leads.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/leads/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?></div>
</form>
<?php layout_bottom();
