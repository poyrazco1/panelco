<?php
declare(strict_types=1);

/** modules/commissions/delete.php — Prim sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/commissions.php';

auth_boot();
require_permission('commissions.delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$c  = get_commission($id);
if (!$c) { flash('error', 'Prim kaydı bulunamadı.'); redirect('modules/commissions/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_commission($id, current_user_id())) {
        log_activity('commission_delete', 'commission', $id, null, 'success', 'Prim silindi');
        flash('success', 'Prim silindi.');
    } else {
        flash('error', 'Silme başarısız.');
    }
    redirect('modules/commissions/index.php');
}

layout_top('Prim Sil', 'commissions');
?>
<div class="page-head"><h1 class="page-title">Prim Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e((string) ($c['personnel_name'] ?? '')) ?></strong> prim kaydını silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/commissions/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/commissions/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
