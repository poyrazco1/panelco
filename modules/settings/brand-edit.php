<?php
declare(strict_types=1);

/**
 * modules/settings/brand-edit.php
 * Marka düzenleme + logo değiştir/sil. POST sonrası 303 redirect.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/brands.php';

auth_boot();
require_permission('brands');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Geçersiz marka.');
    redirect('modules/settings/brands.php');
}

$brand = get_brand_by_id($id);
if (!$brand) {
    flash('error', 'Marka bulunamadı.');
    redirect('modules/settings/brands.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $code     = trim($_POST['code'] ?? '');
    $sortOrd  = trim($_POST['sort_order'] ?? '0');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $desc     = trim($_POST['description'] ?? '');
    $removeLogo = isset($_POST['remove_logo']);

    if ($name === '') {
        $errors[] = 'Marka adı boş olamaz.';
    } elseif (brand_name_exists($name, $id)) {
        $errors[] = 'Bu isimde başka bir marka var.';
    }
    if ($sortOrd !== '' && !preg_match('/^-?\d+$/', $sortOrd)) {
        $errors[] = 'Sıra no bir sayı olmalıdır.';
    }

    if (!$errors) {
        try {
            $file = (!empty($_FILES['logo']['name'])) ? $_FILES['logo'] : null;
            update_brand($id, [
                'name' => $name, 'code' => $code, 'description' => $desc,
                'sort_order' => (int) $sortOrd, 'is_active' => $isActive,
                'remove_logo' => $removeLogo,
            ], $file);
            flash('success', 'Marka güncellendi.');
            http_response_code(303);
            redirect('modules/settings/brands.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    // Hata varsa formu POST değerleriyle doldur
    $brand['name'] = $name;
    $brand['code'] = $code;
    $brand['sort_order'] = $sortOrd;
    $brand['is_active'] = $isActive;
    $brand['description'] = $desc;
}

layout_top('Marka Düzenle', 'brands');
?>

<div class="page-head">
    <h1 class="page-title">Marka Düzenle</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/brands.php')) ?>">← Markalar</a>
        <form method="post" action="<?= e(url('modules/settings/brand-delete.php')) ?>" style="display:inline"
              data-confirm="“<?= e($brand['name']) ?>” markasını silmek istediğinize emin misiniz?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="submit" class="btn btn-danger btn-sm">Sil</button>
        </form>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:680px">
    <div class="card-body">
        <form method="post" action="<?= e(url('modules/settings/brand-edit.php')) ?>"
              enctype="multipart/form-data" data-lock-on-submit novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <div class="form-group">
                <label>Mevcut logo</label>
                <div style="display:flex;align-items:center;gap:12px">
                    <?= brand_logo_html($brand['logo_path'] ?? null, (string) $brand['name']) ?>
                    <?php if (!empty($brand['logo_path'])): ?>
                        <label class="small" style="display:flex;align-items:center;gap:6px;font-weight:500">
                            <input type="checkbox" name="remove_logo" value="1" style="width:auto">
                            Logoyu kaldır
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Marka adı</label>
                    <input type="text" id="name" name="name" value="<?= e($brand['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="code">Marka kodu</label>
                    <input type="text" id="code" name="code" value="<?= e((string) ($brand['code'] ?? '')) ?>">
                    <div class="field-hint">Mevcut slug: <?= e($brand['slug']) ?> (ad değişince yenilenir).</div>
                </div>
            </div>

            <div class="form-group">
                <label for="logo">Logoyu değiştir (PNG, JPG, JPEG, WEBP — en fazla 2 MB)</label>
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
            </div>

            <div class="form-group">
                <label for="description">Açıklama</label>
                <textarea id="description" name="description" rows="2"><?= e((string) ($brand['description'] ?? '')) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="sort_order">Sıra no</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= e((string) $brand['sort_order']) ?>" step="1">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:220px">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $brand['is_active'] === 1 ? 'checked' : '' ?> style="width:auto">
                        <span>Aktif</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <a class="btn" href="<?= e(url('modules/settings/brands.php')) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>

<?php
layout_bottom();
