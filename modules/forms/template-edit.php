<?php
declare(strict_types=1);
/** modules/forms/template-edit.php — Form şablonu düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.templates.manage');

$id = (int) ($_GET['id'] ?? ($_POST['id'] ?? 0));
$tpl = form_center_template_find($id);
if (!$tpl) { flash('error', 'Şablon bulunamadı.'); redirect('modules/forms/templates.php'); }
$fields = form_center_fields($tpl);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tpl = array_merge($tpl, $_POST);
    $fields = form_center_fields_from_input($_POST);
    if (trim((string) ($_POST['form_name'] ?? '')) === '') { $errors[] = 'Form adı zorunludur.'; }
    if (!$fields) { $errors[] = 'En az bir alan tanımlayın.'; }
    if (!$errors) {
        form_center_save_template($_POST, $fields, $id, current_user_id());
        log_activity('form_template_edit', 'form_template', $id, null, 'success', 'Form şablonu güncellendi');
        flash('success', 'Form şablonu güncellendi.');
        redirect('modules/forms/templates.php');
    }
}
$categories = form_center_categories();
layout_top('Form Şablonu Düzenle', 'settings');
?>
<div class="page-head"><h1 class="page-title">Form Şablonu Düzenle</h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/forms/templates.php')) ?>">← Şablonlar</a>
    <form method="post" action="<?= e(url('modules/forms/template-delete.php')) ?>" style="display:inline" onsubmit="return confirm('Şablon pasife alınsın mı? (Kayıtlar korunur)')">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <button type="submit" class="btn btn-sm btn-ghost"><?= icon('trash-2') ?>Sil</button>
    </form>
</div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/forms/template-edit.php')) ?>" novalidate>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_template-form.php'; ?>
    <div class="form-actions" style="max-width:860px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/forms/templates.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
