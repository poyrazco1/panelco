<?php
declare(strict_types=1);
/** modules/international-customers/brand-add.php — Müşteriye marka ilişkisi ekle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.edit_brands');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/international-customers/index.php'); }
csrf_check();
$cid = (int) ($_POST['customer_id'] ?? 0);
$c = ic_get($cid);
if (!$c) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/international-customers/index.php'); }
$name = trim((string) ($_POST['brand_name'] ?? ''));
$rel  = (string) ($_POST['relation_type'] ?? 'interested');
// Var olan markaya eşle (opsiyonel)
$bid = null;
try { $st = db()->prepare('SELECT id FROM brands WHERE name = :n LIMIT 1'); $st->execute([':n' => $name]); $bid = ($v = $st->fetchColumn()) ? (int) $v : null; } catch (Throwable $e) {}
if (ic_brand_add($cid, $name, $rel, $bid, current_user_id())) { flash('success', 'Marka eklendi.'); }
else { flash('error', 'Marka eklenemedi.'); }
redirect('modules/international-customers/view.php?id=' . $cid);
