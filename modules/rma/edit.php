<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/rma.php';
require_once __DIR__ . '/../../includes/shipping.php';
require_once __DIR__ . '/../../includes/brands.php';
auth_boot();
require_permission('rma');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$record = get_rma_record($id);
if (!$record) { flash('error', 'Kayıt bulunamadı.'); http_response_code(303); redirect('modules/rma/index.php'); }
$errors = [];
$r = $record;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $r = $_POST + ['id' => $id];
    try {
        update_rma_record($id, $_POST);
        flash('success', 'Kayıt güncellendi.');
        http_response_code(303);
        redirect('modules/rma/view.php?id=' . $id);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
$platforms = rma_platforms();
$types = rma_process_types();
$reasons = rma_reason_types();
$statuses = rma_statuses();
$cargoOptions = rma_cargo_options();
$brands = get_active_brand_options();
$receivedItems = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (array) ($_POST['ritem'] ?? []) : get_rma_received_items($id);
$sentItems = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (array) ($_POST['sitem'] ?? []) : get_rma_sent_items($id);
layout_top('İade-Değişim Düzenle', 'rma');
?>
<div class="page-head">
    <h1 class="page-title">İade-Değişim Düzenle</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/rma/view.php?id=' . $id)) ?>">← Detay</a>
    </div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<?php
$formAction = url('modules/rma/edit.php?id=' . $id);
$submitLabel = 'Kaydet';
$isEdit = true;
include __DIR__ . '/_rma-form.php';
?>
<div class="card" style="max-width:900px;border-color:#F0D9D9">
    <div class="card-header"><h2>Tehlikeli bölge</h2></div>
    <div class="card-body">
        <p class="muted">Bu kayıt arşive taşınır (ürün satırları ve durum geçmişi korunur). Liste ve public takipten kalkar.</p>
        <form method="post" action="<?= e(url('modules/rma/delete.php')) ?>"
              data-confirm="<?= e("Bu kayıt arşive taşınacaktır.\n\nReferans No: " . ($record['reference_code'] ?? '—') . "\nMüşteri: " . ($record['customer_name'] ?? '—') . "\n\nDevam edilsin mi?") ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash-2') ?>Kaydı sil (arşivle)</button>
        </form>
    </div>
</div>
<?php
layout_bottom();
