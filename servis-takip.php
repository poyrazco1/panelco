<?php
declare(strict_types=1);
/**
 * servis-takip.php — Public servis kabul takip sayfası (login gerekmez).
 * Erişim: /servis-takip.php?code=SRV-YYYY-000001&token=<64hex>
 * İç maliyet, dış tamirci, kâr, iç notlar GİZLİ.
 */
require_once __DIR__ . '/includes/service.php';

if (function_exists('app_init_errors')) { app_init_errors(); }
if (defined('DEFAULT_TIMEZONE')) { @date_default_timezone_set(DEFAULT_TIMEZONE); }

$code  = (string) ($_GET['code'] ?? '');
$token = (string) ($_GET['token'] ?? '');
$rec   = ($code !== '' && $token !== '') ? service_get_public($code, $token) : null;

$company = service_company_info();
$companyName = $company['company_name'] !== '' ? $company['company_name'] : SITE_NAME;
$companyPhone = $company['company_phone'] ?? '';
$logo = pub_logo_url();

header('X-Content-Type-Options: nosniff');
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($companyName) ?> — Servis Takip</title>
<link rel="stylesheet" href="<?= e(asset('assets/css/public.css')) ?>">
</head>
<body>
<?php if (!$rec): ?>
    <div class="p-notfound">
        <div class="p-head" style="justify-content:center">
            <?php if ($logo): ?><img class="p-logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
            <span class="p-logo-txt"><?= e($companyName) ?></span>
        </div>
        <div class="p-card">
            <h1 class="p-title">Kayıt bulunamadı</h1>
            <p class="p-empty">Takip bağlantısı geçersiz, süresi dolmuş veya kapatılmış olabilir. Lütfen bağlantıyı kontrol edin ya da bizimle iletişime geçin.</p>
        </div>
    </div>
<?php else:
    $tl = get_service_public_history((int) $rec['id']);
    $terms = service_active_terms();
    $approvalLabels = service_approval_labels();
    $showPrice = (int) ($rec['show_price_public'] ?? 0) === 1 && (float) ($rec['customer_price'] ?? 0) > 0;
    $waMsg = rawurlencode('Merhaba, ' . (string) $rec['reference_code'] . ' numaralı servis kaydı hakkında bilgi almak istiyorum.');
    $waPhone = preg_replace('/\D+/', '', (string) $companyPhone);
    $deviceTitle = trim(((string) ($rec['brand_name'] ?? '')) . ' ' . ((string) ($rec['device_model'] ?? '')));
    if ($deviceTitle === '') { $deviceTitle = (string) ($rec['device_type'] ?? 'Cihaz'); }
?>
    <div class="wrap">
        <div class="p-head">
            <?php if ($logo): ?><img class="p-logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
            <span class="p-logo-txt"><?= e($companyName) ?></span>
        </div>

        <div class="p-card">
            <p class="p-ref">Referans No<br><b><?= e($rec['reference_code']) ?></b></p>
            <h1 class="p-title">Servis Süreci</h1>
            <div class="p-badges">
                <span class="p-badge <?= (int) in_array($rec['status'], ['delivered','closed'], true) ? 'ok' : 'wait' ?>"><?= e(service_status_label((string) $rec['status'])) ?></span>
                <?php if (!empty($rec['approval_status'])): ?><span class="p-badge info">Onay: <?= e($approvalLabels[$rec['approval_status']] ?? $rec['approval_status']) ?></span><?php endif; ?>
            </div>
            <p class="p-meta">Teslim tarihi: <?= e($rec['received_at'] ? fmt_date($rec['received_at']) : '—') ?></p>
            <p class="p-meta">Son güncelleme: <?= e($rec['updated_at'] ? fmt_date($rec['updated_at']) : fmt_date($rec['created_at'])) ?></p>
        </div>

        <div class="p-card">
            <h2 class="p-sec">Cihaz Bilgileri</h2>
            <dl class="p-kv">
                <dt>Cihaz</dt><dd><?= e($deviceTitle) ?></dd>
                <?php if (!empty($rec['device_type'])): ?><dt>Tür</dt><dd><?= e($rec['device_type']) ?></dd><?php endif; ?>
                <?php if (!empty($rec['serial_no'])): ?><dt>Seri no</dt><dd><?= e($rec['serial_no']) ?></dd><?php endif; ?>
                <?php if (!empty($rec['accessories'])): ?><dt>Aksesuarlar</dt><dd><?= e($rec['accessories']) ?></dd><?php endif; ?>
                <?php if (!empty($rec['problem_description'])): ?><dt>Servis sebebi</dt><dd><?= nl2br(e($rec['problem_description'])) ?></dd><?php endif; ?>
                <?php if ($showPrice): ?><dt>Tutar</dt><dd><?= fmt_money((float) $rec['customer_price']) ?> TL</dd><?php endif; ?>
            </dl>
        </div>

        <?php if ($tl): ?>
        <div class="p-card">
            <h2 class="p-sec">Süreç Durumu</h2>
            <ul class="p-tl">
                <?php foreach ($tl as $h): ?>
                    <li><div class="st"><?= e(service_status_label((string) $h['new_status'])) ?></div>
                        <div class="dt"><?= e(fmt_date($h['created_at'])) ?></div></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($rec['customer_public_note'])): ?>
        <div class="p-card">
            <h2 class="p-sec">Bilgilendirme</h2>
            <div class="p-note"><?= e($rec['customer_public_note']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($terms && !empty($terms['content'])): ?>
        <div class="p-card">
            <h2 class="p-sec"><?= e($terms['title'] ?? 'Servis Koşulları') ?></h2>
            <div class="p-terms"><?= e($terms['content']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($waPhone !== ''): ?>
        <div class="p-card" style="text-align:center">
            <a class="p-wa" href="https://wa.me/<?= e($waPhone) ?>?text=<?= $waMsg ?>" target="_blank" rel="noopener">WhatsApp ile iletişim</a>
        </div>
        <?php endif; ?>

        <p class="p-foot">
            <?= e($companyName) ?>
            <?php if (!empty($company['company_address'])): ?> · <?= e((string) $company['company_address']) ?><?php endif; ?>
            <?php if (!empty($companyPhone)): ?> · Tel: <?= e((string) $companyPhone) ?><?php endif; ?>
            <?php if (!empty($company['company_website'])): ?> · <?= e((string) $company['company_website']) ?><?php endif; ?>
            <br>Bu sayfa yalnızca süreç takibi içindir.
        </p>
    </div>
<?php endif; ?>
</body>
</html>
