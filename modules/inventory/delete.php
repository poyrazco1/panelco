<?php
declare(strict_types=1);

/** modules/inventory/delete.php — Demirbaş sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/inventory.php';

auth_boot();
require_permission('inventory.delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$it = get_inventory_item($id);
if (!$it) { flash('error', 'Demirbaş bulunamadı.'); redirect('modules/inventory/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_inventory_item($id, current_user_id())) {
        log_activity('inventory_delete', 'inventory', $id, null, 'success', 'Demirbaş silindi: ' . $it['name']);
        flash('success', 'Demirbaş silindi.');
    } else {
        flash('error', 'Silme başarısız.');
    }
    redirect('modules/inventory/index.php');
}

layout_top('Demirbaş Sil', 'inventory');
?>
<div class="page-head"><h1 class="page-title">Demirbaş Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e($it['name']) ?></strong> demirbaşını silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/inventory/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/inventory/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
