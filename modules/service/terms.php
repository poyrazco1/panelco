<?php
declare(strict_types=1);

/**
 * modules/service/terms.php
 * Servis Koşulları metni + Firma Bilgileri (servis formunda kullanılır).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';

auth_boot();
require_permission('service');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = (string) ($_POST['form'] ?? '');
    try {
        if ($do === 'terms') {
            $title = trim((string) ($_POST['title'] ?? 'Servis Koşulları'));
            $content = trim((string) ($_POST['content'] ?? ''));
            if ($content === '') {
                throw new RuntimeException('Servis koşulları metni boş olamaz.');
            }
            service_update_terms((int) ($_POST['terms_id'] ?? 0), $title !== '' ? $title : 'Servis Koşulları', $content);
            flash('success', 'Servis koşulları kaydedildi.');
        } elseif ($do === 'company') {
            service_save_company_info($_POST);
            flash('success', 'Firma bilgileri kaydedildi.');
        }
        http_response_code(303);
        redirect('modules/service/terms.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$terms   = service_active_terms();
$company = service_company_info();

layout_top('Servis Koşulları', 'service');
?>

<div class="page-head">
    <h1 class="page-title">Servis Koşulları & Firma Bilgileri</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/service/index.php')) ?>">← Servis</a></div>
</div>

<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<div class="card" style="max-width:760px">
    <div class="card-header"><h2>Servis Koşulları Metni</h2></div>
    <div class="card-body">
        <p class="field-hint" style="margin-top:0">Bu metin servis teslim formunda görünür.</p>
        <form method="post" action="<?= e(url('modules/service/terms.php')) ?>" data-lock-on-submit>
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="terms">
            <input type="hidden" name="terms_id" value="<?= (int) ($terms['id'] ?? 0) ?>">
            <div class="form-group">
                <label for="title">Başlık</label>
                <input type="text" id="title" name="title" value="<?= e((string) ($terms['title'] ?? 'Servis Koşulları')) ?>">
            </div>
            <div class="form-group">
                <label for="content">Koşullar</label>
                <textarea id="content" name="content" rows="6"><?= e((string) ($terms['content'] ?? '')) ?></textarea>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Koşulları kaydet</button></div>
        </form>
    </div>
</div>

<div class="card" style="max-width:760px">
    <div class="card-header"><h2>Firma Bilgileri (Form Üst Bilgisi)</h2></div>
    <div class="card-body">
        <p class="field-hint" style="margin-top:0">Servis teslim formunun üstünde görünür. Logo için <code>assets/img/logo.png</code> yükleyin.</p>
        <form method="post" action="<?= e(url('modules/service/terms.php')) ?>" data-lock-on-submit>
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="company">
            <div class="form-row">
                <div class="form-group"><label for="company_name">Firma adı</label><input type="text" id="company_name" name="company_name" value="<?= e($company['company_name']) ?>"></div>
                <div class="form-group"><label for="company_phone">Telefon</label><input type="text" id="company_phone" name="company_phone" value="<?= e($company['company_phone']) ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="company_email">E-posta</label><input type="email" id="company_email" name="company_email" value="<?= e($company['company_email']) ?>"></div>
                <div class="form-group"><label for="company_website">Web sitesi</label><input type="text" id="company_website" name="company_website" value="<?= e($company['company_website']) ?>"></div>
            </div>
            <div class="form-group"><label for="company_address">Adres</label><textarea id="company_address" name="company_address" rows="2"><?= e($company['company_address']) ?></textarea></div>
            <div class="form-group"><label for="company_tax">Vergi bilgileri (opsiyonel)</label><input type="text" id="company_tax" name="company_tax" value="<?= e($company['company_tax']) ?>"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Firma bilgilerini kaydet</button></div>
        </form>
    </div>
</div>

<?php
layout_bottom();
