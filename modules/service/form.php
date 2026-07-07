<?php
declare(strict_types=1);

/**
 * modules/service/form.php
 * Servis Teslim Formu — A4 baskı / PDF (tarayıcı yazdır).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';

auth_boot();
require_permission('service');

$id = (int) ($_GET['id'] ?? 0);
$s  = get_service_record($id);
if (!$s) {
    flash('error', 'Servis kaydı bulunamadı.');
    http_response_code(303);
    redirect('modules/service/index.php');
}

$company = service_company_info();
$terms   = service_active_terms();
$logoRel = 'assets/img/logo.png';
$hasLogo = is_file(APP_ROOT . '/' . $logoRel);

layout_top('Servis Teslim Formu — ' . $s['reference_code'], 'service');
?>

<div class="print-toolbar">
    <button type="button" class="btn btn-primary" onclick="window.print()">Yazdır / PDF</button>
    <a class="btn" href="<?= e(url('modules/service/view.php?id=' . $id)) ?>">← Kayda dön</a>
</div>

<div class="a4">
    <div class="a4-head">
        <div class="a4-company">
            <div class="cn"><?= e($company['company_name'] !== '' ? $company['company_name'] : SITE_NAME) ?></div>
            <?php if ($company['company_address'] !== ''): ?><div><?= nl2br(e($company['company_address'])) ?></div><?php endif; ?>
            <?php if ($company['company_phone'] !== ''): ?><div>Tel: <?= e($company['company_phone']) ?></div><?php endif; ?>
            <?php if ($company['company_email'] !== ''): ?><div>E-posta: <?= e($company['company_email']) ?></div><?php endif; ?>
            <?php if ($company['company_website'] !== ''): ?><div><?= e($company['company_website']) ?></div><?php endif; ?>
            <?php if ($company['company_tax'] !== ''): ?><div>Vergi: <?= e($company['company_tax']) ?></div><?php endif; ?>
        </div>
        <?php if ($hasLogo): ?><img class="a4-logo" src="<?= e(url($logoRel)) ?>" alt="logo"><?php endif; ?>
    </div>

    <h1>SERVİS TESLİM FORMU</h1>
    <div class="a4-ref">
        Referans No: <?= e($s['reference_code']) ?><br>
        Teslim Tarihi: <?= e($s['received_at'] ? date('d.m.Y', (int) strtotime((string) $s['received_at'])) : date('d.m.Y')) ?>
    </div>

    <h2>Teslim Eden Bilgileri</h2>
    <div class="a4-row2">
        <div>
            <strong>Ad Soyad / Firma:</strong> <?= e($s['customer_name']) ?><br>
            <strong>Telefon:</strong> <?= e($s['customer_phone'] ?? '-') ?>
        </div>
        <div>
            <strong>E-posta:</strong> <?= e($s['customer_email'] ?? '-') ?><br>
            <strong>Adres:</strong> <?= e($s['customer_address'] ?? '-') ?>
        </div>
    </div>

    <h2>Teslim Edilen Cihaz</h2>
    <table>
        <thead>
            <tr><th>Marka</th><th>Model</th><th>Miktar</th><th>Seri No</th><th>Aksesuar</th><th>Servis Sebebi / Sorun</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><?= e($s['brand_name'] ?? '-') ?></td>
                <td><?= e($s['device_model'] ?? '-') ?></td>
                <td><?= (int) $s['quantity'] ?></td>
                <td><?= e($s['serial_no'] ?? '-') ?></td>
                <td><?= e($s['accessories'] ?? '-') ?></td>
                <td><?= e($s['problem_description'] ?? '-') ?></td>
            </tr>
        </tbody>
    </table>

    <h2>Servis Koşulları</h2>
    <div class="a4-terms"><?= e($terms['content'] ?? '') ?></div>

    <div class="a4-approval">
        Müşteri servis koşullarını okuduğunu, cihazı belirtilen bilgilerle teslim ettiğini ve dijital onay verdiğini kabul eder.
        <?php if ((int) $s['is_digital_approved'] === 1): ?>
            <br><strong>Dijital Onay:</strong> Alındı
            <?php if (!empty($s['digital_approved_at'])): ?> &nbsp; <strong>Onay Tarihi:</strong> <?= e(date('d.m.Y H:i', (int) strtotime((string) $s['digital_approved_at']))) ?><?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="a4-sign">
        <div class="box">
            <div>Teslim Eden / Müşteri</div>
            <div class="line">Ad Soyad: <?= e($s['customer_name']) ?></div>
            <div class="line">İmza:</div>
        </div>
        <div class="box">
            <div>Teslim Alan / Personel</div>
            <div class="line">Ad Soyad:</div>
            <div class="line">İmza:</div>
        </div>
    </div>
</div>

<?php
layout_bottom();
