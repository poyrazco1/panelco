<?php
declare(strict_types=1);
/** modules/shipments/address-create.php — Yeni sevkiyat adresi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipment_addresses.create');
$errors = [];
$a = ['is_active' => 1, 'address_type' => 'delivery'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = shipment_address_fields($_POST);
    $errors = shipment_address_validate($a);
    if (!$errors) {
        $id = create_shipment_address($a, current_user_id());
        if ($id > 0) { log_activity('shipment_address_create', 'shipment_address', $id, null, 'success', 'Sevkiyat adresi eklendi: ' . $a['company_name']); flash('success', 'Adres eklendi.'); redirect('modules/shipments/addresses.php'); }
        $errors[] = 'Adres kaydedilemedi.';
    }
}
layout_top('Yeni Sevkiyat Adresi', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Yeni Sevkiyat Adresi</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/shipments/addresses.php')) ?>">← Adresler</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/shipments/address-create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_address-form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/shipments/addresses.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
