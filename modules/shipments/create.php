<?php
declare(strict_types=1);
/** modules/shipments/create.php — Yeni sevkiyat. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipments.create');
$errors = [];
$s = ['shipment_type' => 'delivery', 'priority' => 'normal', 'related_type' => 'manual', 'shipment_date' => date('Y-m-d')];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $s = shipment_fields_from_input($_POST);
    $errors = shipment_validate($s);
    if (!$errors) {
        $id = create_shipment($s, current_user_id());
        if ($id > 0) { log_activity('shipment_create', 'shipment', $id, null, 'success', 'Sevkiyat oluşturuldu'); flash('success', 'Sevkiyat oluşturuldu.'); redirect('modules/shipments/view.php?id=' . $id); }
        $errors[] = 'Sevkiyat kaydedilemedi.';
    }
}
$addresses = get_shipment_addresses(['active' => 1]);
$couriers = get_personnel_options(false);
layout_top('Yeni Sevkiyat', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Yeni Sevkiyat</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Sevkiyatlar</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/shipments/create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_shipment-form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/shipments/index.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
