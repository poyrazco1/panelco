<?php
declare(strict_types=1);

/** modules/documents/pdf.php — Herhangi bir belge türünü sunucuda PDF üretip indirir. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/document_registry.php';
require_once __DIR__ . '/../../classes/PdfService.php';
require_once __DIR__ . '/../../classes/DocumentService.php';

auth_boot();

$type = (string) ($_GET['type'] ?? '');
$id   = (int) ($_GET['id'] ?? 0);
$m    = document_type_meta($type);
if (!$m) { http_response_code(404); echo 'Bilinmeyen belge türü.'; exit; }

require_permission($m['module'] . '.pdf');

$row = document_type_load($type, $id);
if (!$row) { flash('error', 'Belge bulunamadı.'); redirect($m['index']); }

try {
    $no    = (string) ($m['no'])($row);
    $inner = document_type_inner($type, $row);
    $pdf   = PdfService::render($inner, ['title' => $m['label'] . ' ' . $no, 'orientation' => (string) ($m['orientation'] ?? 'portrait')]);
    $name  = PdfService::friendlyName((string) $m['label'], $no, (string) ($m['party'])($row));
    DocumentService::streamPdf($pdf, $name, !empty($_GET['inline']));
    exit;
} catch (Throwable $e) {
    log_error('documents pdf [' . $type . ']: ' . $e->getMessage());
    flash('error', 'PDF oluşturulamadı. Lütfen daha sonra tekrar deneyin.');
    redirect($m['index']);
}
