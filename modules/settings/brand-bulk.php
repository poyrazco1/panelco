<?php
declare(strict_types=1);

/**
 * modules/settings/brand-bulk.php
 * Toplu işlem: seçilenleri aktif/pasif yap veya sil. YALNIZCA POST + CSRF.
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

$action = (string) ($_POST['action'] ?? '');
$ids    = $_POST['ids'] ?? [];
$ids    = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
$ids    = array_values(array_filter($ids, fn ($v) => $v > 0));

if (!$ids) {
    flash('info', 'Hiç marka seçilmedi.');
    redirect('modules/settings/brands.php');
}
if (!in_array($action, ['activate', 'deactivate', 'delete'], true)) {
    flash('error', 'Geçersiz işlem.');
    redirect('modules/settings/brands.php');
}

$place = implode(',', array_fill(0, count($ids), '?'));

try {
    if ($action === 'delete') {
        // Logoları da temizle
        foreach ($ids as $bid) {
            delete_brand((int) $bid);
        }
        flash('success', count($ids) . ' marka silindi.');
    } else {
        $val = ($action === 'activate') ? 1 : 0;
        $params = array_merge([$val], $ids);
        db()->prepare("UPDATE brands SET is_active = ? WHERE id IN ($place)")->execute($params);
        flash('success', 'Seçili markalar güncellendi.');
    }
} catch (Throwable $e) {
    flash('error', safe_error('Toplu işlem yapılamadı.', 'brand-bulk: ' . $e->getMessage()));
}

http_response_code(303);
redirect('modules/settings/brands.php');
