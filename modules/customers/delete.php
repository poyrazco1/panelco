<?php
declare(strict_types=1);

/**
 * modules/customers/delete.php
 * Müşteri silme (yumuşak silme). GET → onay, POST + CSRF → sil.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/customers.php';

auth_boot();
require_permission('customers.delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$c  = get_customer_by_id($id);
if (!$c) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/customers/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_customer($id, current_user_id())) {
        log_activity('customer_delete', 'customer', $id, null, 'success', 'Müşteri silindi: ' . $c['company_name']);
        flash('success', 'Müşteri silindi.');
    } else {
        flash('error', 'Silme başarısız.');
    }
    redirect('modules/customers/index.php');
}

layout_top('Müşteri Sil', 'customers');
?>
<div class="page-head"><h1 class="page-title">Müşteri Sil</h1></div>
<div class="card" style="max-width:520px">
    <div class="card-body">
        <p><strong><?= e($c['company_name']) ?></strong> kaydını silmek istediğinize emin misiniz? Bu işlem kaydı listelerden kaldırır.</p>
        <form method="post" action="<?= e(url('modules/customers/delete.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="form-actions">
                <button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button>
                <a class="btn" href="<?= e(url('modules/customers/view.php?id=' . $id)) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>
<?php
layout_bottom();
