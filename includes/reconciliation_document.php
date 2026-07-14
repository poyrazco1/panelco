<?php
declare(strict_types=1);

/**
 * includes/reconciliation_document.php
 * Mutabakat belgesinin ortak gövdesi (yazdırma, PDF ve e-posta AYNI çıktıyı kullanır).
 */

require_once __DIR__ . '/reconciliation.php';
require_once __DIR__ . '/forms.php';

/** Mutabakat belge gövdesini (.doc-sheet içeriği) HTML olarak üretir. */
function recon_document_inner_html(array $r): string
{
    $sym = quote_currency_symbol((string) $r['currency']);
    ob_start();
    document_header([
        'title'       => 'MUTABAKAT',
        'number'      => (string) $r['recon_no'],
        'date'        => (string) ($r['recon_date'] ?? ''),
        'department'  => 'Muhasebe',
        'prepared_by' => (string) ($r['created_by_name'] ?? ''),
        'extra'       => ['Dönem' => (string) ($r['period'] ?? '')],
    ]);
    ?>
    <div class="doc-party"><strong>Sayın:</strong> <?= e((string) $r['customer_name']) ?><?php if (!empty($r['cari_code'])): ?> · Cari: <?= e((string) $r['cari_code']) ?><?php endif; ?></div>

    <table class="table" style="margin-top:14px">
        <tbody>
            <tr><th style="width:200px">Borç</th><td><?= e(fmt_money((float) $r['debit']) . ' ' . $sym) ?></td></tr>
            <tr><th>Alacak</th><td><?= e(fmt_money((float) $r['credit']) . ' ' . $sym) ?></td></tr>
            <tr><th>Bakiye</th><td><strong><?= e(fmt_money((float) $r['balance']) . ' ' . $sym) ?></strong></td></tr>
            <tr><th>Sonuç</th><td><?= e(recon_agreement_label((string) $r['agreement'])) ?></td></tr>
        </tbody>
    </table>

    <?php if (trim((string) ($r['description'] ?? '')) !== ''): ?>
        <p style="margin-top:14px"><strong>Açıklama:</strong><br><?= nl2br(e((string) $r['description'])) ?></p>
    <?php endif; ?>

    <p style="margin-top:18px;font-size:12.5px;color:#444">İşbu mutabakat mektubuna <strong>7 gün</strong> içinde itiraz edilmediği takdirde bakiye kabul edilmiş sayılır.</p>

    <?php
    document_signatures('Düzenleyen', 'Mutabık / Kaşe-İmza', ['right_name' => (string) ($r['authorized_name'] ?? '')]);
    document_footer();
    return (string) ob_get_clean();
}
