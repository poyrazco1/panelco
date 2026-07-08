<?php
declare(strict_types=1);
/** modules/international-customers/filter-delete.php — Kayıtlı filtre sil. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.filter');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/international-customers/index.php'); }
csrf_check();
$fid = (int) ($_POST['id'] ?? 0);
if (ic_saved_filter_delete($fid, current_user_id())) { flash('success', 'Filtre silindi.'); }
redirect('modules/international-customers/index.php');
