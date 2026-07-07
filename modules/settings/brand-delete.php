<?php
declare(strict_types=1);

/**
 * modules/settings/brand-delete.php
 * Marka silme. YALNIZCA POST + CSRF. Logo diskten silinir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/brands.php';

auth_boot();
require_permission('brands');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    redirect('modules/settings/brands.php');
}

csrf_check();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Geçersiz marka.');
    redirect('modules/settings/brands.php');
}

try {
    delete_brand($id);
    flash('success', 'Marka silindi.');
} catch (Throwable $e) {
    flash('error', safe_error('Marka silinemedi.', 'brand-delete: ' . $e->getMessage()));
}

http_response_code(303);
redirect('modules/settings/brands.php');
