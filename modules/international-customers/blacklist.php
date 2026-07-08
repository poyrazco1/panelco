<?php
declare(strict_types=1);
/** modules/international-customers/blacklist.php — Kara liste aç/kapat (yalnızca yetkili). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.blacklist');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/international-customers/index.php'); }
csrf_check();
$cid = (int) ($_POST['customer_id'] ?? 0);
$c = ic_get($cid);
if (!$c) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/international-customers/index.php'); }
$on = (int) ($_POST['on'] ?? 0) === 1;
if (ic_set_blacklist($cid, $on, current_user_id(), (string) ($_POST['reason'] ?? ''))) {
    log_activity('intl_blacklist', 'international_customer', $cid, (string) $c['record_no'], 'success', $on ? 'Kara listeye alındı' : 'Kara listeden çıkarıldı');
    flash('success', $on ? 'Müşteri kara listeye alındı.' : 'Müşteri kara listeden çıkarıldı.');
} else { flash('error', 'İşlem başarısız.'); }
redirect('modules/international-customers/view.php?id=' . $cid);
