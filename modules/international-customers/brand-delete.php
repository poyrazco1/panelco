<?php
declare(strict_types=1);
/** modules/international-customers/brand-delete.php — Marka ilişkisi kaldır. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.edit_brands');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/international-customers/index.php'); }
csrf_check();
$cid = (int) ($_POST['customer_id'] ?? 0);
$id  = (int) ($_POST['id'] ?? 0);
if (ic_brand_delete($id, $cid)) { flash('success', 'Marka kaldırıldı.'); }
redirect('modules/international-customers/view.php?id=' . $cid);
