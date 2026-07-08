<?php
declare(strict_types=1);
/** modules/international-customers/contact-delete.php — Kişi sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.delete_contacts');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/international-customers/index.php'); }
csrf_check();
$cid = (int) ($_POST['customer_id'] ?? 0);
$id  = (int) ($_POST['id'] ?? 0);
if (ic_contact_delete($id, $cid, current_user_id())) { flash('success', 'Kişi silindi.'); }
else { flash('error', 'Silme başarısız.'); }
redirect('modules/international-customers/view.php?id=' . $cid);
