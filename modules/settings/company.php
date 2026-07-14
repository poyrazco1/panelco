<?php
declare(strict_types=1);

/**
 * modules/settings/company.php
 * Şirket Bilgileri / İletişim / Logo & Favicon / Mail gönderen ayarları (app_settings).
 * PRG + CSRF. Logo ve favicon GERÇEK dosya upload ile yüklenir (tip/boyut kontrollü).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php'; // service_company_info / service_save_company_info

auth_boot();
require_permission('settings');

/* app_settings anahtarları (metin alanları) */
$textKeys = [
    'company_name', 'company_legal_name', 'company_tax_office', 'company_tax_no',
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
    $logoRel = $handleUpload('company_logo_file', 'logo',
        ['png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp'], 'svg' => ['image/svg+xml']],
        2 * 1024 * 1024);
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

            if ($logoRel !== null) { app_setting_set('company_logo', $logoRel); }
            if ($favRel !== null)  { app_setting_set('company_favicon', $favRel); }

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
        <div class="card-header"><h2>Logo & Favicon</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label for="company_logo_file">Logo yükle (PNG, JPG, WEBP, SVG — max 2 MB)</label>
                <input type="file" id="company_logo_file" name="company_logo_file" accept=".png,.jpg,.jpeg,.webp,.svg,image/*">
                <div class="field-hint">Formlarda, PDF çıktılarda ve public takip sayfalarında kullanılır.</div>
                <?php if ($logo !== '' && is_file(APP_ROOT . '/' . ltrim($logo, '/'))): ?>
                    <div class="settings-media-preview"><img src="<?= e(asset(ltrim($logo, '/'))) ?>" alt="Logo"></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="company_favicon_file">Favicon yükle (ICO, PNG, SVG — max 512 KB)</label>
                <input type="file" id="company_favicon_file" name="company_favicon_file" accept=".ico,.png,.svg,image/*">
                <div class="field-hint">Tüm panel sekmelerinde tarayıcı ikonu olarak gösterilir.</div>
                <?php if ($fav !== '' && is_file(APP_ROOT . '/' . ltrim($fav, '/'))): ?>
                    <div class="settings-media-preview is-favicon"><img src="<?= e(asset(ltrim($fav, '/'))) ?>" alt="Favicon"></div>
                <?php endif; ?>
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
