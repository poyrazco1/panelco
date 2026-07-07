<?php
declare(strict_types=1);

/**
 * modules/file-manager/download.php — Güvenli dosya indirme (yetki + yol doğrulaması).
 * Dosya sunucu tarafında okunur; kök dışı/korumalı dosyalar reddedilir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/filemanager.php';

auth_boot();
require_permission('file_manager.view');

$rel = (string) ($_GET['f'] ?? '');
$abs = fm_resolve($rel);
$name = $abs !== null ? basename($abs) : '';

if ($abs === null || !is_file($abs) || fm_protected_name($name)) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

log_activity('file_download', 'file', null, fm_relative($abs), 'success', 'Dosya indirildi');

// Uzantıya göre güvenli içerik tipi (çalıştırmayı önlemek için indirme olarak sun)
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
header('Content-Length: ' . (string) filesize($abs));
header('X-Content-Type-Options: nosniff');
header('Pragma: no-cache');
header('Expires: 0');
readfile($abs);
exit;
