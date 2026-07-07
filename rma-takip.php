<?php
declare(strict_types=1);
/**
 * rma-takip.php — Public iade/değişim takip sayfası (login gerekmez).
 * Erişim: /rma-takip.php?code=RMA-YYYYMMDD-0001&token=<64hex>
 * Yalnızca müşteriye uygun alanlar gösterilir; iç maliyet/tedarikçi/iç not GİZLİ.
 */
require_once __DIR__ . '/includes/rma.php';
require_once __DIR__ . '/includes/service.php'; // yalnızca firma bilgisi için

if (function_exists('app_init_errors')) { app_init_errors(); }
if (defined('DEFAULT_TIMEZONE')) { @date_default_timezone_set(DEFAULT_TIMEZONE); }

$code  = (string) ($_GET['code'] ?? '');
$token = (string) ($_GET['token'] ?? '');
$rec   = ($code !== '' && $token !== '') ? rma_get_public($code, $token) : null;

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
<title><?= e($companyName) ?> — İade / Değişim Takip</title>
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
    $statuses = rma_statuses();
    $types = rma_process_types();
    $recv = get_rma_received_items((int) $rec['id']);
    $sent = get_rma_sent_items((int) $rec['id']);
    $tl = get_rma_public_history((int) $rec['id']);
    $waMsg = rawurlencode('Merhaba, ' . (string) $rec['reference_code'] . ' numaralı iade/değişim süreci hakkında bilgi almak istiyorum.');
    $waPhone = preg_replace('/\D+/', '', (string) $companyPhone);
?>
    <div class="wrap">
        <div class="p-head">
            <?php if ($logo): ?><img class="p-logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
            <span class="p-logo-txt"><?= e($companyName) ?></span>
        </div>

        <div class="p-card">
            <p class="p-ref">Referans No<br><b><?= e($rec['reference_code']) ?></b></p>
            <h1 class="p-title">İade / Değişim Süreci</h1>
            <div class="p-badges">
                <span class="p-badge <?= (int) in_array($rec['status'], ['closed','cancelled'], true) ? 'ok' : 'wait' ?>"><?= e($statuses[$rec['status']] ?? $rec['status']) ?></span>
                <span class="p-badge info"><?= e($types[$rec['process_type']] ?? $rec['process_type']) ?></span>
                <?php if (!empty($rec['reason_type'])): ?><span class="p-badge"><?= e($rec['reason_type']) ?></span><?php endif; ?>
            </div>
            <p class="p-meta">İşlem tarihi: <?= e($rec['process_date'] ?? '—') ?></p>
            <p class="p-meta">Son güncelleme: <?= e($rec['updated_at'] ? fmt_date($rec['updated_at']) : fmt_date($rec['created_at'])) ?></p>
        </div>

        <?php if ($tl): ?>
        <div class="p-card">
            <h2 class="p-sec">Süreç Durumu</h2>
            <ul class="p-tl">
                <?php foreach ($tl as $h): ?>
                    <li><div class="st"><?= e(rma_status_label((string) $h['new_status'])) ?></div>
                        <div class="dt"><?= e(fmt_date($h['created_at'])) ?></div></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($recv): ?>
        <div class="p-card">
            <h2 class="p-sec">Tarafımıza Ulaşan Ürünler</h2>
            <div class="p-items">
                <?php foreach ($recv as $it): ?>
                    <div class="p-item">
                        <div class="nm"><?= e(trim(((string) ($it['product_name'] ?? '')) . ' ' . ((string) ($it['product_model'] ?? '')))) ?: 'Ürün' ?></div>
                        <div class="sub">Adet: <?= (int) ($it['quantity'] ?? 1) ?><?= !empty($it['brand_name']) ? ' · ' . e($it['brand_name']) : '' ?></div>
                        <?php if (!empty($it['cargo_tracking_no'])): ?>
                            <div class="p-track">Kargo<?= !empty($it['cargo_company']) ? ' (' . e($it['cargo_company']) . ')' : '' ?>:
                                <?php if (!empty($it['cargo_tracking_url'])): ?><a href="<?= e($it['cargo_tracking_url']) ?>" target="_blank" rel="noopener"><?= e($it['cargo_tracking_no']) ?></a><?php else: ?><?= e($it['cargo_tracking_no']) ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($sent): ?>
        <div class="p-card">
            <h2 class="p-sec">Tarafınıza Gönderilen Ürünler</h2>
            <div class="p-items">
                <?php foreach ($sent as $it): ?>
                    <div class="p-item">
                        <div class="nm"><?= e(trim(((string) ($it['product_name'] ?? '')) . ' ' . ((string) ($it['product_model'] ?? '')))) ?: 'Ürün' ?></div>
                        <div class="sub">Adet: <?= (int) ($it['quantity'] ?? 1) ?><?= !empty($it['brand_name']) ? ' · ' . e($it['brand_name']) : '' ?></div>
                        <?php if (!empty($it['cargo_tracking_no'])): ?>
                            <div class="p-track">Kargo<?= !empty($it['cargo_company']) ? ' (' . e($it['cargo_company']) . ')' : '' ?>:
                                <?php if (!empty($it['cargo_tracking_url'])): ?><a href="<?= e($it['cargo_tracking_url']) ?>" target="_blank" rel="noopener"><?= e($it['cargo_tracking_no']) ?></a><?php else: ?><?= e($it['cargo_tracking_no']) ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($rec['customer_public_note'])): ?>
        <div class="p-card">
            <h2 class="p-sec">Bilgilendirme</h2>
            <div class="p-note"><?= e($rec['customer_public_note']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($waPhone !== ''): ?>
        <div class="p-card" style="text-align:center">
            <a class="p-wa" href="https://wa.me/<?= e($waPhone) ?>?text=<?= $waMsg ?>" target="_blank" rel="noopener">WhatsApp ile iletişim</a>
        </div>
        <?php endif; ?>

        <p class="p-foot"><?= e($companyName) ?> · Bu sayfa yalnızca süreç takibi içindir.</p>
    </div>
<?php endif; ?>
</body>
</html>
