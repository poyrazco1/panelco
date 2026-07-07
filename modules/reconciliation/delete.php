<?php
declare(strict_types=1);

/** modules/reconciliation/delete.php — Mutabakat sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation.php';

auth_boot();
require_permission('reconciliation.delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$r  = get_reconciliation($id);
if (!$r) { flash('error', 'Mutabakat bulunamadı.'); redirect('modules/reconciliation/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_reconciliation($id, current_user_id())) {
        log_activity('reconciliation_delete', 'reconciliation', $id, (string) $r['recon_no'], 'success', 'Mutabakat silindi');
        flash('success', 'Mutabakat silindi.');
    } else {
        flash('error', 'Silme başarısız.');
    }
    redirect('modules/reconciliation/index.php');
}

layout_top('Mutabakat Sil', 'reconciliation');
?>
<div class="page-head"><h1 class="page-title">Mutabakat Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e((string) $r['recon_no']) ?></strong> kaydını silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/reconciliation/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/reconciliation/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
