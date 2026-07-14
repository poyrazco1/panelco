<?php
declare(strict_types=1);

/**
 * modules/settings/lead-scan-settings.php
 * Google Places (Lead Tarama) ayarları. API anahtarı ŞİFRELİ saklanır; yalnızca
 * Süper Admin görüntüleyip değiştirebilir. Tam anahtar ekranda GÖSTERİLMEZ.
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/google_places.php';

auth_boot();
require_permission('settings');

$isSuper = gp_is_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Anahtar değişikliği YALNIZCA Süper Admin'e izinlidir (backend denetimi).
    $newKey = null;
    if ($isSuper) {
        $posted = (string) ($_POST['api_key'] ?? '');
        // Boş bırakılırsa mevcut anahtar korunur; maske metni girildiyse yok say.
        if (trim($posted) !== '' && strpos($posted, '•') === false) {
            $newKey = $posted;
        }
    }

    $res = gp_settings_save([
        'is_active'          => $_POST['is_active'] ?? 0,
        'default_country'    => $_POST['default_country'] ?? '',
        'default_city'       => $_POST['default_city'] ?? '',
        'default_language'   => $_POST['default_language'] ?? 'tr',
        'default_radius'     => $_POST['default_radius'] ?? 5000,
        'max_results'        => $_POST['max_results'] ?? 60,
        'daily_query_limit'  => $_POST['daily_query_limit'] ?? 1000,
        'monthly_est_limit'  => $_POST['monthly_est_limit'] ?? 20000,
        'block_duplicates'   => $_POST['block_duplicates'] ?? 0,
        'include_no_phone'   => $_POST['include_no_phone'] ?? 0,
        'include_no_website' => $_POST['include_no_website'] ?? 0,
        'auto_details'       => $_POST['auto_details'] ?? 0,
    ], $newKey, current_user_id());

    if ($res['ok']) {
        // Anahtar değeri ASLA loglanmaz — yalnızca değişip değişmediği bilgisi.
        log_activity('settings_update', 'settings', null, 'lead_scan', 'success',
            'Lead Tarama (Google Places) ayarları güncellendi' . ($newKey !== null ? ' (anahtar değişti)' : ''));
        flash('success', 'Lead Tarama ayarları kaydedildi.');
    } else {
        flash('error', $res['error']);
    }
    http_response_code(303);
    redirect('modules/settings/lead-scan-settings.php');
}

$s = gp_settings();
$hasKey = gp_has_api_key();
$maskedHint = ($isSuper && $hasKey) ? gp_api_key_masked_hint() : '';
$keyDecodeOk = !$hasKey || gp_api_key() !== null;

layout_top('Lead Tarama Ayarları', 'settings');
?>
<div class="page-head">
    <h1 class="page-title">Lead Tarama Ayarları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div>
</div>
<?= render_flashes() ?>

<?php if (!vault_is_configured()): ?>
    <div class="alert alert-danger" style="max-width:820px">Şifreleme yapılandırılmamış (VAULT_KEY). API anahtarı güvenle saklanamaz; önce <code>config.php</code> içinde anahtar tanımlayın.</div>
<?php endif; ?>
<?php if ($hasKey && !$keyDecodeOk): ?>
    <div class="alert alert-danger" style="max-width:820px">Kayıtlı API anahtarı mevcut şifreleme anahtarıyla çözülemiyor (VAULT_KEY değişmiş olabilir). Süper Admin yeni anahtarı tekrar girmeli.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('modules/settings/lead-scan-settings.php')) ?>">
    <?= csrf_field() ?>

    <div class="card" style="max-width:820px"><div class="card-header"><h2>Google API Anahtarı</h2></div><div class="card-body">
        <?php if ($isSuper): ?>
            <div class="form-group">
                <label for="api_key">Google Places API Anahtarı</label>
                <input type="password" id="api_key" name="api_key" autocomplete="off" spellcheck="false"
                       placeholder="<?= $hasKey ? 'Kayıtlı — değiştirmek için yeni anahtar girin' : 'Anahtarı yapıştırın' ?>"
                       value="">
                <div class="field-hint">
                    <?php if ($hasKey): ?>
                        Kayıtlı anahtar: <code><?= e($maskedHint) ?></code> · Boş bırakırsanız mevcut anahtar korunur. Yeni değer girerseniz eskisinin yerini alır.
                    <?php else: ?>
                        Anahtar <strong>şifreli</strong> saklanır; kayıttan sonra tam hâli bir daha gösterilmez. Yalnızca Süper Admin görüntüley/değiştirebilir.
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <p class="muted" style="margin:0">
                API anahtarı <strong><?= $hasKey ? 'tanımlı' : 'tanımsız' ?></strong>. Güvenlik gereği anahtarı yalnızca <strong>Süper Admin</strong> görüntüleyip değiştirebilir.
            </p>
        <?php endif; ?>
    </div></div>

    <div class="card" style="max-width:820px"><div class="card-header"><h2>Genel</h2></div><div class="card-body">
        <div class="form-group">
            <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= !empty($s['is_active']) ? 'checked' : '' ?>> Google Places taramasını etkinleştir</label>
            <div class="field-hint">Kapalıyken tarama başlatılamaz. Anahtar tanımlı değilse etkinleştirilemez.</div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="default_country">Varsayılan ülke</label><input type="text" id="default_country" name="default_country" value="<?= e((string) $s['default_country']) ?>"></div>
            <div class="form-group"><label for="default_city">Varsayılan şehir</label><input type="text" id="default_city" name="default_city" value="<?= e((string) $s['default_city']) ?>" placeholder="Örn. İzmir"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="default_language">Dil kodu</label><input type="text" id="default_language" name="default_language" value="<?= e((string) $s['default_language']) ?>" placeholder="tr" style="max-width:120px"></div>
            <div class="form-group"><label for="default_radius">Varsayılan yarıçap (m)</label><input type="number" id="default_radius" name="default_radius" min="50" max="50000" value="<?= e((string) $s['default_radius']) ?>"></div>
            <div class="form-group"><label for="max_results">Maksimum sonuç</label><input type="number" id="max_results" name="max_results" min="1" max="200" value="<?= e((string) $s['max_results']) ?>"></div>
        </div>
    </div></div>

    <div class="card" style="max-width:820px"><div class="card-header"><h2>Kota & Maliyet</h2></div><div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="daily_query_limit">Günlük çağrı limiti</label><input type="number" id="daily_query_limit" name="daily_query_limit" min="0" value="<?= e((string) $s['daily_query_limit']) ?>">
                <div class="field-hint">0 = sınırsız. Bugün: <strong><?= (int) gp_usage_today() ?></strong> çağrı.</div></div>
            <div class="form-group"><label for="monthly_est_limit">Aylık çağrı limiti</label><input type="number" id="monthly_est_limit" name="monthly_est_limit" min="0" value="<?= e((string) $s['monthly_est_limit']) ?>">
                <div class="field-hint">0 = sınırsız. Bu ay: <strong><?= (int) gp_usage_month() ?></strong> çağrı.</div></div>
        </div>
    </div></div>

    <div class="card" style="max-width:820px"><div class="card-header"><h2>Tarama Davranışı</h2></div><div class="card-body">
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="block_duplicates" value="1" <?= !empty($s['block_duplicates']) ? 'checked' : '' ?>> Kopya kayıtları engelle (Place ID / telefon / alan adı / ad+adres)</label></div>
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="include_no_phone" value="1" <?= !empty($s['include_no_phone']) ? 'checked' : '' ?>> Telefonu olmayan işletmeleri de dahil et</label></div>
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="include_no_website" value="1" <?= !empty($s['include_no_website']) ? 'checked' : '' ?>> Web sitesi olmayan işletmeleri de dahil et</label></div>
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="auto_details" value="1" <?= !empty($s['auto_details']) ? 'checked' : '' ?>> Telefon/çalışma saati için otomatik ayrıntı çek (Place Details)</label>
            <div class="field-hint">Ayrıntı çağrısı ek maliyet oluşturur; kapatırsanız yalnızca arama sonucu alanları kullanılır.</div></div>
    </div></div>

    <?php if (!empty($s['last_error'])): ?>
        <div class="alert alert-warning" style="max-width:820px">Son hata: <?= e((string) $s['last_error']) ?></div>
    <?php endif; ?>

    <div class="form-actions" style="max-width:820px"><button type="submit" class="btn btn-primary">Kaydet</button></div>
</form>
<?php layout_bottom();
