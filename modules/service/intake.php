<?php
declare(strict_types=1);

/**
 * modules/service/intake.php
 * Servis Kabul — yeni kayıt (ve ?id= ile düzenleme). POST sonrası 303 redirect.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';
require_once __DIR__ . '/../../includes/brands.php';
require_once __DIR__ . '/../../includes/personnel.php';

auth_boot();
require_permission('service');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;

$r = ['status' => 'new', 'approval_required' => 1, 'received_at' => date('Y-m-d'), 'quantity' => 1];
if ($isEdit) {
    $existing = get_service_record($id);
    if (!$existing) {
        flash('error', 'Servis kaydı bulunamadı.');
        http_response_code(303);
        redirect('modules/service/index.php');
    }
    $r = $existing;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $r = $_POST + (['received_photo_path' => $r['received_photo_path'] ?? null, 'reference_code' => $r['reference_code'] ?? null, 'id' => $id, 'profit_amount' => $r['profit_amount'] ?? null]);

    try {
        $file = (!empty($_FILES['photo']['name'])) ? $_FILES['photo'] : null;
        if ($isEdit) {
            update_service_record($id, $_POST, $file);
            flash('success', 'Servis kaydı güncellendi.');
            http_response_code(303);
            redirect('modules/service/view.php?id=' . $id);
        } else {
            $res = create_service_record($_POST, $file);
            flash('success', 'Servis kaydı oluşturuldu: ' . $res['reference_code']);
            http_response_code(303);
            redirect('modules/service/view.php?id=' . $res['id']);
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$brands    = get_active_brand_options();
$repairers = get_repairer_options(true);
$personnel = get_personnel_options(true);

layout_top($isEdit ? 'Servis Kaydı Düzenle' : 'Servis Kabul', 'service');
?>

<div class="page-head">
    <h1 class="page-title"><?= $isEdit ? 'Servis Kaydı Düzenle' : 'Servis Kabul — Yeni Kayıt' ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/service/index.php')) ?>">← Servis Kayıtları</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<?php
$formAction  = $isEdit ? url('modules/service/intake.php?id=' . $id) : url('modules/service/intake.php');
$submitLabel = $isEdit ? 'Kaydet' : 'Servis Kaydı Oluştur';
include __DIR__ . '/_service-form.php';
?>

<?php
layout_bottom();
