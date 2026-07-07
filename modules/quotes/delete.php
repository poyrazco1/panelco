<?php
declare(strict_types=1);

/** modules/quotes/delete.php — Teklif sil (yumuşak). GET onay, POST + CSRF sil. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/quotes.php';

auth_boot();
require_permission('quotes.delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$q  = get_quote($id);
if (!$q) { flash('error', 'Teklif bulunamadı.'); redirect('modules/quotes/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_quote($id, current_user_id())) {
        log_activity('quote_delete', 'quote', $id, (string) $q['quote_no'], 'success', 'Teklif silindi');
        flash('success', 'Teklif silindi.');
    } else {
        flash('error', 'Silme başarısız.');
    }
    redirect('modules/quotes/index.php');
}

layout_top('Teklif Sil', 'quotes');
?>
<div class="page-head"><h1 class="page-title">Teklif Sil</h1></div>
<div class="card" style="max-width:520px">
    <div class="card-body">
        <p><strong><?= e((string) $q['quote_no']) ?></strong> teklifini silmek istediğinize emin misiniz?</p>
        <form method="post" action="<?= e(url('modules/quotes/delete.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="form-actions">
                <button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button>
                <a class="btn" href="<?= e(url('modules/quotes/view.php?id=' . $id)) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>
<?php layout_bottom();
