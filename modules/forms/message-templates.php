<?php
declare(strict_types=1);
/** modules/forms/message-templates.php — Form mesaj şablonları (WhatsApp/e-posta). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.templates.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    form_center_save_message_templates([
        'received' => $_POST['received'] ?? '',
        'approved' => $_POST['approved'] ?? '',
        'rejected' => $_POST['rejected'] ?? '',
    ]);
    log_activity('settings_update', 'settings', null, 'form_msg', 'success', 'Form mesaj şablonları güncellendi');
    flash('success', 'Mesaj şablonları kaydedildi.');
    http_response_code(303);
    redirect('modules/forms/message-templates.php');
}
$m = form_center_message_templates();
layout_top('Form Mesaj Şablonları', 'settings');
?>
<div class="page-head"><h1 class="page-title">Form Mesaj Şablonları</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div></div>
<?= render_flashes() ?>
<form method="post" action="<?= e(url('modules/forms/message-templates.php')) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:760px"><div class="card-body">
        <div class="form-group"><label for="received">Form Alındı</label><textarea id="received" name="received" rows="3"><?= e($m['received']) ?></textarea></div>
        <div class="form-group"><label for="approved">Onaylandı</label><textarea id="approved" name="approved" rows="3"><?= e($m['approved']) ?></textarea></div>
        <div class="form-group"><label for="rejected">Reddedildi</label><textarea id="rejected" name="rejected" rows="3"><?= e($m['rejected']) ?></textarea></div>
        <div class="field-hint">Değişkenler: <code>{ad_soyad}</code> <code>{firma}</code> <code>{form_adi}</code> <code>{talep_no}</code> <code>{sebep}</code>. Otomatik gönderim yoktur; kayıt detayında tıklanabilir WhatsApp linki üretilir.</div>
    </div></div>
    <div class="form-actions" style="max-width:760px"><button type="submit" class="btn btn-primary">Kaydet</button></div>
</form>
<?php layout_bottom();
