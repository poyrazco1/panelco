<?php
declare(strict_types=1);
/** modules/leads/delete.php — Lead sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';
auth_boot();
require_permission('leads.delete');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$l = get_lead($id);
if (!$l) { flash('error', 'Lead bulunamadı.'); redirect('modules/leads/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_lead($id, current_user_id())) { log_activity('lead_delete', 'lead', $id, null, 'success', 'Lead silindi'); flash('success', 'Lead silindi.'); }
    else { flash('error', 'Silme başarısız.'); }
    redirect('modules/leads/index.php');
}
layout_top('Lead Sil', 'leads');
?>
<div class="page-head"><h1 class="page-title">Lead Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e($l['company_name']) ?></strong> lead kaydını silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/leads/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/leads/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
