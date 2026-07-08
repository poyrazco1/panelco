<?php
declare(strict_types=1);

/**
 * public-form.php — Dış (login gerektirmeyen) form doldurma sayfası.
 * Erişim: /public-form.php?token=<hex>
 * Güvenlik: token aktif + süresi geçmemiş + kullanım hakkı olmalı; honeypot
 * spam alanı; CSRF. Panelden bağımsız sade layout.
 */
require_once __DIR__ . '/includes/form-center.php';
require_once __DIR__ . '/includes/csrf.php';

if (function_exists('app_init_errors')) { app_init_errors(); }
if (defined('DEFAULT_TIMEZONE')) { @date_default_timezone_set(DEFAULT_TIMEZONE); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

$company = SITE_NAME;
$logo = function_exists('pub_logo_url') ? pub_logo_url() : null;

$token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
$check = form_center_validate_external_token($token);

$done = false;
$errors = [];
$values = [];

if ($check['ok'] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = csrf_verify($_POST['_csrf'] ?? '');
    // Honeypot: gerçek kullanıcılar "website" alanını doldurmaz.
    $honey = trim((string) ($_POST['website'] ?? ''));
    if ($honey !== '') {
        // Bot: sessizce başarı göster, kayıt açma.
        $done = true;
    } elseif (!$csrfOk) {
        $errors['_'] = 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.';
    } else {
        $template = $check['template'];
        $res = form_center_validate_submission($template, $_POST);
        $errors = $res['errors'];
        $values = $res['data'];
        if (!$errors) {
            $sid = form_center_create_submission($template, $res['data'], [
                'external_token_id' => (int) $check['token']['id'],
            ]);
            if ($sid > 0) {
                form_center_process_uploads($sid, $template, null);
                form_center_increment_token_use((int) $check['token']['id']);
                if (function_exists('log_activity')) { log_activity('form_public_submit', 'form_submission', $sid, null, 'success', 'Dış form gönderimi'); }
                $done = true;
            } else {
                $errors['_'] = 'Form kaydedilemedi. Lütfen tekrar deneyin.';
            }
        }
    }
}

$reasonMsg = [
    'invalid'         => 'Form linki geçersiz.',
    'not_found'       => 'Form linki bulunamadı.',
    'inactive'        => 'Bu form linki pasif durumda.',
    'expired'         => 'Bu form linkinin süresi dolmuş.',
    'used_up'         => 'Bu form linki için kullanım hakkı dolmuş.',
    'form_unavailable'=> 'Form şu anda kullanıma kapalı.',
    'error'           => 'Form açılırken bir sorun oluştu.',
];
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($company) ?> — Form</title>
<link rel="stylesheet" href="<?= e(asset('css/public.css')) ?>">
<style>
.pf-form .form-group{margin:0 0 14px}
.pf-form label{display:block;font-size:13px;font-weight:600;margin:0 0 5px}
.pf-form input,.pf-form select,.pf-form textarea{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:8px;font-size:15px;background:#fff}
.pf-form .req{color:#b4232a}
.pf-form .field-hint{font-size:12px;color:var(--muted);margin-top:4px}
.pf-form .field-error{font-size:12.5px;color:#b4232a;margin-top:4px}
.pf-form .has-error input,.pf-form .has-error select,.pf-form .has-error textarea{border-color:#b4232a}
.pf-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.pf-btn{display:inline-block;padding:12px 20px;border-radius:8px;border:none;background:#1f2a44;color:#fff;font-size:15px;font-weight:600;cursor:pointer}
.pf-alert{padding:12px 14px;border-radius:8px;margin-bottom:14px;font-size:14px;background:#fbeaea;color:#a12525}
.pf-ok{text-align:center;padding:20px 0}
.pf-ok .pf-ok-ic{font-size:44px}
.check-inline{display:flex;align-items:center;gap:8px;font-weight:400}
.check-inline input{width:auto}
</style>
</head>
<body>
<div class="wrap">
    <div class="p-head">
        <?php if ($logo): ?><img class="p-logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <span class="p-logo-txt"><?= e($company) ?></span>
    </div>

    <?php if (!$check['ok']): ?>
        <div class="p-card">
            <h1 class="p-title">Form açılamıyor</h1>
            <p class="p-meta"><?= e($reasonMsg[$check['reason']] ?? 'Form linki geçerli değil.') ?></p>
        </div>
    <?php elseif ($done): ?>
        <div class="p-card pf-ok">
            <div class="pf-ok-ic">✓</div>
            <h1 class="p-title">Teşekkürler!</h1>
            <p class="p-meta">Form talebiniz alındı. En kısa sürede sizinle iletişime geçeceğiz.</p>
        </div>
    <?php else: $template = $check['template']; $fields = form_center_fields($template); ?>
        <div class="p-card">
            <h1 class="p-title"><?= e((string) ($check['token']['title'] ?? $template['form_name'])) ?></h1>
            <?php if (!empty($template['description'])): ?><p class="p-meta"><?= e((string) $template['description']) ?></p><?php endif; ?>
            <?php if (!empty($errors['_'])): ?><div class="pf-alert"><?= e($errors['_']) ?></div><?php endif; ?>
            <form method="post" action="<?= e(url('public-form.php')) ?>?token=<?= e($token) ?>" enctype="multipart/form-data" class="pf-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="pf-hp" aria-hidden="true"><label>Web sitesi (boş bırakın)<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <?php form_center_render_fields($fields, $values, $errors); ?>
                <button type="submit" class="pf-btn">Gönder</button>
            </form>
        </div>
    <?php endif; ?>
    <p class="p-meta" style="text-align:center">© <?= e(date('Y')) ?> <?= e($company) ?></p>
</div>
</body>
</html>
