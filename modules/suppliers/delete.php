<?php
declare(strict_types=1);

/** modules/suppliers/delete.php — Tedarikçi sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/suppliers.php';

auth_boot();
require_permission('suppliers.delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$s  = get_supplier_by_id($id);
if (!$s) { flash('error', 'Tedarikçi bulunamadı.'); redirect('modules/suppliers/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_supplier($id, current_user_id())) {
        log_activity('supplier_delete', 'supplier', $id, null, 'success', 'Tedarikçi silindi: ' . $s['company_name']);
        flash('success', 'Tedarikçi silindi.');
    } else {
        flash('error', 'Silme başarısız.');
    }
    redirect('modules/suppliers/index.php');
}

layout_top('Tedarikçi Sil', 'suppliers');
?>
<div class="page-head"><h1 class="page-title">Tedarikçi Sil</h1></div>
<div class="card" style="max-width:520px">
    <div class="card-body">
        <p><strong><?= e($s['company_name']) ?></strong> kaydını silmek istediğinize emin misiniz?</p>
        <form method="post" action="<?= e(url('modules/suppliers/delete.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="form-actions">
                <button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button>
                <a class="btn" href="<?= e(url('modules/suppliers/view.php?id=' . $id)) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>
<?php layout_bottom();
