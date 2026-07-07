<?php
declare(strict_types=1);

/**
 * modules/settings/smtp-test.php
 * SMTP / E-posta yapılandırma testi.
 * Yalnızca 'settings' yetkisine sahip kullanıcılar erişebilir.
 * POST ile TEST_MAIL_TO adresine gerçek bir test maili gönderir (PHPMailer/SMTP).
 * Başarı mesajı SADECE send() true dönerse gösterilir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/mail.php';

auth_boot();
require_permission('settings');

$selfUrl = url('modules/settings/smtp-test.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $to = defined('TEST_MAIL_TO') ? trim((string) TEST_MAIL_TO) : '';
    if ($to === '' || !is_valid_email($to)) {
        flash('error', 'Geçersiz test alıcısı (TEST_MAIL_TO). Lütfen config.php içinde tanımlayın.');
        http_response_code(303);
        redirect($selfUrl);
    }

    $subject = 'PoyrazTech Panel SMTP Test';
    $body    = "Bu e-posta PoyrazTech Panel SMTP yapılandırmasını test etmek için gönderilmiştir.";

    $res = mail_send($to, 'PoyrazTech Panel', $subject, $body, ['type' => 'test']);
    if ($res['ok']) {
        flash('success', 'SMTP test maili başarıyla gönderildi.');
    } elseif (($res['error'] ?? '') === 'not_configured') {
        // Yapılandırma eksik/placeholder — özel yönlendirici mesaj.
        flash('error', $res['msg']);
    } else {
        flash('error', 'SMTP test maili gönderilemedi. Log kayıtlarını kontrol edin.');
    }
    http_response_code(303);
    redirect($selfUrl);
}

// Görüntüleme: mevcut yapılandırma özeti (ŞİFRE GÖSTERİLMEZ).
$cfg      = mail_config_status();
$host     = defined('SMTP_HOST') ? (string) SMTP_HOST : '';
$port     = defined('SMTP_PORT') ? (int) SMTP_PORT : 0;
$user     = defined('SMTP_USERNAME') ? (string) SMTP_USERNAME : '';
$enc      = defined('SMTP_ENCRYPTION') ? (string) SMTP_ENCRYPTION : '';
$from     = defined('MAIL_FROM') ? (string) MAIL_FROM : '';
$fromName = defined('MAIL_FROM_NAME') ? (string) MAIL_FROM_NAME : '';
$testTo   = defined('TEST_MAIL_TO') ? (string) TEST_MAIL_TO : '';
$placeholder = defined('SMTP_PASSWORD_PLACEHOLDER') ? (string) SMTP_PASSWORD_PLACEHOLDER : 'BURAYA_MAIL_SIFRESI_YAZILACAK';
$passSet  = defined('SMTP_PASSWORD') && (string) SMTP_PASSWORD !== '' && (string) SMTP_PASSWORD !== $placeholder;

layout_top('SMTP / E-posta Testi', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">SMTP / E-posta Testi</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Ayarlar</a>
    </div>
</div>

<?php render_flashes(); ?>

<div class="card">
    <div class="card-header"><strong>Mevcut SMTP Yapılandırması</strong></div>
    <div class="card-body">
        <?php if ($cfg['ok']): ?>
            <div class="alert alert-success">SMTP yapılandırması eksiksiz görünüyor. Aşağıdaki butonla gerçek bir test maili gönderebilirsiniz.</div>
        <?php else: ?>
            <div class="alert alert-error"><?= e($cfg['msg']) ?></div>
        <?php endif; ?>

        <table class="table">
            <tbody>
                <tr><th style="width:220px">SMTP Sunucu</th><td><?= e($host !== '' ? $host : '—') ?></td></tr>
                <tr><th>Port</th><td><?= e($port > 0 ? (string) $port : '—') ?></td></tr>
                <tr><th>Kullanıcı Adı</th><td><?= e($user !== '' ? $user : '—') ?></td></tr>
                <tr><th>Şifre</th><td><?= $passSet ? 'Tanımlı (gizli)' : 'Tanımlı değil / placeholder' ?></td></tr>
                <tr><th>Şifreleme</th><td><?= e($enc !== '' ? strtoupper($enc) : 'Yok') ?></td></tr>
                <tr><th>Gönderen (From)</th><td><?= e($fromName !== '' ? $fromName . ' <' . $from . '>' : ($from !== '' ? $from : '—')) ?></td></tr>
                <tr><th>Test Alıcısı</th><td><?= e($testTo !== '' ? $testTo : '—') ?></td></tr>
            </tbody>
        </table>

        <form method="post" action="<?= e($selfUrl) ?>" style="margin-top:16px">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary"><?= icon('mail') ?>Test Maili Gönder</button>
        </form>

        <p class="muted" style="margin-top:12px;font-size:12.5px">
            Not: SMTP şifresi yalnızca sunucudaki <code>config.php</code> içinde saklanır; bu ekranda, loglarda ve hata
            mesajlarında hiçbir zaman gösterilmez.
        </p>
    </div>
</div>

<?php
layout_bottom();
