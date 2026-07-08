<?php
declare(strict_types=1);
/** modules/international-customers/filter-save.php — Kayıtlı filtre oluştur. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.filter');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/international-customers/index.php'); }
csrf_check();
$name = trim((string) ($_POST['name'] ?? ''));
$params = (array) ($_POST['params'] ?? []);
// Boş değerleri temizle
$clean = [];
foreach ($params as $k => $v) {
    $vals = array_values(array_filter((array) $v, static fn($x) => $x !== ''));
    if ($vals) { $clean[$k] = count($vals) === 1 && !in_array($k, ['country','company_role','status_id','company_type_id','category_id'], true) ? $vals[0] : $vals; }
}
$shared = !empty($_POST['is_shared']);
if ($name !== '' && ic_saved_filter_save($name, $clean, $shared, current_user_id()) > 0) {
    flash('success', 'Filtre kaydedildi: ' . $name);
} else { flash('error', 'Filtre kaydedilemedi.'); }
redirect('modules/international-customers/index.php?' . http_build_query($clean));
