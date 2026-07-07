<?php
declare(strict_types=1);
/** modules/shipments/delete.php — Sevkiyat sil (yumuşak; yalnız yetkili). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';
auth_boot();
require_permission('shipments.delete');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$s = get_shipment($id);
if (!$s) { flash('error', 'Sevkiyat bulunamadı.'); redirect('modules/shipments/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_shipment($id, current_user_id())) { log_activity('shipment_delete', 'shipment', $id, (string) $s['shipment_no'], 'success', 'Sevkiyat silindi'); flash('success', 'Sevkiyat silindi.'); }
    else { flash('error', 'Silme başarısız.'); }
    redirect('modules/shipments/index.php');
}
layout_top('Sevkiyat Sil', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Sevkiyat Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e((string) $s['shipment_no']) ?></strong> sevkiyatını silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/shipments/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/shipments/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
