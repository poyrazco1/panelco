<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';
auth_boot();
require_permission('service');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$rec = get_repairer_by_id($id);
if (!$rec) { flash('error', 'Tamirci bulunamadı.'); http_response_code(303); redirect('modules/service/repairers.php'); }
$errors = [];
$r = $rec;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $r = $_POST + ['id' => $id];
    try {
        save_repairer($_POST, $id);
        flash('success', 'Dış tamirci güncellendi.');
        http_response_code(303);
        redirect('modules/service/repairers.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
$specialties = ['Yazıcı Tamiri','Fotokopi Tamiri','Fuser Bakımı','Anakart Tamiri','Mekanik Onarım','Genel Tamir'];
layout_top('Dış Tamirci Düzenle', 'service');
?>
<div class="page-head">
    <h1 class="page-title">Dış Tamirci Düzenle</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/service/repairers.php')) ?>">← Tamirciler</a>
        <form method="post" action="<?= e(url('modules/service/repairer-delete.php')) ?>" style="display:inline" data-confirm="Bu tamirci silinsin mi?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="submit" class="btn btn-danger btn-sm">Sil</button>
        </form>
    </div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<?php
$formAction = url('modules/service/repairer-edit.php?id=' . $id);
$submitLabel = 'Kaydet';
$isEdit = true;
include __DIR__ . '/_repairer-form.php';
layout_bottom();
