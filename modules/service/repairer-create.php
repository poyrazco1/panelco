<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';
auth_boot();
require_permission('service');
$errors = [];
$r = ['is_active' => 1];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $r = $_POST;
    try {
        save_repairer($_POST);
        flash('success', 'Dış tamirci eklendi.');
        http_response_code(303);
        redirect('modules/service/repairers.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
$specialties = ['Yazıcı Tamiri','Fotokopi Tamiri','Fuser Bakımı','Anakart Tamiri','Mekanik Onarım','Genel Tamir'];
layout_top('Yeni Dış Tamirci', 'service');
?>
<div class="page-head">
    <h1 class="page-title">Yeni Dış Tamirci</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/service/repairers.php')) ?>">← Tamirciler</a></div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<?php
$formAction = url('modules/service/repairer-create.php');
$submitLabel = 'Ekle';
$isEdit = false;
include __DIR__ . '/_repairer-form.php';
layout_bottom();
