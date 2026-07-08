<?php
declare(strict_types=1);
/** modules/international-customers/status-change.php — Durum değiştir + log. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.status_change');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/international-customers/index.php'); }
csrf_check();
$cid = (int) ($_POST['customer_id'] ?? 0);
$c = ic_get($cid);
if (!$c) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/international-customers/index.php'); }
$sid = (int) ($_POST['status_id'] ?? 0) ?: null;
if (ic_set_status($cid, $sid, current_user_id(), (string) ($_POST['note'] ?? ''))) {
    log_activity('intl_status_change', 'international_customer', $cid, (string) $c['record_no'], 'success', 'Durum güncellendi');
    flash('success', 'Durum güncellendi.');
} else { flash('error', 'Durum güncellenemedi.'); }
redirect('modules/international-customers/view.php?id=' . $cid);
