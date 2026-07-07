<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/rma.php';
auth_boot();
require_permission('rma');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(303); redirect('modules/rma/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$do = (string) ($_POST['do'] ?? '');
try {
    if ($do === 'regenerate') { rma_regenerate_token($id); flash('success', 'Takip linki yenilendi.'); }
    elseif ($do === 'disable') { rma_toggle_public($id, false); flash('success', 'Public takip kapatıldı.'); }
    elseif ($do === 'enable') { rma_toggle_public($id, true); flash('success', 'Public takip açıldı.'); }
    else { flash('error', 'Geçersiz işlem.'); }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}
http_response_code(303);
redirect('modules/rma/view.php?id=' . $id);
