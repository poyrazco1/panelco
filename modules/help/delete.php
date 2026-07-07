<?php
declare(strict_types=1);
/** modules/help/delete.php — Yardım konusu sil. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/help.php';
auth_boot();
require_permission('help.delete');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$a = get_help_article($id);
if (!$a) { flash('error', 'Konu bulunamadı.'); redirect('modules/help/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_help_article($id, current_user_id())) { log_activity('help_delete', 'help', $id, null, 'success', 'Yardım konusu silindi'); flash('success', 'Konu silindi.'); }
    else { flash('error', 'Silme başarısız.'); }
    redirect('modules/help/index.php');
}
layout_top('Yardım Konusu Sil', 'help');
?>
<div class="page-head"><h1 class="page-title">Yardım Konusu Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e($a['title']) ?></strong> konusunu silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/help/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/help/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
