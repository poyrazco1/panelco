<?php
declare(strict_types=1);

/**
 * modules/settings/personnel-edit.php
 * Personel düzenleme + fotoğraf değiştir/sil. POST sonrası 303 redirect.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/personnel.php';

auth_boot();
require_permission('personnel');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$record = get_personnel_by_id($id);
if (!$record) {
    flash('error', 'Personel bulunamadı.');
    http_response_code(303);
    redirect('modules/settings/personnel.php');
}

$errors = [];
$p = $record;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Hata olursa formu POST değerleriyle doldur (mevcut fotoğrafı koru)
    $p = $_POST + ['photo_path' => $record['photo_path'], 'full_name' => $record['full_name'], 'id' => $id];

    try {
        $file = (!empty($_FILES['photo']['name'])) ? $_FILES['photo'] : null;
        update_personnel($id, $_POST, $file);
        flash('success', 'Personel güncellendi.');
        http_response_code(303);
        redirect('modules/settings/personnel.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$departments = personnel_departments();
$statuses    = personnel_status_labels();
$users       = personnel_user_options();

layout_top('Personel Düzenle', 'personnel');
?>

<div class="page-head">
    <h1 class="page-title">Personel Düzenle</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/personnel.php')) ?>">← Personeller</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<?php
$formAction  = url('modules/settings/personnel-edit.php');
$submitLabel = 'Kaydet';
$isEdit = true;
include __DIR__ . '/_personnel-form.php';
?>

<div class="card" style="max-width:820px;border-color:#F0D9D9">
    <div class="card-header"><h2>Tehlikeli bölge</h2></div>
    <div class="card-body">
        <p class="muted">Bu personeli kalıcı olarak siler. Fotoğrafı da kaldırılır.</p>
        <form method="post" action="<?= e(url('modules/settings/personnel-delete.php')) ?>"
              data-confirm="Bu personeli silmek istediğinize emin misiniz?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Personeli sil</button>
        </form>
    </div>
</div>

<?php
layout_bottom();
