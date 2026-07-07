<?php
declare(strict_types=1);

/** modules/settings/shipment-settings.php — Sevkiyat yönetici WhatsApp + mesaj şablonu. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    shipment_save_settings([
        'manager_name'     => $_POST['manager_name'] ?? '',
        'manager_whatsapp' => $_POST['manager_whatsapp'] ?? '',
        'wa_template'      => $_POST['wa_template'] ?? '',
    ]);
    log_activity('settings_update', 'settings', null, 'shipment', 'success', 'Sevkiyat ayarları güncellendi');
    flash('success', 'Sevkiyat ayarları kaydedildi.');
    http_response_code(303);
    redirect('modules/settings/shipment-settings.php');
}

$set = shipment_settings();
layout_top('Sevkiyat Ayarları', 'settings');
?>
<div class="page-head"><h1 class="page-title">Sevkiyat Ayarları</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div></div>
<?= render_flashes() ?>
<form method="post" action="<?= e(url('modules/settings/shipment-settings.php')) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:720px"><div class="card-header"><h2>Yönetici Bilgilendirme</h2></div><div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="manager_name">Sevkiyat yönetici adı</label><input type="text" id="manager_name" name="manager_name" value="<?= e($set['manager_name']) ?>"></div>
            <div class="form-group"><label for="manager_whatsapp">Yönetici WhatsApp</label><input type="text" id="manager_whatsapp" name="manager_whatsapp" value="<?= e($set['manager_whatsapp']) ?>" inputmode="tel"></div>
        </div>
        <div class="form-group"><label for="wa_template">WhatsApp mesaj şablonu</label><textarea id="wa_template" name="wa_template" rows="8"><?= e($set['wa_template']) ?></textarea>
            <div class="field-hint">Değişkenler: <code>{sevkiyat_no}</code> <code>{sevkiyat_tipi}</code> <code>{firma_adi}</code> <code>{adres}</code> <code>{sevkiyatci_adi}</code> <code>{durum}</code> <code>{tarih}</code> <code>{not}</code> <code>{panel_linki}</code>. Otomatik gönderim yoktur; sevkiyat detayında tıklanabilir buton üretilir.</div>
        </div>
    </div></div>
    <div class="form-actions" style="max-width:720px"><button type="submit" class="btn btn-primary">Kaydet</button></div>
</form>
<?php layout_bottom();
