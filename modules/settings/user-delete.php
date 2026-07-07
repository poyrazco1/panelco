<?php
declare(strict_types=1);

/**
 * modules/settings/user-delete.php
 * Kullanıcı silme. YALNIZCA POST + CSRF.
 * Kendini silemez; son aktif yönetici silinemez.
 */
require_once __DIR__ . '/../../includes/permissions.php';

auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    redirect('modules/settings/users.php');
}

csrf_check();

$me = current_user();
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Geçersiz kullanıcı.');
    redirect('modules/settings/users.php');
}
if ($id === $me['id']) {
    flash('error', 'Kendi hesabınızı silemezsiniz.');
    redirect('modules/settings/users.php');
}

try {
    $st = db()->prepare('SELECT is_active, role_id FROM users WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $row = $st->fetch();

    if (!$row) {
        flash('info', 'Kullanıcı bulunamadı.');
        redirect('modules/settings/users.php');
    }

    // Son aktif yönetici korumasi
    $targetIsActiveAdmin = ((int) $row['is_active'] === 1)
        && is_admin_role($row['role_id'] !== null ? (int) $row['role_id'] : null);
    if ($targetIsActiveAdmin && count_active_admins($id) < 1) {
        flash('error', 'En az bir aktif yönetici kalmalıdır. Bu kullanıcı silinemez.');
        redirect('modules/settings/users.php');
    }

    db()->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
    flash('success', 'Kullanıcı silindi.');
} catch (Throwable $e) {
    flash('error', safe_error('Kullanıcı silinemedi.', 'user-delete: ' . $e->getMessage()));
}

redirect('modules/settings/users.php');
