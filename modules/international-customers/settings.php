<?php
declare(strict_types=1);
/** modules/international-customers/settings.php — Dış ticaret gönderim ayarları. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    app_setting_set('intl_sender_name', trim((string) ($_POST['intl_sender_name'] ?? '')));
    app_setting_set('intl_sender_whatsapp', trim((string) ($_POST['intl_sender_whatsapp'] ?? '')));
    $limit = max(0, (int) ($_POST['intl_daily_mail_limit'] ?? 200));
    app_setting_set('intl_daily_mail_limit', (string) $limit);
    log_activity('settings_update', 'settings', null, 'intl', 'success', 'Dış ticaret gönderim ayarları güncellendi');
    flash('success', 'Ayarlar kaydedildi.');
    http_response_code(303);
    redirect('modules/international-customers/settings.php');
}
$name = (string) app_setting_get('intl_sender_name', '');
$wa   = (string) app_setting_get('intl_sender_whatsapp', '');
$limit = (string) app_setting_get('intl_daily_mail_limit', '200');
layout_top('Dış Ticaret Gönderim Ayarları', 'settings');
?>
<div class="page-head"><h1 class="page-title">Dış Ticaret Gönderim Ayarları</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div></div>
<?= render_flashes() ?>
<form method="post" action="<?= e(url('modules/international-customers/settings.php')) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:640px"><div class="card-body">
        <div class="form-group"><label for="intl_sender_name">Gönderen Adı</label><input type="text" id="intl_sender_name" name="intl_sender_name" value="<?= e($name) ?>" placeholder="Mesaj imzasında {sender_name}"></div>
        <div class="form-group"><label for="intl_sender_whatsapp">Gönderen WhatsApp</label><input type="text" id="intl_sender_whatsapp" name="intl_sender_whatsapp" value="<?= e($wa) ?>" placeholder="+90…  ({sender_whatsapp})"></div>
        <div class="form-group"><label for="intl_daily_mail_limit">Günlük Mail Gönderim Limiti</label><input type="number" id="intl_daily_mail_limit" name="intl_daily_mail_limit" value="<?= e($limit) ?>" min="0" max="10000">
            <div class="field-hint">Günlük toplam gönderilen mail bu limiti aşarsa yeni mail gönderimi engellenir. Spam koruması içindir.</div></div>
    </div></div>
    <div class="form-actions" style="max-width:640px"><button type="submit" class="btn btn-primary">Kaydet</button></div>
</form>
<?php layout_bottom();
