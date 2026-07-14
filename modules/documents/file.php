<?php
declare(strict_types=1);

/**
 * modules/documents/file.php — Arşivlenmiş belge PDF'ini yetkili şekilde indirir.
 * Dosyalar uploads/documents altında (web'den doğrudan erişimi engelli); bu uç
 * yetki denetimi + yol doğrulaması yapar.
 *   ?file=<document_files.id>  veya  ?log=<document_email_logs.id>
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/email_system.php';
require_once __DIR__ . '/../../classes/PdfService.php';

auth_boot();

$type = ''; $rel = ''; $name = 'belge.pdf'; $mime = 'application/pdf';

if (!empty($_GET['file'])) {
    $f = document_file_get((int) $_GET['file']);
    if ($f) { $type = (string) $f['document_type']; $rel = (string) $f['file_path']; $name = (string) $f['original_name']; $mime = (string) $f['mime']; }
} elseif (!empty($_GET['log'])) {
    $l = document_email_log_get((int) $_GET['log']);
    if ($l && (int) $l['has_pdf'] === 1) { $type = (string) $l['document_type']; $rel = (string) $l['pdf_path']; $name = 'belge-' . (string) $l['document_no'] . '.pdf'; }
}

if ($rel === '') { http_response_code(404); echo 'Dosya bulunamadı.'; exit; }

// Yetki: belge türünün modülünde .pdf veya .mail
$mod = document_type_module_key($type);
if (!can($mod . '.pdf') && !can($mod . '.mail')) {
    http_response_code(403); echo 'Bu belgeyi indirme yetkiniz yok.'; exit;
}

// Yol doğrulaması: yalnızca uploads/documents altındaki dosyalar.
$rel = ltrim($rel, '/');
$root = defined('APP_ROOT') ? APP_ROOT : (__DIR__ . '/../..');
$abs  = $root . '/' . $rel;
$realBase = realpath($root . '/uploads/documents');
$realFile = realpath($abs);
if ($realBase === false || $realFile === false || strpos($realFile, $realBase) !== 0 || !is_file($realFile)) {
    http_response_code(404); echo 'Dosya bulunamadı.'; exit;
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
header('Content-Length: ' . (string) filesize($realFile));
header('X-Content-Type-Options: nosniff');
readfile($realFile);
exit;
