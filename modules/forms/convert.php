<?php
declare(strict_types=1);
/**
 * modules/forms/convert.php — Form kaydını ilgili modüle dönüştür / ilişkilendir.
 * Duplicate engellenir (converted_at/related_record_id). Hata olursa form bozulmaz.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';
auth_boot();
require_permission('forms.submissions.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/forms/submissions.php'); }
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'convert');

if ($action === 'relate_shipment') {
    if (!can('shipments.edit')) { flash('error', 'Bu işlem için yetkiniz yok.'); redirect('modules/forms/submission-view.php?id=' . $id); }
    $stopId = (int) ($_POST['stop_id'] ?? 0);
    $res = form_center_relate_shipment($id, $stopId, current_user_id());
    flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Sevkiyat durağıyla ilişkilendirildi.' : (string) $res['error']);
    http_response_code(303);
    redirect('modules/forms/submission-view.php?id=' . $id);
}

$res = form_center_convert_submission($id, current_user_id());
if ($res['ok']) {
    flash('success', 'Kayıt oluşturuldu: ' . $res['module'] . ' #' . $res['record_id'] . '.');
} else {
    flash('error', (string) $res['error']);
}
http_response_code(303);
redirect('modules/forms/submission-view.php?id=' . $id);
