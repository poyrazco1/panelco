<?php
declare(strict_types=1);
/** modules/shipments/address-edit.php — Sevkiyat adresi düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipment_addresses.edit');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$a = get_shipment_address($id);
if (!$a) { flash('error', 'Adres bulunamadı.'); redirect('modules/shipments/addresses.php'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = shipment_address_fields($_POST);
    $errors = shipment_address_validate($d);
    if (!$errors) {
        if (update_shipment_address($id, $d, current_user_id())) { log_activity('shipment_address_edit', 'shipment_address', $id, null, 'success', 'Sevkiyat adresi güncellendi'); flash('success', 'Adres güncellendi.'); redirect('modules/shipments/addresses.php'); }
        $errors[] = 'Güncelleme başarısız.';
    }
    $a = array_merge($a, $d);
}
layout_top('Sevkiyat Adresi Düzenle', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Sevkiyat Adresi Düzenle</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/shipments/addresses.php')) ?>">← Adresler</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/shipments/address-edit.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_address-form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/shipments/addresses.php')) ?>">Vazgeç</a>
    <?php if (can('shipment_addresses.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/shipments/address-delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?></div>
</form>
<?php layout_bottom();
