<?php
declare(strict_types=1);

/** modules/finance/statement-pdf.php — Cari ekstreyi sunucuda PDF üretip indirir. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/finance.php';
require_once __DIR__ . '/../../includes/customers.php';
require_once __DIR__ . '/../../includes/document_builders.php';
require_once __DIR__ . '/../../classes/PdfService.php';
require_once __DIR__ . '/../../classes/DocumentService.php';

auth_boot();
require_permission('finance.pdf');

$cid  = (int) ($_GET['customer'] ?? 0);
$from = trim((string) ($_GET['from'] ?? ''));
$to   = trim((string) ($_GET['to'] ?? ''));
$cust = $cid > 0 ? get_customer_by_id($cid) : null;
if (!$cust) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/finance/statement.php'); }

$stmt = fin_statement($cid, $from, $to);
$curr = !empty($stmt['rows']) ? (string) ($stmt['rows'][0]['currency'] ?? 'TRY') : 'TRY';

try {
    $inner = statement_document_inner_html([
        'customer_name' => (string) $cust['company_name'],
        'from' => $from, 'to' => $to, 'currency' => $curr, 'stmt' => $stmt,
    ]);
    $pdf  = PdfService::render($inner, ['title' => 'Cari Ekstre', 'orientation' => 'landscape']);
    $name = PdfService::friendlyName('cari-ekstre', (string) $cust['company_name'], trim($from . '-' . $to, '-'));
    DocumentService::streamPdf($pdf, $name, !empty($_GET['inline']));
    exit;
} catch (Throwable $e) {
    log_error('statement pdf: ' . $e->getMessage());
    flash('error', 'PDF oluşturulamadı.');
    redirect('modules/finance/statement.php?customer=' . $cid);
}
