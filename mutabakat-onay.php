<?php
declare(strict_types=1);

/**
 * mutabakat-onay.php — Public mutabakat görüntüleme + dijital onay (login gerekmez).
 * Erişim: /mutabakat-onay.php?token=<64hex>
 * Müşteri belgeyi görür, PDF indirir ve "Mutabıkız / Mutabık değiliz" onayı verir.
 */
require_once __DIR__ . '/includes/reconciliation_document.php';
require_once __DIR__ . '/includes/email_system.php';
require_once __DIR__ . '/includes/service.php';           // service_company_info
require_once __DIR__ . '/classes/DocumentTokenService.php';
require_once __DIR__ . '/classes/PdfService.php';
require_once __DIR__ . '/classes/DocumentService.php';

if (function_exists('app_init_errors')) { app_init_errors(); }
if (defined('DEFAULT_TIMEZONE')) { @date_default_timezone_set(DEFAULT_TIMEZONE); }
header('X-Content-Type-Options: nosniff');

$token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
$t = $token !== '' ? DocumentTokenService::verify($token, 'reconciliation') : null;
$r = $t ? get_reconciliation((int) $t['document_id']) : null;

// Token geçerliyse PDF akışı
if ($r && isset($_GET['pdf'])) {
    try {
        $pdf  = PdfService::render(recon_document_inner_html($r), ['title' => 'Mutabakat ' . (string) $r['recon_no']]);
        $name = PdfService::friendlyName('mutabakat', (string) $r['recon_no'], (string) $r['customer_name']);
        DocumentService::streamPdf($pdf, $name, true);
    } catch (Throwable $e) {
        log_error('mutabakat-onay pdf: ' . $e->getMessage());
        http_response_code(500);
        echo 'PDF oluşturulamadı.';
    }
    exit;
}

$done = false; $doneDecision = '';
if ($r && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = ($_POST['decision'] ?? '') === 'disagreed' ? 'disagreed' : (($_POST['decision'] ?? '') === 'agreed' ? 'agreed' : '');
    $note     = trim((string) ($_POST['note'] ?? ''));
    $authName = trim((string) ($_POST['authorized_name'] ?? ''));
    if ($decision === '') {
        $formError = 'Lütfen "Mutabıkız" veya "Mutabık değiliz" seçeneğini işaretleyin.';
    } elseif ($authName === '') {
        $formError = 'Lütfen yetkili ad soyad girin.';
    } else {
        document_approval_insert([
            'document_type'   => 'reconciliation',
            'document_id'     => (int) $r['id'],
            'token_id'        => (int) $t['id'],
            'decision'        => $decision,
            'note'            => $note,
            'authorized_name' => $authName,
            'ip_address'      => function_exists('client_ip') ? client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent'      => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);
        try {
            db()->prepare('UPDATE reconciliations SET agreement = ?, authorized_name = ? WHERE id = ?')
                ->execute([$decision, $authName, (int) $r['id']]);
        } catch (Throwable $e) { log_error('mutabakat-onay update: ' . $e->getMessage()); }
        if (function_exists('log_activity')) {
            log_activity('reconciliation_approval', 'reconciliation', (int) $r['id'], (string) $r['recon_no'], 'success',
                'Müşteri onayı: ' . $decision . ' (' . $authName . ')');
        }
        $done = true; $doneDecision = $decision;
        $r = get_reconciliation((int) $r['id']); // güncel durumu yansıt
    }
}

$approvals   = $r ? document_approvals_for('reconciliation', (int) $r['id']) : [];
$company     = service_company_info();
$companyName = $company['company_name'] !== '' ? $company['company_name'] : SITE_NAME;
$logo        = pub_logo_url();
$fav         = pub_favicon_url();
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($companyName) ?> — Mutabakat Onayı</title>
<?php if ($fav): ?><link rel="icon" type="<?= e(favicon_mime($fav)) ?>" href="<?= e($fav) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>">
<style>
  body { background:#f5f6f8; }
  .onay-wrap { max-width: 880px; margin: 0 auto; padding: 20px 14px 60px; }
  .onay-brand { display:flex; align-items:center; gap:10px; justify-content:center; margin: 8px 0 18px; }
  .onay-brand img { max-height:44px; max-width:180px; }
  .onay-brand span { font-weight:700; color:#1F2A44; font-size:18px; }
  .onay-card { background:#fff; border:1px solid #e2e5ea; border-radius:12px; padding:20px; margin-top:16px; }
  .onay-opts { display:flex; gap:12px; flex-wrap:wrap; margin:10px 0; }
  .onay-opt { flex:1; min-width:200px; border:1px solid #e2e5ea; border-radius:10px; padding:12px 14px; cursor:pointer; }
  .onay-opt input { margin-right:8px; }
  .onay-opt.sel-agree { border-color:#276749; background:#e9f3ec; }
  .onay-opt.sel-disagree { border-color:#9b1c22; background:#fbebec; }
  @media print { .onay-actions, .onay-form, .onay-brand { display:none !important; } body{background:#fff;} }
</style>
</head>
<body>
<div class="onay-wrap">
    <div class="onay-brand">
        <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="<?= e($companyName) ?>"><?php else: ?><span><?= e($companyName) ?></span><?php endif; ?>
    </div>

<?php if (!$r): ?>
    <div class="onay-card" style="text-align:center">
        <h1 style="margin-top:0">Bağlantı geçersiz</h1>
        <p class="muted">Bu mutabakat bağlantısı geçersiz, süresi dolmuş veya iptal edilmiş olabilir. Lütfen bağlantıyı gönderen firma ile iletişime geçin.</p>
    </div>
<?php else:
    $latest = $approvals[0] ?? null;
?>
    <?php if ($done): ?>
        <div class="alert alert-success">Onayınız kaydedildi: <strong><?= $doneDecision === 'agreed' ? 'Mutabıkız' : 'Mutabık değiliz' ?></strong>. Teşekkür ederiz.</div>
    <?php elseif (!empty($formError)): ?>
        <div class="alert alert-error"><?= e($formError) ?></div>
    <?php endif; ?>

    <div class="onay-actions no-print" style="text-align:right;margin-bottom:6px">
        <a class="btn btn-sm" href="<?= e(url('mutabakat-onay.php')) ?>?token=<?= e($token) ?>&amp;pdf=1" target="_blank">PDF görüntüle / indir</a>
    </div>

    <div class="doc-sheet">
        <?= recon_document_inner_html($r) ?>
    </div>

    <?php if ($latest !== null): ?>
        <div class="onay-card">
            <h2 style="margin-top:0">Onay Durumu</h2>
            <p>
                <span class="badge <?= $latest['decision'] === 'agreed' ? 'badge-success' : 'badge-danger' ?>">
                    <?= $latest['decision'] === 'agreed' ? 'Mutabıkız' : 'Mutabık değiliz' ?>
                </span>
                — <?= e((string) $latest['authorized_name']) ?> ·
                <?= e(date('d.m.Y H:i', strtotime((string) $latest['created_at']))) ?>
            </p>
            <?php if (trim((string) $latest['note']) !== ''): ?><p class="muted"><?= nl2br(e((string) $latest['note'])) ?></p><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$done): ?>
    <form class="onay-card onay-form" method="post" action="<?= e(url('mutabakat-onay.php')) ?>?token=<?= e($token) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <h2 style="margin-top:0">Mutabakat Onayı</h2>
        <div class="onay-opts">
            <label class="onay-opt"><input type="radio" name="decision" value="agreed" required> Mutabıkız</label>
            <label class="onay-opt"><input type="radio" name="decision" value="disagreed"> Mutabık değiliz</label>
        </div>
        <div class="form-group"><label for="o-name">Yetkili ad soyad</label>
            <input type="text" id="o-name" name="authorized_name" value="<?= e((string) ($r['authorized_name'] ?? '')) ?>" required></div>
        <div class="form-group"><label for="o-note">Açıklama (opsiyonel)</label>
            <textarea id="o-note" name="note" rows="3"></textarea></div>
        <button type="submit" class="btn btn-primary">Onayı Gönder</button>
        <p class="field-hint" style="margin-top:8px">Onayınızın tarihi, saati ve IP adresi kayıt altına alınır.</p>
    </form>
    <?php endif; ?>
<?php endif; ?>
</div>
<script>
document.querySelectorAll('.onay-opt input').forEach(function(i){
    i.addEventListener('change', function(){
        document.querySelectorAll('.onay-opt').forEach(function(o){ o.classList.remove('sel-agree','sel-disagree'); });
        var box = i.closest('.onay-opt');
        if (box) box.classList.add(i.value === 'agreed' ? 'sel-agree' : 'sel-disagree');
    });
});
</script>
</body>
</html>
