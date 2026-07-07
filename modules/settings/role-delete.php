<?php
declare(strict_types=1);

/**
 * modules/settings/role-delete.php
 * Rol silme. YALNIZCA POST + CSRF.
 * Sistem rolü (Yönetici) silinemez; kullanıcıya atanmış rol silinemez.
 */
require_once __DIR__ . '/../../includes/permissions.php';

auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    redirect('modules/settings/roles.php');
}

csrf_check();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Geçersiz rol.');
    redirect('modules/settings/roles.php');
}

try {
    $st = db()->prepare('SELECT is_system FROM roles WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $role = $st->fetch();

    if (!$role) {
        flash('info', 'Rol bulunamadı.');
        redirect('modules/settings/roles.php');
    }
    if ((int) $role['is_system'] === 1) {
        flash('error', 'Sistem rolü silinemez.');
        redirect('modules/settings/roles.php');
    }

    $chk = db()->prepare('SELECT COUNT(*) FROM users WHERE role_id = :id');
    $chk->execute([':id' => $id]);
    if ((int) $chk->fetchColumn() > 0) {
        flash('error', 'Bu rol kullanıcılara atanmış. Önce ilgili kullanıcıların rolünü değiştirin.');
        redirect('modules/settings/roles.php');
    }

    db()->prepare('DELETE FROM roles WHERE id = :id')->execute([':id' => $id]);
    flash('success', 'Rol silindi.');
} catch (Throwable $e) {
    flash('error', safe_error('Rol silinemedi.', 'role-delete: ' . $e->getMessage()));
}

redirect('modules/settings/roles.php');
