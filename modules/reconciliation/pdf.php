<?php
declare(strict_types=1);

/** modules/reconciliation/pdf.php — Mutabakatı sunucuda PDF üretip indirir. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation_document.php';
require_once __DIR__ . '/../../classes/PdfService.php';
require_once __DIR__ . '/../../classes/DocumentService.php';

auth_boot();
require_permission('reconciliation.pdf');

$id = (int) ($_GET['id'] ?? 0);
$r  = get_reconciliation($id);
if (!$r) { flash('error', 'Mutabakat bulunamadı.'); redirect('modules/reconciliation/index.php'); }

try {
    $inner = recon_document_inner_html($r);
    $pdf   = PdfService::render($inner, ['title' => 'Mutabakat ' . (string) $r['recon_no']]);
    $name  = PdfService::friendlyName('mutabakat', (string) $r['recon_no'], (string) $r['customer_name']);
    $inline = !empty($_GET['inline']);
    DocumentService::streamPdf($pdf, $name, $inline);
    exit;
} catch (Throwable $e) {
    log_error('reconciliation pdf: ' . $e->getMessage());
    flash('error', 'PDF oluşturulamadı. Lütfen daha sonra tekrar deneyin.');
    redirect('modules/reconciliation/view.php?id=' . $id);
}
