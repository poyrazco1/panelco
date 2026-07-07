<?php
declare(strict_types=1);
/** modules/help/edit.php — Yardım konusu düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/help.php';
auth_boot();
require_permission('help.edit');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$a = get_help_article($id);
if (!$a) { flash('error', 'Konu bulunamadı.'); redirect('modules/help/index.php'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = help_fields_from_input($_POST);
    $errors = help_validate($d);
    if (!$errors) {
        if (update_help_article($id, $d, current_user_id())) { log_activity('help_edit', 'help', $id, null, 'success', 'Yardım konusu güncellendi'); flash('success', 'Konu güncellendi.'); redirect('modules/help/view.php?id=' . $id); }
        $errors[] = 'Güncelleme başarısız.';
    }
    $a = array_merge($a, $d);
}
layout_top('Yardım Konusu Düzenle', 'help');
?>
<div class="page-head"><h1 class="page-title">Yardım Konusu Düzenle</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/help/view.php?id=' . $id)) ?>">← Detay</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/help/edit.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_help-form.php'; ?>
    <div class="form-actions" style="max-width:820px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/help/view.php?id=' . $id)) ?>">Vazgeç</a>
    <?php if (can('help.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/help/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?></div>
</form>
<?php layout_bottom();
