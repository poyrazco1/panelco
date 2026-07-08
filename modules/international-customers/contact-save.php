<?php
declare(strict_types=1);
/** modules/international-customers/contact-save.php — Kişi ekle/güncelle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();

$cid = (int) ($_POST['customer_id'] ?? 0);
$ctId = (int) ($_POST['id'] ?? 0) ?: null;
require_permission($ctId ? 'international_customers.edit_contacts' : 'international_customers.create_contacts');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/international-customers/index.php'); }
csrf_check();
$c = ic_get($cid);
if (!$c) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/international-customers/index.php'); }

if (ic_contact_save($cid, $_POST, $ctId, current_user_id())) {
    log_activity('intl_contact_save', 'international_customer', $cid, (string) $c['record_no'], 'success', 'Kişi kaydedildi');
    flash('success', 'Kişi kaydedildi.');
} else {
    flash('error', 'Kişi kaydedilemedi (ad soyad zorunlu).');
}
redirect('modules/international-customers/view.php?id=' . $cid);
