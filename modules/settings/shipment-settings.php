<?php
declare(strict_types=1);

/** modules/settings/shipment-settings.php — Sevkiyat: yönetici WhatsApp,
 *  mesaj şablonu, rota varsayılanları (başlangıç/bitiş) ve fotoğraf ayarları. */
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
    // Rota varsayılanları + fotoğraf yükleme ayarları
    app_setting_set('shipment_default_start', trim((string) ($_POST['default_start'] ?? '')));
    app_setting_set('shipment_default_end', trim((string) ($_POST['default_end'] ?? '')));
    $maxMb = (int) ($_POST['photo_max_mb'] ?? 8);
    if ($maxMb <= 0 || $maxMb > 32) { $maxMb = 8; }
    app_setting_set('shipment_photo_max_mb', (string) $maxMb);
    // İzin verilen tipleri normalize et (küçük harf, whitelist)
    $allow = ['jpg','jpeg','png','webp','heic','gif'];
    $types = array_values(array_filter(array_map(static fn($t) => strtolower(trim($t)), explode(',', (string) ($_POST['photo_types'] ?? '')))));
    $types = array_values(array_intersect($types, $allow));
    if (!$types) { $types = ['jpg','jpeg','png','webp']; }
    app_setting_set('shipment_photo_types', implode(',', $types));

    log_activity('settings_update', 'settings', null, 'shipment', 'success', 'Sevkiyat ayarları güncellendi');
    flash('success', 'Sevkiyat ayarları kaydedildi.');
    http_response_code(303);
    redirect('modules/settings/shipment-settings.php');
}

$set = ship_route_settings();
layout_top('Sevkiyat Ayarları', 'settings');
?>
<div class="page-head"><h1 class="page-title">Sevkiyat Ayarları</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div></div>
<?= render_flashes() ?>
<form method="post" action="<?= e(url('modules/settings/shipment-settings.php')) ?>">
    <?= csrf_field() ?>

    <div class="card" style="max-width:720px"><div class="card-header"><h2>Rota Varsayılanları</h2></div><div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="default_start">Varsayılan başlangıç noktası</label><input type="text" id="default_start" name="default_start" value="<?= e($set['default_start']) ?>" placeholder="Depo / merkez adresi"></div>
            <div class="form-group"><label for="default_end">Varsayılan bitiş noktası</label><input type="text" id="default_end" name="default_end" value="<?= e($set['default_end']) ?>" placeholder="Bitiş adresi"></div>
        </div>
        <div class="field-hint">Yeni rota oluştururken başlangıç/bitiş alanları bu değerlerle önden dolar.</div>
    </div></div>

    <div class="card" style="max-width:720px"><div class="card-header"><h2>Fotoğraf (Kanıt) Ayarları</h2></div><div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="photo_max_mb">Maksimum dosya boyutu (MB)</label><input type="number" id="photo_max_mb" name="photo_max_mb" min="1" max="32" value="<?= e($set['photo_max_mb']) ?>"></div>
            <div class="form-group"><label for="photo_types">İzin verilen dosya tipleri</label><input type="text" id="photo_types" name="photo_types" value="<?= e($set['photo_types']) ?>" placeholder="jpg,jpeg,png,webp"></div>
        </div>
        <div class="field-hint">Virgülle ayırın. Desteklenen: jpg, jpeg, png, webp, heic, gif. Kanıt fotoğrafları <code>uploads/shipment-proof/</code> altına güvenli adla kaydedilir.</div>
    </div></div>

    <div class="card" style="max-width:720px"><div class="card-header"><h2>Yönetici Bilgilendirme</h2></div><div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="manager_name">Sevkiyat yönetici adı</label><input type="text" id="manager_name" name="manager_name" value="<?= e($set['manager_name']) ?>"></div>
            <div class="form-group"><label for="manager_whatsapp">Yönetici WhatsApp</label><input type="text" id="manager_whatsapp" name="manager_whatsapp" value="<?= e($set['manager_whatsapp']) ?>" inputmode="tel"></div>
        </div>
        <div class="form-group"><label for="wa_template">WhatsApp mesaj şablonu (eski sevkiyat kayıtları)</label><textarea id="wa_template" name="wa_template" rows="6"><?= e($set['wa_template']) ?></textarea>
            <div class="field-hint">Değişkenler: <code>{sevkiyat_no}</code> <code>{sevkiyat_tipi}</code> <code>{firma_adi}</code> <code>{adres}</code> <code>{sevkiyatci_adi}</code> <code>{durum}</code> <code>{tarih}</code> <code>{not}</code> <code>{panel_linki}</code>. Otomatik gönderim yoktur; tıklanabilir buton üretilir.</div>
        </div>
    </div></div>

    <div class="form-actions" style="max-width:720px"><button type="submit" class="btn btn-primary">Kaydet</button></div>
</form>
<?php layout_bottom();
