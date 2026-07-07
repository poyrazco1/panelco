<?php
declare(strict_types=1);

/**
 * modules/settings/shipping-bulk.php
 * Toplu işlem: seçilenleri aktif/pasif yap veya sil. YALNIZCA POST + CSRF.
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

$action = (string) ($_POST['action'] ?? '');
$ids    = $_POST['ids'] ?? [];
$ids    = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
$ids    = array_filter($ids, fn ($v) => $v > 0);

if (!$ids) {
    flash('info', 'Hiç kargo seçilmedi.');
    redirect('modules/settings/shipping.php');
}
if (!in_array($action, ['activate', 'deactivate', 'delete'], true)) {
    flash('error', 'Geçersiz işlem.');
    redirect('modules/settings/shipping.php');
}

// Güvenli parametreli IN listesi
$place = implode(',', array_fill(0, count($ids), '?'));

try {
    if ($action === 'delete') {
        // Logoları sil, sonra kayıtları (ücretler CASCADE)
        $sel = db()->prepare("SELECT logo_path FROM shipping_methods WHERE id IN ($place)");
        $sel->execute(array_values($ids));
        foreach ($sel->fetchAll() as $row) {
            shipping_delete_logo($row['logo_path'] ?? null);
        }
        db()->prepare("DELETE FROM shipping_methods WHERE id IN ($place)")->execute(array_values($ids));
        flash('success', count($ids) . ' kargo silindi.');
    } else {
        $val = ($action === 'activate') ? 1 : 0;
        $params = array_merge([$val], array_values($ids));
        db()->prepare("UPDATE shipping_methods SET is_active = ? WHERE id IN ($place)")->execute($params);
        flash('success', 'Seçili kargolar güncellendi.');
    }
} catch (Throwable $e) {
    flash('error', safe_error('Toplu işlem yapılamadı.', 'shipping-bulk: ' . $e->getMessage()));
}

http_response_code(303);
redirect('modules/settings/shipping.php');
