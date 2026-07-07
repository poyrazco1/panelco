<?php
declare(strict_types=1);

/**
 * modules/settings/shipping-create.php
 * Yeni kargo yöntemi ekleme (logo yükleme dahil). POST sonrası 303 redirect.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipping.php';

auth_boot();
require_permission('shipping');

$errors   = [];
$name     = '';
$freeLim  = '';
$sortOrd  = '0';
$isActive = 1;
$desc     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $freeLim  = trim($_POST['free_shipping_limit'] ?? '');
    $sortOrd  = trim($_POST['sort_order'] ?? '0');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $desc     = trim($_POST['description'] ?? '');

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Kargo adı en az 2 karakter olmalıdır.';
    }
    $freeVal = ($freeLim === '') ? null : (float) str_replace(',', '.', $freeLim);
    if ($freeLim !== '' && (!is_numeric(str_replace(',', '.', $freeLim)) || $freeVal < 0)) {
        $errors[] = 'Ücretsiz kargo limiti geçerli, negatif olmayan bir sayı olmalıdır.';
    }
    $sortVal = (int) $sortOrd;

    // Logo (opsiyonel)
    $logoPath = null;
    if (!$errors) {
        $logoErr = null;
        $logoPath = shipping_upload_logo($_FILES['logo'] ?? [], $logoErr);
        if ($logoErr !== null) {
            $errors[] = $logoErr;
        }
    }

    if (!$errors) {
        try {
            $code = shipping_unique_code($name);
            db()->prepare(
                'INSERT INTO shipping_methods
                    (name, code, logo_path, pricing_type, free_shipping_limit, sort_order, is_active, description)
                 VALUES (:n, :c, :l, :pt, :f, :s, :a, :d)'
            )->execute([
                ':n' => $name, ':c' => $code, ':l' => $logoPath, ':pt' => 'desi',
                ':f' => $freeVal, ':s' => $sortVal, ':a' => $isActive,
                ':d' => ($desc !== '' ? $desc : null),
            ]);
            flash('success', 'Kargo yöntemi eklendi. Şimdi desi ücretlerini girebilirsiniz.');
            $newId = (int) db()->lastInsertId();
            http_response_code(303);
            redirect('modules/settings/shipping-edit.php?id=' . $newId);
        } catch (Throwable $e) {
            // Logo yüklendiyse geri al
            shipping_delete_logo($logoPath);
            $errors[] = safe_error('Kargo eklenemedi.', 'shipping-create: ' . $e->getMessage());
        }
    } else {
        // Hata varsa yüklenen logoyu bırakma
        shipping_delete_logo($logoPath ?? null);
    }
}

layout_top('Yeni Kargo', 'shipping');
?>

<div class="page-head">
    <h1 class="page-title">Yeni Kargo Yöntemi</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/shipping.php')) ?>">← Kargolar</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:680px">
    <div class="card-body">
        <form method="post" action="<?= e(url('modules/settings/shipping-create.php')) ?>"
              enctype="multipart/form-data" data-lock-on-submit novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Kargo adı</label>
                <input type="text" id="name" name="name" value="<?= e($name) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="free_shipping_limit">Ücretsiz kargo limiti (TL)</label>
                    <input type="text" id="free_shipping_limit" name="free_shipping_limit"
                           inputmode="decimal" value="<?= e($freeLim) ?>" placeholder="örn. 750">
                    <div class="field-hint">Boş bırakılabilir.</div>
                </div>
                <div class="form-group">
                    <label for="sort_order">Sıra no</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= e($sortOrd) ?>" step="1">
                </div>
            </div>

            <div class="form-group">
                <label for="logo">Logo (PNG, JPG, WEBP — en fazla 2 MB)</label>
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
            </div>

            <div class="form-group">
                <label for="description">Açıklama / not</label>
                <textarea id="description" name="description" rows="2"><?= e($desc) ?></textarea>
            </div>

            <div class="form-group">
                <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:220px">
                    <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> style="width:auto">
                    <span>Aktif</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Ekle</button>
                <a class="btn" href="<?= e(url('modules/settings/shipping.php')) ?>">Vazgeç</a>
            </div>
            <p class="field-hint" style="margin-top:10px">Ücretlendirme kademelerini ekledikten sonra düzenleme sayfasında girersiniz.</p>
        </form>
    </div>
</div>

<?php
layout_bottom();
