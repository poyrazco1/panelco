<?php
declare(strict_types=1);

/**
 * modules/settings/personnel-create.php
 * Yeni personel ekleme. POST sonrası 303 redirect.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/personnel.php';

auth_boot();
require_permission('personnel');

$errors = [];
$p = ['employment_status' => 'active', 'is_active' => 1, 'sort_order' => '0'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $p = $_POST; // formu tekrar doldurmak için

    try {
        $file = (!empty($_FILES['photo']['name'])) ? $_FILES['photo'] : null;
        create_personnel($_POST, $file);
        flash('success', 'Personel eklendi.');
        http_response_code(303);
        redirect('modules/settings/personnel.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$departments = personnel_departments();
$statuses    = personnel_status_labels();
$users       = personnel_user_options();

layout_top('Yeni Personel', 'personnel');
?>

<div class="page-head">
    <h1 class="page-title">Yeni Personel</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/personnel.php')) ?>">← Personeller</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<?php
$formAction  = url('modules/settings/personnel-create.php');
$submitLabel = 'Ekle';
$isEdit = false;
include __DIR__ . '/_personnel-form.php';
?>

<?php
layout_bottom();
