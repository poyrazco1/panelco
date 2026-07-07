<?php
declare(strict_types=1);

/**
 * modules/settings/company.php
 * Şirket Bilgileri / İletişim ayarları (app_settings). PRG + CSRF.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php'; // service_company_info / service_save_company_info

auth_boot();
require_permission('settings');

$errors = [];

// company_logo için basit tekil erişim (app_settings)
$getLogo = static function (): string {
    try {
        $st = db()->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'company_logo' LIMIT 1");
        $st->execute();
        return trim((string) ($st->fetchColumn() ?: ''));
    } catch (Throwable $e) { return ''; }
};
$saveLogo = static function (string $path): void {
    try {
        db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([':k' => 'company_logo', ':v' => trim($path)]);
    } catch (Throwable $e) { log_error('company logo save: ' . $e->getMessage()); }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string) ($_POST['company_email'] ?? ''));
    if ($email !== '' && !is_valid_email($email)) {
        $errors[] = 'Geçerli bir e-posta girin.';
    }
    if (!$errors) {
        try {
            service_save_company_info([
                'company_name'    => (string) ($_POST['company_name'] ?? ''),
                'company_address' => (string) ($_POST['company_address'] ?? ''),
                'company_phone'   => (string) ($_POST['company_phone'] ?? ''),
                'company_email'   => $email,
                'company_website' => (string) ($_POST['company_website'] ?? ''),
                'company_tax'     => (string) ($_POST['company_tax'] ?? ''),
            ]);
            $saveLogo((string) ($_POST['company_logo'] ?? ''));
            flash('success', 'Şirket bilgileri kaydedildi.');
            http_response_code(303);
            redirect('modules/settings/company.php');
        } catch (Throwable $e) {
            $errors[] = 'Kaydetme sırasında bir sorun oluştu.';
            log_error('company save: ' . $e->getMessage());
        }
    }
}

$info = service_company_info();
$logo = $getLogo();

layout_top('Şirket Bilgileri', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Şirket Bilgileri</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div>
</div>

<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<form method="post" action="<?= e(url('modules/settings/company.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <div class="card" style="max-width:760px">
        <div class="card-header"><h2>Firma & Vergi</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group"><label for="company_name">Firma / Ticari unvan</label><input type="text" id="company_name" name="company_name" value="<?= e($info['company_name']) ?>"></div>
                <div class="form-group"><label for="company_tax">Vergi dairesi / no</label><input type="text" id="company_tax" name="company_tax" value="<?= e($info['company_tax']) ?>"></div>
            </div>
            <div class="form-group"><label for="company_address">Adres</label><textarea id="company_address" name="company_address" rows="2"><?= e($info['company_address']) ?></textarea></div>
        </div>
    </div>

    <div class="card" style="max-width:760px">
        <div class="card-header"><h2>İletişim</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group"><label for="company_phone">Telefon / WhatsApp</label><input type="text" id="company_phone" name="company_phone" value="<?= e($info['company_phone']) ?>" inputmode="tel"><div class="field-hint">Public takip sayfalarındaki WhatsApp butonu bu numarayı kullanır.</div></div>
                <div class="form-group"><label for="company_email">E-posta</label><input type="email" id="company_email" name="company_email" value="<?= e($info['company_email']) ?>"></div>
            </div>
            <div class="form-group"><label for="company_website">Web sitesi</label><input type="text" id="company_website" name="company_website" value="<?= e($info['company_website']) ?>" placeholder="https://..."></div>
        </div>
    </div>

    <div class="card" style="max-width:760px">
        <div class="card-header"><h2>Logo</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label for="company_logo">Logo yolu (göreli)</label>
                <input type="text" id="company_logo" name="company_logo" value="<?= e($logo) ?>" placeholder="assets/uploads/logo.png">
                <div class="field-hint">Public takip sayfalarında üstte gösterilir. Dosyayı sunucuya yükleyip yolunu buraya yazın. Boş bırakılırsa firma adı yazıyla gösterilir.</div>
            </div>
            <?php if ($logo !== '' && is_file(APP_ROOT . '/' . ltrim($logo, '/'))): ?>
                <p class="small muted">Önizleme:</p>
                <img src="<?= e(asset(ltrim($logo, '/'))) ?>" alt="Logo" style="max-height:60px;border:1px solid var(--border);border-radius:6px;padding:4px;background:#fff">
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions" style="max-width:760px">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/settings/index.php')) ?>">Vazgeç</a>
    </div>
</form>

<?php
layout_bottom();
