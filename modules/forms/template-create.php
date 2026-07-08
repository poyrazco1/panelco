<?php
declare(strict_types=1);
/** modules/forms/template-create.php — Yeni form şablonu. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.templates.manage');

$tpl = ['form_type' => 'internal', 'is_active' => 1];
$fields = [];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tpl = $_POST;
    $fields = form_center_fields_from_input($_POST);
    if (trim((string) ($_POST['form_name'] ?? '')) === '') { $errors[] = 'Form adı zorunludur.'; }
    if (!$fields) { $errors[] = 'En az bir alan tanımlayın.'; }
    if (!$errors) {
        $id = form_center_save_template($_POST, $fields, null, current_user_id());
        if ($id > 0) {
            log_activity('form_template_create', 'form_template', $id, null, 'success', 'Form şablonu eklendi: ' . ($_POST['form_name'] ?? ''));
            flash('success', 'Form şablonu oluşturuldu.');
            redirect('modules/forms/templates.php');
        }
        $errors[] = 'Şablon kaydedilemedi.';
    }
}
$categories = form_center_categories();
layout_top('Yeni Form Şablonu', 'settings');
?>
<div class="page-head"><h1 class="page-title">Yeni Form Şablonu</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/forms/templates.php')) ?>">← Şablonlar</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/forms/template-create.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_template-form.php'; ?>
    <div class="form-actions" style="max-width:860px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/forms/templates.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
