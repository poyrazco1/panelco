<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/rma.php';
require_once __DIR__ . '/../../includes/shipping.php';
require_once __DIR__ . '/../../includes/brands.php';
auth_boot();
require_permission('rma');
$errors = [];
$r = ['process_date' => date('Y-m-d'), 'process_type' => 'iade', 'status' => 'requested', 'quantity' => 1, 'loss_amount' => '0.00'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $r = $_POST;
    try {
        $id = create_rma_record($_POST);
        flash('success', 'İade-değişim kaydı oluşturuldu.');
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
$receivedItems = [];
$sentItems = [];
layout_top('Yeni İade-Değişim Kaydı', 'rma');
?>
<div class="page-head">
    <h1 class="page-title">Yeni İade-Değişim Kaydı</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/rma/index.php')) ?>">← Liste</a></div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<?php
$formAction = url('modules/rma/create.php');
$submitLabel = 'Kaydı Oluştur';
$isEdit = false;
include __DIR__ . '/_rma-form.php';
layout_bottom();
