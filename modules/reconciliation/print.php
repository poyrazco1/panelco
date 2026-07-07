<?php
declare(strict_types=1);

/** modules/reconciliation/print.php — Mutabakat yazdır/PDF (imza-kaşe alanlı). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation.php';
require_once __DIR__ . '/../../includes/forms.php';

auth_boot();
require_permission('reconciliation.view');

$id = (int) ($_GET['id'] ?? 0);
$r  = get_reconciliation($id);
if (!$r) { flash('error', 'Mutabakat bulunamadı.'); redirect('modules/reconciliation/index.php'); }

$sym = quote_currency_symbol((string) $r['currency']);
$fav = function_exists('pub_favicon_url') ? pub_favicon_url() : null;
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Mutabakat <?= e((string) $r['recon_no']) ?></title>
    <?php if ($fav): ?><link rel="icon" href="<?= e($fav) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<?php document_actions(); ?>
<div class="doc-sheet">
    <?php document_header(['title' => 'MUTABAKAT', 'number' => (string) $r['recon_no'], 'date' => (string) ($r['recon_date'] ?? ''), 'extra' => ['Dönem' => (string) ($r['period'] ?? '')]]); ?>

    <div class="doc-party"><strong>Sayın:</strong> <?= e($r['customer_name']) ?><?php if (!empty($r['cari_code'])): ?> · Cari: <?= e((string) $r['cari_code']) ?><?php endif; ?></div>

    <table class="table" style="margin-top:14px">
        <tbody>
            <tr><th style="width:200px">Borç</th><td><?= e(fmt_money((float) $r['debit']) . ' ' . $sym) ?></td></tr>
            <tr><th>Alacak</th><td><?= e(fmt_money((float) $r['credit']) . ' ' . $sym) ?></td></tr>
            <tr><th>Bakiye</th><td><strong><?= e(fmt_money((float) $r['balance']) . ' ' . $sym) ?></strong></td></tr>
            <tr><th>Sonuç</th><td><?= e(recon_agreement_label((string) $r['agreement'])) ?></td></tr>
        </tbody>
    </table>

    <?php if (trim((string) ($r['description'] ?? '')) !== ''): ?><p style="margin-top:14px"><strong>Açıklama:</strong><br><?= nl2br(e((string) $r['description'])) ?></p><?php endif; ?>

    <p style="margin-top:18px;font-size:12.5px;color:#444">İşbu mutabakat mektubuna <strong>7 gün</strong> içinde itiraz edilmediği takdirde bakiye kabul edilmiş sayılır.</p>

    <?php document_signatures('Düzenleyen', 'Mutabık / Kaşe-İmza'); ?>
    <?php document_footer(); ?>
</div>
</body>
</html>
