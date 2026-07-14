<?php
declare(strict_types=1);

/**
 * modules/settings/company.php
 * Şirket Bilgileri / İletişim / Logo & Favicon / Mail gönderen ayarları (app_settings).
 * PRG + CSRF. Logo ve favicon GERÇEK dosya upload ile yüklenir (tip/boyut kontrollü).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php'; // service_company_info / service_save_company_info
require_once __DIR__ . '/../../includes/leave.php';   // app_setting_get / app_setting_set

auth_boot();
require_permission('settings');

/* app_settings anahtarları (metin alanları) */
$textKeys = [
    'company_name', 'company_legal_name', 'company_tax_office', 'company_tax_no',
    'company_mersis', 'company_trade_registry', 'company_authorized_name',
    'company_address', 'company_phone', 'company_whatsapp', 'company_email',
    'company_website', 'default_currency', 'default_vat', 'mail_from_name', 'mail_from_email',
    'landing_title', 'landing_subtitle', 'landing_cta',
];

$get = static function (string $k, string $def = ''): string {
    $v = app_setting_get($k, null);
    return $v === null ? $def : (string) $v;
};

$errors = [];

/* ---- Güvenli dosya upload yardımcısı ---- */
$handleUpload = static function (string $field, string $basename, array $allowed, int $maxBytes) use (&$errors): ?string {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // dosya seçilmedi → mevcut korunur
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Dosya yüklenemedi (' . e($field) . ').'; return null; }
    if ($f['size'] <= 0 || $f['size'] > $maxBytes) { $errors[] = 'Dosya boyutu çok büyük (' . e($field) . '). En fazla ' . round($maxBytes / 1024) . ' KB.'; return null; }

    $ext = strtolower((string) pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) { $errors[] = 'İzin verilmeyen dosya türü (' . e($field) . '). İzinli: ' . implode(', ', array_keys($allowed)) . '.'; return null; }

    // MIME doğrulama (SVG hariç finfo; SVG için içerik-tabanlı güvenlik kontrolü)
    $realMime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = (string) finfo_file($fi, (string) $f['tmp_name']);
        finfo_close($fi);
    }
    if ($ext === 'svg') {
        $head = (string) @file_get_contents((string) $f['tmp_name'], false, null, 0, 8192);
        if (stripos($head, '<svg') === false || stripos($head, '<script') !== false || stripos($head, 'onload') !== false) {
            $errors[] = 'Geçersiz veya güvensiz SVG dosyası (' . e($field) . ').'; return null;
        }
    } elseif ($realMime !== '' && !in_array($realMime, $allowed[$ext], true)) {
        $errors[] = 'Dosya içeriği uzantıyla uyuşmuyor (' . e($field) . ').'; return null;
    }

    $destDir = APP_ROOT . '/uploads/company';
    if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }

    // Aynı isimde eski uzantıları temizle (logo.png, logo.svg vb. tek kalsın)
    foreach (array_keys($allowed) as $oldExt) {
        $old = $destDir . '/' . $basename . '.' . $oldExt;
        if (is_file($old)) { @unlink($old); }
    }

    $rel = 'uploads/company/' . $basename . '.' . $ext;
    if (!move_uploaded_file((string) $f['tmp_name'], APP_ROOT . '/' . $rel)) {
        $errors[] = 'Dosya kaydedilemedi (' . e($field) . ').'; return null;
    }
    return $rel;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = trim((string) ($_POST['company_email'] ?? ''));
    if ($email !== '' && !is_valid_email($email)) { $errors[] = 'Geçerli bir firma e-postası girin.'; }
    $mailFrom = trim((string) ($_POST['mail_from_email'] ?? ''));
    if ($mailFrom !== '' && !is_valid_email($mailFrom)) { $errors[] = 'Geçerli bir mail gönderen adresi girin.'; }
    $vat = trim((string) ($_POST['default_vat'] ?? ''));
    if ($vat !== '' && !is_numeric(str_replace(',', '.', $vat))) { $errors[] = 'KDV oranı sayısal olmalıdır.'; }

    // Dosya yüklemeleri (hata varsa $errors dolar, kayıt yapılmaz)
    $imgTypes = ['png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp'], 'svg' => ['image/svg+xml']];
    $logoRel      = $handleUpload('company_logo_file',       'logo',       $imgTypes, 2 * 1024 * 1024);
    $logoPrintRel = $handleUpload('company_logo_print_file', 'logo-print', $imgTypes, 2 * 1024 * 1024);
    $logoDarkRel  = $handleUpload('company_logo_dark_file',  'logo-dark',  $imgTypes, 2 * 1024 * 1024);
    $logoLightRel = $handleUpload('company_logo_light_file', 'logo-light', $imgTypes, 2 * 1024 * 1024);
    $kaseRel      = $handleUpload('company_kase_file',       'kase',       $imgTypes, 2 * 1024 * 1024);
    $signRel      = $handleUpload('company_signature_file',  'signature',  $imgTypes, 2 * 1024 * 1024);
    $favRel = $handleUpload('company_favicon_file', 'favicon',
        ['ico' => ['image/x-icon', 'image/vnd.microsoft.icon'], 'png' => ['image/png'], 'svg' => ['image/svg+xml']],
        512 * 1024);

    if (!$errors) {
        try {
            foreach ($textKeys as $k) {
                app_setting_set($k, trim((string) ($_POST[$k] ?? '')));
            }
            // Geriye uyum: service_company_info() company_tax alanını okur.
            $taxCombined = trim(trim((string) ($_POST['company_tax_office'] ?? '')) . ' / ' . trim((string) ($_POST['company_tax_no'] ?? '')), ' /');
            app_setting_set('company_tax', $taxCombined);

            if ($logoRel !== null)      { app_setting_set('company_logo', $logoRel); }
            if ($logoPrintRel !== null) { app_setting_set('company_logo_print', $logoPrintRel); }
            if ($logoDarkRel !== null)  { app_setting_set('company_logo_dark', $logoDarkRel); }
            if ($logoLightRel !== null) { app_setting_set('company_logo_light', $logoLightRel); }
            if ($kaseRel !== null)      { app_setting_set('company_kase', $kaseRel); }
            if ($signRel !== null)      { app_setting_set('company_signature', $signRel); }
            if ($favRel !== null)       { app_setting_set('company_favicon', $favRel); }

            log_activity('settings_update', 'settings', null, 'company', 'success', 'Şirket bilgileri güncellendi');
            flash('success', 'Şirket bilgileri kaydedildi.');
            http_response_code(303);
            redirect('modules/settings/company.php');
        } catch (Throwable $e) {
            $errors[] = 'Kaydetme sırasında bir sorun oluştu.';
            log_error('company save: ' . $e->getMessage());
        }
    }
}

$logo = $get('company_logo');
$fav  = $get('company_favicon');

/* Kurumsal Kimlik: yüklü görsel yollarını (varsa) önizleme için oku. */
$identityFiles = [
    'company_logo'       => ['label' => 'Ana logo',        'field' => 'company_logo_file',       'hint' => 'Panelde ve varsayılan olarak belgelerde kullanılır.'],
    'company_logo_print' => ['label' => 'Yazdırma logosu', 'field' => 'company_logo_print_file', 'hint' => 'Belge çıktılarında önceliklidir. Boşsa ana logo kullanılır.'],
    'company_logo_dark'  => ['label' => 'Koyu zemin logo', 'field' => 'company_logo_dark_file',  'hint' => 'Koyu arka planlı ekranlar için (ör. giriş sayfası).'],
    'company_logo_light' => ['label' => 'Açık zemin logo', 'field' => 'company_logo_light_file', 'hint' => 'Açık arka planlar için.'],
    'company_kase'       => ['label' => 'Kaşe görseli',    'field' => 'company_kase_file',       'hint' => 'Belge kaşe/imza alanında görünür (PNG şeffaf önerilir).'],
    'company_signature'  => ['label' => 'İmza görseli',    'field' => 'company_signature_file',  'hint' => 'Yetkili imzası; belge çıktısında kaşenin yanında görünür.'],
];

layout_top('Şirket Bilgileri', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Şirket Bilgileri</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div>
</div>

<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<form method="post" action="<?= e(url('modules/settings/company.php')) ?>" enctype="multipart/form-data" data-lock-on-submit novalidate>
    <?= csrf_field() ?>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Firma & Vergi</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group"><label for="company_name">Firma adı</label><input type="text" id="company_name" name="company_name" value="<?= e($get('company_name')) ?>"></div>
                <div class="form-group"><label for="company_legal_name">Ticari unvan</label><input type="text" id="company_legal_name" name="company_legal_name" value="<?= e($get('company_legal_name')) ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="company_tax_office">Vergi dairesi</label><input type="text" id="company_tax_office" name="company_tax_office" value="<?= e($get('company_tax_office')) ?>"></div>
                <div class="form-group"><label for="company_tax_no">Vergi numarası</label><input type="text" id="company_tax_no" name="company_tax_no" value="<?= e($get('company_tax_no')) ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="company_mersis">MERSİS numarası</label><input type="text" id="company_mersis" name="company_mersis" value="<?= e($get('company_mersis')) ?>"></div>
                <div class="form-group"><label for="company_trade_registry">Ticaret sicil numarası</label><input type="text" id="company_trade_registry" name="company_trade_registry" value="<?= e($get('company_trade_registry')) ?>"></div>
            </div>
            <div class="form-group"><label for="company_authorized_name">Yetkili adı (belge kaşe/imza altında görünür)</label><input type="text" id="company_authorized_name" name="company_authorized_name" value="<?= e($get('company_authorized_name')) ?>"></div>
            <div class="form-group"><label for="company_address">Adres</label><textarea id="company_address" name="company_address" rows="2"><?= e($get('company_address')) ?></textarea></div>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>İletişim</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group"><label for="company_phone">Telefon</label><input type="text" id="company_phone" name="company_phone" value="<?= e($get('company_phone')) ?>" inputmode="tel"></div>
                <div class="form-group"><label for="company_whatsapp">WhatsApp</label><input type="text" id="company_whatsapp" name="company_whatsapp" value="<?= e($get('company_whatsapp')) ?>" inputmode="tel"><div class="field-hint">Public takip sayfalarındaki WhatsApp butonu bu numarayı kullanır.</div></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="company_email">E-posta</label><input type="email" id="company_email" name="company_email" value="<?= e($get('company_email')) ?>"></div>
                <div class="form-group"><label for="company_website">Web sitesi</label><input type="text" id="company_website" name="company_website" value="<?= e($get('company_website')) ?>" placeholder="https://..."></div>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Varsayılanlar & Mail Gönderen</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group"><label for="default_currency">Varsayılan para birimi</label>
                    <select id="default_currency" name="default_currency">
                        <?php foreach (['TRY' => 'TL (₺)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)'] as $cv => $cl): ?>
                            <option value="<?= e($cv) ?>"<?= $get('default_currency', 'TRY') === $cv ? ' selected' : '' ?>><?= e($cl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label for="default_vat">Varsayılan KDV oranı (%)</label><input type="text" id="default_vat" name="default_vat" value="<?= e($get('default_vat', '20')) ?>" inputmode="decimal"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="mail_from_name">Mail gönderen adı</label><input type="text" id="mail_from_name" name="mail_from_name" value="<?= e($get('mail_from_name')) ?>" placeholder="PoyrazTech Panel"><div class="field-hint">Boşsa config.php'deki MAIL_FROM_NAME kullanılır.</div></div>
                <div class="form-group"><label for="mail_from_email">Mail gönderen adresi</label><input type="email" id="mail_from_email" name="mail_from_email" value="<?= e($get('mail_from_email')) ?>" placeholder="panel@poyraztech.com"><div class="field-hint">Boşsa config.php'deki MAIL_FROM kullanılır. SMTP giriş hesabı değişmez.</div></div>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Kurumsal Kimlik — Logo, Kaşe & İmza</h2></div>
        <div class="card-body">
            <p class="field-hint" style="margin-top:0">PNG, JPG, WEBP veya SVG — her biri max 2 MB. Görseller orantısı bozulmadan küçültülür; yazdırma, PDF ve e-posta ekindeki belgelerde görünür. Yeni dosya seçmezseniz mevcut görsel korunur.</p>
            <div class="form-row" style="flex-wrap:wrap">
                <?php foreach ($identityFiles as $key => $meta): $val = $get($key); ?>
                    <div class="form-group" style="min-width:240px">
                        <label for="<?= e($meta['field']) ?>"><?= e($meta['label']) ?></label>
                        <input type="file" id="<?= e($meta['field']) ?>" name="<?= e($meta['field']) ?>" accept=".png,.jpg,.jpeg,.webp,.svg,image/*">
                        <div class="field-hint"><?= e($meta['hint']) ?></div>
                        <?php if ($val !== '' && is_file(APP_ROOT . '/' . ltrim($val, '/'))): ?>
                            <div class="settings-media-preview"><img src="<?= e(url(ltrim($val, '/'))) ?>" alt="<?= e($meta['label']) ?>"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="form-group" style="min-width:240px">
                    <label for="company_favicon_file">Favicon (ICO, PNG, SVG — max 512 KB)</label>
                    <input type="file" id="company_favicon_file" name="company_favicon_file" accept=".ico,.png,.svg,image/*">
                    <div class="field-hint">Tüm panel sekmelerinde tarayıcı ikonu olarak gösterilir.</div>
                    <?php if ($fav !== '' && is_file(APP_ROOT . '/' . ltrim($fav, '/'))): ?>
                        <div class="settings-media-preview is-favicon"><img src="<?= e(url(ltrim($fav, '/'))) ?>" alt="Favicon"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Giriş / Tanıtım Sayfası</h2></div>
        <div class="card-body">
            <p class="field-hint" style="margin-top:0">Ana sayfa (poyraztech.com) ve giriş ekranındaki büyük başlık, açıklama ve buton yazısı. Marka adı üstteki "Firma adı", logo ve favicon ise "Logo &amp; Favicon" kartından düzenlenir.</p>
            <div class="form-group">
                <label for="landing_title">Ana başlık</label>
                <textarea id="landing_title" name="landing_title" rows="2" placeholder="İşinizi tek panelden yönetin."><?= e($get('landing_title')) ?></textarea>
                <div class="field-hint">Boşsa varsayılan metin gösterilir. Alt satıra geçmek için Enter kullanabilirsiniz.</div>
            </div>
            <div class="form-group">
                <label for="landing_subtitle">Açıklama</label>
                <textarea id="landing_subtitle" name="landing_subtitle" rows="3" placeholder="Müşteriler, teklifler, siparişler, sevkiyat ve raporlar — hepsi tek yerde."><?= e($get('landing_subtitle')) ?></textarea>
            </div>
            <div class="form-group">
                <label for="landing_cta">Giriş butonu yazısı</label>
                <input type="text" id="landing_cta" name="landing_cta" value="<?= e($get('landing_cta')) ?>" placeholder="Panele Giriş">
            </div>
        </div>
    </div>

    <div class="form-actions" style="max-width:820px">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/settings/index.php')) ?>">Vazgeç</a>
    </div>
</form>

<?php
layout_bottom();
