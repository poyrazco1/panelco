<?php
declare(strict_types=1);

/**
 * modules/settings/personnel-delete.php
 * Tek personel silme. Yalnızca POST + CSRF.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/personnel.php';

auth_boot();
require_permission('personnel');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(303);
    redirect('modules/settings/personnel.php');
}

csrf_check();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    try {
        delete_personnel($id);
        flash('success', 'Personel silindi.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
} else {
    flash('error', 'Geçersiz personel.');
}

http_response_code(303);
redirect('modules/settings/personnel.php');
