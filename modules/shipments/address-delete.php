<?php
declare(strict_types=1);
/** modules/shipments/address-delete.php — Adres sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipment_addresses.delete');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$a = get_shipment_address($id);
if (!$a) { flash('error', 'Adres bulunamadı.'); redirect('modules/shipments/addresses.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_shipment_address($id, current_user_id())) { log_activity('shipment_address_delete', 'shipment_address', $id, null, 'success', 'Sevkiyat adresi silindi'); flash('success', 'Adres silindi.'); }
    else { flash('error', 'Silme başarısız.'); }
    redirect('modules/shipments/addresses.php');
}
layout_top('Adres Sil', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Sevkiyat Adresi Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e($a['company_name']) ?></strong> adresini silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/shipments/address-delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/shipments/addresses.php')) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
