<?php
declare(strict_types=1);

/** modules/international-customers/delete.php — Yumuşak silme (GET onay, POST + CSRF sil). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';

auth_boot();
require_permission('international_customers.delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$c  = ic_get($id);
if (!$c) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/international-customers/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (ic_delete($id, current_user_id())) {
        log_activity('intl_customer_delete', 'international_customer', $id, (string) $c['record_no'], 'success', 'Yurtdışı müşteri silindi: ' . $c['company_name']);
        flash('success', 'Müşteri silindi.');
    } else {
        flash('error', 'Silme başarısız.');
    }
    redirect('modules/international-customers/index.php');
}

layout_top('Müşteri Sil', 'international_customers');
?>
<div class="page-head"><h1 class="page-title">Müşteri Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e($c['company_name']) ?></strong> (<?= e($c['record_no']) ?>) kaydını silmek istediğinize emin misiniz? Kayıt listelerden kaldırılır (kalıcı olarak silinmez).</p>
    <form method="post" action="<?= e(url('modules/international-customers/delete.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions">
            <button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button>
            <a class="btn" href="<?= e(url('modules/international-customers/view.php?id=' . $id)) ?>">Vazgeç</a>
        </div>
    </form>
</div></div>
<?php layout_bottom();
