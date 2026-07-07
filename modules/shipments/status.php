<?php
declare(strict_types=1);
/** modules/shipments/status.php — Sevkiyat durumu değiştir (yönetici veya atanan sevkiyatçı). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipments.status');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/shipments/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$s = get_shipment($id); // görünürlük kontrolü (sevkiyatçı yalnız kendi)
if (!$s) { flash('error', 'Sevkiyat bulunamadı veya yetkiniz yok.'); redirect('modules/shipments/index.php'); }
$status = (string) ($_POST['status'] ?? '');
$note = trim((string) ($_POST['note'] ?? ''));
$fail = (string) ($_POST['fail_reason'] ?? '');
if ($status === 'failed' && !isset(shipment_fail_reasons()[$fail])) { $fail = 'other'; }
if (set_shipment_status($id, $status, $note, current_user_id(), $status === 'failed' ? $fail : null)) {
    log_activity('shipment_status', 'shipment', $id, (string) $s['shipment_no'], 'success', 'Durum: ' . shipment_status_label($status) . ($status === 'failed' ? (' / ' . (shipment_fail_reasons()[$fail] ?? '')) : ''));
    flash('success', 'Durum güncellendi.');
} else { flash('error', 'Durum güncellenemedi.'); }
redirect('modules/shipments/view.php?id=' . $id);
