<?php
declare(strict_types=1);
/** modules/orders/status.php — Sipariş durumu değiştir. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/orders.php';
auth_boot();
require_permission('orders.status');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/orders/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$o = get_order($id);
if (!$o) { flash('error', 'Sipariş bulunamadı.'); redirect('modules/orders/index.php'); }
if (set_order_status($id, $status, current_user_id())) { log_activity('order_status', 'order', $id, (string) $o['order_no'], 'success', 'Durum: ' . order_status_label($status)); flash('success', 'Sipariş durumu güncellendi.'); }
else { flash('error', 'Durum güncellenemedi.'); }
redirect('modules/orders/view.php?id=' . $id);
