<?php
declare(strict_types=1);

/** modules/settings/service-messages.php — Servis durum bilgilendirme şablonları + SMS config. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service-messages.php';

auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    service_msg_save_templates([
        'wa'           => $_POST['wa'] ?? '',
        'mail_subject' => $_POST['mail_subject'] ?? '',
        'mail_body'    => $_POST['mail_body'] ?? '',
    ]);
    app_setting_set('service_sms_enabled', isset($_POST['sms_enabled']) ? '1' : '0');
    app_setting_set('service_sms_provider', trim((string) ($_POST['sms_provider'] ?? '')));
    app_setting_set('service_sms_sender', trim((string) ($_POST['sms_sender'] ?? '')));
    log_activity('settings_update', 'settings', null, 'service_messages', 'success', 'Servis mesaj şablonları güncellendi');
    flash('success', 'Şablonlar kaydedildi.');
    http_response_code(303);
    redirect('modules/settings/service-messages.php');
}

$sms = service_sms_config();
layout_top('Servis Mesaj Şablonları', 'settings');
?>
<div class="page-head"><h1 class="page-title">Servis Mesaj Şablonları</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div></div>
<?= render_flashes() ?>

<form method="post" action="<?= e(url('modules/settings/service-messages.php')) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:760px"><div class="card-header"><h2>Şablonlar</h2></div><div class="card-body">
        <p class="field-hint" style="margin-top:0">Değişkenler: <code>{musteri_adi}</code> <code>{servis_kodu}</code> <code>{urun_adi}</code> <code>{durum}</code> <code>{takip_linki}</code> <code>{firma_adi}</code> <code>{telefon}</code>. Otomatik toplu gönderim yoktur; mesaj her kayıt için kullanıcı onayıyla üretilir.</p>
        <div class="form-group"><label for="wa">WhatsApp mesajı</label><textarea id="wa" name="wa" rows="3"><?= e(service_msg_template('wa')) ?></textarea></div>
        <div class="form-group"><label for="mail_subject">E-posta konusu</label><input type="text" id="mail_subject" name="mail_subject" value="<?= e(service_msg_template('mail_subject')) ?>"></div>
        <div class="form-group"><label for="mail_body">E-posta gövdesi</label><textarea id="mail_body" name="mail_body" rows="6"><?= e(service_msg_template('mail_body')) ?></textarea></div>
    </div></div>

    <div class="card" style="max-width:760px"><div class="card-header"><h2>SMS (config hazırlığı)</h2></div><div class="card-body">
        <p class="field-hint" style="margin-top:0">SMS gönderimi için altyapı hazırdır; gerçek gönderim SMS sağlayıcı API'si bağlandığında etkinleşecektir.</p>
        <div class="form-check"><label><input type="checkbox" name="sms_enabled" value="1"<?= $sms['enabled'] ? ' checked' : '' ?>> SMS bilgilendirmeyi etkinleştir (hazır olduğunda)</label></div>
        <div class="form-row">
            <div class="form-group"><label for="sms_provider">SMS sağlayıcı</label><input type="text" id="sms_provider" name="sms_provider" value="<?= e($sms['provider']) ?>" placeholder="NetGSM, İleti Merkezi…"></div>
            <div class="form-group"><label for="sms_sender">Gönderen başlığı</label><input type="text" id="sms_sender" name="sms_sender" value="<?= e($sms['sender']) ?>"></div>
        </div>
    </div></div>

    <div class="form-actions" style="max-width:760px"><button type="submit" class="btn btn-primary">Kaydet</button></div>
</form>
<?php layout_bottom();
