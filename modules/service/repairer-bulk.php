<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';
auth_boot();
require_permission('service');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(303); redirect('modules/service/repairers.php'); }
csrf_check();
$action = (string) ($_POST['action'] ?? '');
$ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($v)=>$v>0));
if (!$ids) { flash('info', 'Hiç kayıt seçilmedi.'); http_response_code(303); redirect('modules/service/repairers.php'); }
try {
    if ($action === 'delete') {
        foreach ($ids as $id) { delete_repairer((int) $id); }
        flash('success', count($ids) . ' tamirci silindi.');
    } elseif ($action === 'activate' || $action === 'deactivate') {
        $val = $action === 'activate' ? 1 : 0;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        db()->prepare("UPDATE external_repairers SET is_active = ? WHERE id IN ($ph)")->execute(array_merge([$val], $ids));
        flash('success', 'Seçili tamirciler güncellendi.');
    } else {
        flash('error', 'Geçersiz işlem.');
    }
} catch (Throwable $e) {
    log_error('repairer-bulk: ' . $e->getMessage());
    flash('error', 'İşlem yapılamadı.');
}
http_response_code(303);
redirect('modules/service/repairers.php');
