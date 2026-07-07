<?php
declare(strict_types=1);

/**
 * modules/settings/personnel-bulk.php
 * Toplu işlem: aktif yap / pasif yap / sil. Yalnızca POST + CSRF.
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

$action = (string) ($_POST['action'] ?? '');
$ids    = array_values(array_unique(array_map('intval', (array) ($_POST['ids'] ?? []))));
$ids    = array_filter($ids, static fn ($v) => $v > 0);

if (!$ids) {
    flash('error', 'Hiç personel seçilmedi.');
    http_response_code(303);
    redirect('modules/settings/personnel.php');
}

try {
    if ($action === 'delete') {
        foreach ($ids as $id) {
            delete_personnel((int) $id);
        }
        flash('success', count($ids) . ' personel silindi.');
    } elseif ($action === 'activate' || $action === 'deactivate') {
        $val = $action === 'activate' ? 1 : 0;
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = db()->prepare("UPDATE personnel SET is_active = ? WHERE id IN ($ph)");
        $st->execute(array_merge([$val], $ids));
        flash('success', count($ids) . ' personel güncellendi.');
    } else {
        flash('error', 'Geçersiz işlem.');
    }
} catch (Throwable $e) {
    log_error('personnel-bulk: ' . $e->getMessage());
    flash('error', 'İşlem sırasında bir hata oluştu.');
}

http_response_code(303);
redirect('modules/settings/personnel.php');
