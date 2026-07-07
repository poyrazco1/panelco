<?php
declare(strict_types=1);
/** modules/help/create.php — Yeni yardım konusu. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/help.php';
auth_boot();
require_permission('help.create');
$errors = [];
$a = ['is_active' => 1, 'category' => (string) ($_GET['category'] ?? 'dashboard')];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = help_fields_from_input($_POST);
    $errors = help_validate($a);
    if (!$errors) {
        $id = create_help_article($a, current_user_id());
        if ($id > 0) { log_activity('help_create', 'help', $id, null, 'success', 'Yardım konusu eklendi: ' . $a['title']); flash('success', 'Konu eklendi.'); redirect('modules/help/view.php?id=' . $id); }
        $errors[] = 'Konu kaydedilemedi.';
    }
}
layout_top('Yeni Yardım Konusu', 'help');
?>
<div class="page-head"><h1 class="page-title">Yeni Yardım Konusu</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/help/index.php')) ?>">← Yardım Merkezi</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/help/create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_help-form.php'; ?>
    <div class="form-actions" style="max-width:820px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/help/index.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
