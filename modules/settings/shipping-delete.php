<?php
declare(strict_types=1);

/**
 * modules/settings/shipping-delete.php
 * Kargo silme. YALNIZCA POST + CSRF. Ücret satırları CASCADE ile silinir; logo diskten silinir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipping.php';

auth_boot();
require_permission('shipping');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    redirect('modules/settings/shipping.php');
}

csrf_check();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Geçersiz kargo.');
    redirect('modules/settings/shipping.php');
}

try {
    $method = shipping_find($id);
    if (!$method) {
        flash('info', 'Kargo bulunamadı.');
        redirect('modules/settings/shipping.php');
    }
    db()->prepare('DELETE FROM shipping_methods WHERE id = :id')->execute([':id' => $id]);
    shipping_delete_logo($method['logo_path'] ?? null);
    flash('success', 'Kargo yöntemi silindi.');
} catch (Throwable $e) {
    flash('error', safe_error('Kargo silinemedi.', 'shipping-delete: ' . $e->getMessage()));
}

http_response_code(303);
redirect('modules/settings/shipping.php');
