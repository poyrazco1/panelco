<?php
declare(strict_types=1);
/** modules/shipments/edit.php — Sevkiyat düzenle (yalnızca yetkili yönetici). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipments.edit');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$s = get_shipment($id);
if (!$s) { flash('error', 'Sevkiyat bulunamadı.'); redirect('modules/shipments/index.php'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = shipment_fields_from_input($_POST);
    $errors = shipment_validate($d);
    if (!$errors) {
        if (update_shipment($id, $d, current_user_id())) { log_activity('shipment_edit', 'shipment', $id, (string) $s['shipment_no'], 'success', 'Sevkiyat güncellendi'); flash('success', 'Sevkiyat güncellendi.'); redirect('modules/shipments/view.php?id=' . $id); }
        $errors[] = 'Güncelleme başarısız.';
    }
    $s = array_merge($s, $d);
}
$addresses = get_shipment_addresses(['active' => 1]);
$couriers = get_personnel_options(false);
layout_top('Sevkiyat Düzenle', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Sevkiyat Düzenle · <?= e((string) $s['shipment_no']) ?></h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/shipments/view.php?id=' . $id)) ?>">← Detay</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/shipments/edit.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_shipment-form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/shipments/view.php?id=' . $id)) ?>">Vazgeç</a>
    <?php if (can('shipments.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/shipments/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?></div>
</form>
<?php layout_bottom();
