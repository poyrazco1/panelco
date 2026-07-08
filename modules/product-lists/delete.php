<?php
declare(strict_types=1);
/** modules/product-lists/delete.php — Ürün listesi sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/product-lists.php';
auth_boot();
require_permission('product_lists.delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$pl = pl_get($id);
if (!$pl) { flash('error', 'Liste bulunamadı.'); redirect('modules/product-lists/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (pl_delete($id, current_user_id())) {
        log_activity('product_list_delete', 'product_list', $id, (string) $pl['list_no'], 'success', 'Ürün listesi silindi');
        flash('success', 'Liste silindi.');
    } else { flash('error', 'Silme başarısız.'); }
    redirect('modules/product-lists/index.php');
}

layout_top('Liste Sil', 'product_lists');
?>
<div class="page-head"><h1 class="page-title">Liste Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e($pl['title']) ?></strong> (<?= e($pl['list_no']) ?>) listesini silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/product-lists/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/product-lists/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
