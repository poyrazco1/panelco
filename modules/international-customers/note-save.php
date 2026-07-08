<?php
declare(strict_types=1);
/** modules/international-customers/note-save.php — Müşteriye not ekle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.notes');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/international-customers/index.php'); }
csrf_check();
$cid = (int) ($_POST['customer_id'] ?? 0);
$c = ic_get($cid);
if (!$c) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/international-customers/index.php'); }
if (ic_note_add($cid, (string) ($_POST['body'] ?? ''), current_user_id())) { flash('success', 'Not eklendi.'); }
else { flash('error', 'Not boş olamaz.'); }
redirect('modules/international-customers/view.php?id=' . $cid);
