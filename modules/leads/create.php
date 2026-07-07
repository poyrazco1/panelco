<?php
declare(strict_types=1);
/** modules/leads/create.php — Yeni lead. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';
require_once __DIR__ . '/../../includes/personnel.php';
auth_boot();
require_permission('leads.create');
$errors = [];
$l = ['status' => 'new'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $l = lead_fields_from_input($_POST);
    $errors = lead_validate($l);
    if (!$errors) {
        $id = create_lead($l, current_user_id());
        if ($id > 0) { log_activity('lead_create', 'lead', $id, null, 'success', 'Lead eklendi: ' . $l['company_name']); flash('success', 'Lead eklendi.'); redirect('modules/leads/view.php?id=' . $id); }
        $errors[] = 'Lead kaydedilemedi.';
    }
}
$people = get_personnel_options(false);
layout_top('Yeni Lead', 'leads');
?>
<div class="page-head"><h1 class="page-title">Yeni Lead</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>">← Lead Yönetimi</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/leads/create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_lead-form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/leads/index.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
