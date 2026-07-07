<?php
declare(strict_types=1);

/**
 * modules/currency/converter.php
 * Sunucu tarafı kur çevrimi. Veri kur.php'den gelir.
 *  - AJAX / ?format=json  -> JSON döner.
 *  - Normal form gönderimi (JS kapalı) -> sonuç sayfası gösterir.
 * Yalnızca POST + CSRF.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../kur.php';

auth_boot();
require_permission('currency');

$symbols   = kur_symbols();
$wantsJson = (($_GET['format'] ?? '') === 'json')
    || (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest');

// Doğrudan GET erişimi -> çeviriciye dön
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    redirect('modules/currency/index.php');
}

csrf_check();

$amountRaw = trim((string) ($_POST['amount'] ?? ''));
$from      = strtoupper(trim((string) ($_POST['from'] ?? '')));
$to        = strtoupper(trim((string) ($_POST['to'] ?? '')));
$amount    = (float) str_replace(',', '.', $amountRaw);

$errors = [];
if ($amountRaw === '' || !is_numeric(str_replace(',', '.', $amountRaw))) {
    $errors[] = 'Geçerli bir tutar girin.';
}
if (!in_array($from, $symbols, true) || !in_array($to, $symbols, true)) {
    $errors[] = 'Geçersiz para birimi.';
}

$rates  = kur_get_rates();
$result = $errors ? null : kur_convert($rates, $amount, $from, $to);
if ($result === null && !$errors) {
    $errors[] = 'Çevrim yapılamadı, kur verisi eksik.';
}

// --- JSON yanıt ---
if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    if ($errors) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'ok'        => true,
        'amount'    => $amount,
        'from'      => $from,
        'to'        => $to,
        'result'    => round($result, 4),
        'formatted' => fmt_money($result, 2) . ' ' . $to,
        'source'    => $rates['source'] ?? '',
        'date'      => $rates['date'] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- HTML sonuç sayfası (JS kapalı fallback) ---
layout_top('Çevrim Sonucu', 'currency');
?>

<div class="page-head">
    <h1 class="page-title">Çevrim Sonucu</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/currency/index.php')) ?>">← Çeviriciye dön</a>
    </div>
</div>

<?php if ($errors): ?>
    <?php foreach ($errors as $er): ?>
        <div class="alert alert-error"><?= e($er) ?></div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card" style="max-width:520px">
        <div class="card-body">
            <p class="muted" style="margin-bottom:6px">
                <?= e(fmt_money($amount, 2)) ?> <?= e($from) ?> =
            </p>
            <div class="converter-result"><?= e(fmt_money($result, 2)) ?> <?= e($to) ?></div>
            <p class="field-hint" style="margin-top:12px">
                Kaynak: <?= e(kur_source_label($rates['source'] ?? '')) ?> ·
                Tarih: <?= e($rates['date'] ?? '') ?>
            </p>
        </div>
    </div>
<?php endif; ?>

<?php
layout_bottom();
