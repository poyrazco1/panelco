<?php
declare(strict_types=1);

/**
 * modules/forms/submit.php — İç kullanıcıların form doldurma ekranı (login zorunlu).
 * submitted_by_user_id doldurulur; onay gerekiyorsa Onay Bekleyenler'e düşer.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';

auth_boot();
require_permission('forms.submit');

$id = (int) ($_GET['id'] ?? ($_POST['template_id'] ?? 0));
$template = form_center_template_find($id);
if (!$template || (int) $template['is_active'] !== 1 || !in_array((string) $template['form_type'], ['internal', 'both'], true)) {
    flash('error', 'Form bulunamadı veya iç kullanıma kapalı.');
    redirect('modules/forms/index.php');
}

$fields = form_center_fields($template);
$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = form_center_validate_submission($template, $_POST);
    $errors = $res['errors'];
    $values = $res['data'];
    if (!$errors) {
        $sid = form_center_create_submission($template, $res['data'], ['submitted_by_user_id' => current_user_id()]);
        if ($sid > 0) {
            form_center_process_uploads($sid, $template, current_user_id());
            log_activity('form_submit', 'form_submission', $sid, null, 'success', 'Form gönderildi: ' . $template['form_name']);
            flash('success', 'Formunuz gönderildi.' . (!empty($template['requires_approval']) ? ' Onay bekleniyor.' : ''));
            redirect('modules/forms/submission-view.php?id=' . $sid);
        }
        $errors['_'] = 'Form kaydedilemedi. Lütfen tekrar deneyin.';
    }
}

layout_top($template['form_name'], 'forms');
?>
<div class="page-head">
    <h1 class="page-title"><?= e((string) $template['form_name']) ?></h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/forms/index.php')) ?>">← Form Merkezi</a></div>
</div>
<?= render_flashes() ?>
<?php if (!empty($errors['_'])): ?><div class="alert alert-error"><?= e($errors['_']) ?></div><?php endif; ?>
<?php if (!empty($template['requires_approval'])): ?><div class="alert alert-info">Bu form onay gerektirir. Gönderdikten sonra yetkili onayına düşer.</div><?php endif; ?>

<form method="post" action="<?= e(url('modules/forms/submit.php')) ?>" enctype="multipart/form-data" class="form-fill" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
    <div class="card" style="max-width:720px"><div class="card-body">
        <?php if (!empty($template['description'])): ?><p class="muted"><?= e((string) $template['description']) ?></p><?php endif; ?>
        <?php form_center_render_fields($fields, $values, $errors); ?>
    </div></div>
    <div class="form-actions" style="max-width:720px">
        <button type="submit" class="btn btn-primary"><?= icon('send') ?>Gönder</button>
        <a class="btn" href="<?= e(url('modules/forms/index.php')) ?>">Vazgeç</a>
    </div>
</form>
<?php layout_bottom();
