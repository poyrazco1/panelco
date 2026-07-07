<?php
declare(strict_types=1);
/** modules/orders/delete.php — Sipariş sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/orders.php';
auth_boot();
require_permission('orders.delete');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$o = get_order($id);
if (!$o) { flash('error', 'Sipariş bulunamadı.'); redirect('modules/orders/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_order($id, current_user_id())) { log_activity('order_delete', 'order', $id, (string) $o['order_no'], 'success', 'Sipariş silindi'); flash('success', 'Sipariş silindi.'); }
    else { flash('error', 'Silme başarısız.'); }
    redirect('modules/orders/index.php');
}
layout_top('Sipariş Sil', 'orders');
?>
<div class="page-head"><h1 class="page-title">Sipariş Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e((string) $o['order_no']) ?></strong> siparişini silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/orders/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/orders/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
