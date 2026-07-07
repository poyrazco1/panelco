<?php
declare(strict_types=1);

/**
 * modules/settings/brand-create.php
 * Yeni marka ekleme. POST sonrası 303 redirect.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/brands.php';

auth_boot();
require_permission('brands');

$errors   = [];
$name     = '';
$code     = '';
$sortOrd  = '0';
$isActive = 1;
$desc     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $code     = trim($_POST['code'] ?? '');
    $sortOrd  = trim($_POST['sort_order'] ?? '0');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $desc     = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = 'Marka adı boş olamaz.';
    } elseif (brand_name_exists($name)) {
        $errors[] = 'Bu isimde bir marka zaten var.';
    }
    if ($sortOrd !== '' && !preg_match('/^-?\d+$/', $sortOrd)) {
        $errors[] = 'Sıra no bir sayı olmalıdır.';
    }

    if (!$errors) {
        try {
            $file = (!empty($_FILES['logo']['name'])) ? $_FILES['logo'] : null;
            $newId = create_brand([
                'name' => $name, 'code' => $code, 'description' => $desc,
                'sort_order' => (int) $sortOrd, 'is_active' => $isActive,
            ], $file);
            flash('success', 'Marka eklendi.');
            http_response_code(303);
            redirect('modules/settings/brands.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

layout_top('Yeni Marka', 'brands');
?>

<div class="page-head">
    <h1 class="page-title">Yeni Marka</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/brands.php')) ?>">← Markalar</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:680px">
    <div class="card-body">
        <form method="post" action="<?= e(url('modules/settings/brand-create.php')) ?>"
              enctype="multipart/form-data" data-lock-on-submit novalidate>
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Marka adı</label>
                    <input type="text" id="name" name="name" value="<?= e($name) ?>" required>
                </div>
                <div class="form-group">
                    <label for="code">Marka kodu</label>
                    <input type="text" id="code" name="code" value="<?= e($code) ?>" placeholder="boş = addan üretilir">
                    <div class="field-hint">Slug ad'dan otomatik üretilir.</div>
                </div>
            </div>

            <div class="form-group">
                <label for="logo">Logo (PNG, JPG, JPEG, WEBP — en fazla 2 MB)</label>
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
            </div>

            <div class="form-group">
                <label for="description">Açıklama</label>
                <textarea id="description" name="description" rows="2"><?= e($desc) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="sort_order">Sıra no</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= e($sortOrd) ?>" step="1">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:220px">
                        <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> style="width:auto">
                        <span>Aktif</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Ekle</button>
                <a class="btn" href="<?= e(url('modules/settings/brands.php')) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>

<?php
layout_bottom();
