<?php
declare(strict_types=1);
/** modules/leads/delete.php — Lead sil (yumuşak; sebep + denetim). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads_crm.php';
auth_boot();
require_permission('leads.delete');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$l = get_lead($id);
if (!$l) { flash('error', 'Lead bulunamadı.'); redirect('modules/leads/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $reason = trim((string) ($_POST['reason'] ?? ''));
    if (delete_lead($id, current_user_id(), $reason)) { log_activity('lead_delete', 'lead', $id, null, 'success', 'Lead çöp kutusuna taşındı'); flash('success', 'Lead çöp kutusuna taşındı.'); }
    else { flash('error', 'Silme başarısız.'); }
    http_response_code(303);
    redirect('modules/leads/index.php');
}
layout_top('Lead Sil', 'leads');
?>
<div class="page-head"><h1 class="page-title">Lead Sil</h1></div>
<?= render_flashes() ?>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e($l['company_name']) ?></strong> lead kaydı çöp kutusuna taşınacak. Çöp kutusundan geri yükleyebilir veya kalıcı silebilirsiniz.</p>
    <form method="post" action="<?= e(url('modules/leads/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-group"><label for="reason">Silme sebebi (opsiyonel)</label><input type="text" id="reason" name="reason" placeholder="Örn. yanlış kayıt, mükerrer"></div>
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Çöp Kutusuna Taşı</button><a class="btn" href="<?= e(url('modules/leads/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
