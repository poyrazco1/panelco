<?php
declare(strict_types=1);

/**
 * modules/settings/shipping-edit.php
 * Kargo düzenleme + logo değiştir/sil + desi ücret kademeleri yönetimi.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipping.php';

auth_boot();
require_permission('shipping');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Geçersiz kargo.');
    redirect('modules/settings/shipping.php');
}

$method = shipping_find($id);
if (!$method) {
    flash('error', 'Kargo bulunamadı.');
    redirect('modules/settings/shipping.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $freeLim  = trim($_POST['free_shipping_limit'] ?? '');
    $sortOrd  = trim($_POST['sort_order'] ?? '0');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $desc     = trim($_POST['description'] ?? '');
    $removeLogo = isset($_POST['remove_logo']);

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Kargo adı en az 2 karakter olmalıdır.';
    }
    $freeVal = ($freeLim === '') ? null : (float) str_replace(',', '.', $freeLim);
    if ($freeLim !== '' && (!is_numeric(str_replace(',', '.', $freeLim)) || $freeVal < 0)) {
        $errors[] = 'Ücretsiz kargo limiti geçerli, negatif olmayan bir sayı olmalıdır.';
    }
    $sortVal = (int) $sortOrd;

    // Ücret satırları doğrula
    $priceRows = [];
    $mins = $_POST['min_desi'] ?? [];
    $maxs = $_POST['max_desi'] ?? [];
    $prcs = $_POST['price'] ?? [];
    if (is_array($mins)) {
        $count = count($mins);
        for ($i = 0; $i < $count; $i++) {
            $priceRows[] = [
                'min'   => $mins[$i] ?? '',
                'max'   => $maxs[$i] ?? '',
                'price' => $prcs[$i] ?? '',
            ];
        }
    }
    $valid = shipping_validate_prices($priceRows);
    foreach ($valid['errors'] as $ve) {
        $errors[] = $ve;
    }

    // Yeni logo (opsiyonel)
    $newLogo = null;
    if (!$errors) {
        $logoErr = null;
        $newLogo = shipping_upload_logo($_FILES['logo'] ?? [], $logoErr);
        if ($logoErr !== null) {
            $errors[] = $logoErr;
        }
    }

    if (!$errors) {
        try {
            $logoPath = $method['logo_path'];
            if ($newLogo !== null) {
                shipping_delete_logo($method['logo_path']); // eskiyi sil
                $logoPath = $newLogo;
            } elseif ($removeLogo) {
                shipping_delete_logo($method['logo_path']);
                $logoPath = null;
            }

            db()->prepare(
                'UPDATE shipping_methods
                    SET name=:n, free_shipping_limit=:f, sort_order=:s, is_active=:a,
                        description=:d, logo_path=:l
                 WHERE id=:id'
            )->execute([
                ':n' => $name, ':f' => $freeVal, ':s' => $sortVal, ':a' => $isActive,
                ':d' => ($desc !== '' ? $desc : null), ':l' => $logoPath, ':id' => $id,
            ]);

            shipping_replace_prices($id, $valid['clean']);

            flash('success', 'Kargo yöntemi güncellendi.');
            http_response_code(303);
            redirect('modules/settings/shipping-edit.php?id=' . $id);
        } catch (Throwable $e) {
            shipping_delete_logo($newLogo);
            $errors[] = safe_error('Kargo güncellenemedi.', 'shipping-edit: ' . $e->getMessage());
        }
    } else {
        shipping_delete_logo($newLogo ?? null);
    }

    // Hata varsa formu POST değerleriyle yeniden doldur
    $method['name'] = $name;
    $method['free_shipping_limit'] = $freeLim;
    $method['sort_order'] = $sortVal;
    $method['is_active'] = $isActive;
    $method['description'] = $desc;
    $prices = $priceRows;
} else {
    $prices = array_map(fn ($p) => ['min' => $p['min_desi'], 'max' => $p['max_desi'], 'price' => $p['price']], shipping_prices($id));
}

// Formda en az bir boş satır göster
if (empty($prices)) {
    $prices = [['min' => '', 'max' => '', 'price' => '']];
}

layout_top('Kargo Düzenle', 'shipping');
?>

<div class="page-head">
    <h1 class="page-title">Kargo Düzenle</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/shipping.php')) ?>">← Kargolar</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<form method="post" action="<?= e(url('modules/settings/shipping-edit.php')) ?>"
      enctype="multipart/form-data" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="card" style="max-width:760px">
        <div class="card-header"><h2>Kargo Bilgileri</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label>Mevcut logo</label>
                <div style="display:flex;align-items:center;gap:12px">
                    <?= shipping_logo_html($method['logo_path'] ?? null, (string) $method['name']) ?>
                    <?php if (!empty($method['logo_path'])): ?>
                        <label class="small" style="display:flex;align-items:center;gap:6px;font-weight:500">
                            <input type="checkbox" name="remove_logo" value="1" style="width:auto">
                            Logoyu kaldır
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="name">Kargo adı</label>
                <input type="text" id="name" name="name" value="<?= e($method['name']) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="free_shipping_limit">Ücretsiz kargo limiti (TL)</label>
                    <input type="text" id="free_shipping_limit" name="free_shipping_limit"
                           inputmode="decimal" value="<?= e((string) ($method['free_shipping_limit'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label for="sort_order">Sıra no</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= e((string) $method['sort_order']) ?>" step="1">
                </div>
            </div>

            <div class="form-group">
                <label for="logo">Logoyu değiştir (PNG, JPG, WEBP — en fazla 2 MB)</label>
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
            </div>

            <div class="form-group">
                <label for="description">Açıklama / not</label>
                <textarea id="description" name="description" rows="2"><?= e((string) ($method['description'] ?? '')) ?></textarea>
            </div>

            <div class="form-group">
                <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:220px">
                    <input type="checkbox" name="is_active" value="1" <?= (int) $method['is_active'] === 1 ? 'checked' : '' ?> style="width:auto">
                    <span>Aktif</span>
                </label>
            </div>
        </div>
    </div>

    <div class="card" id="ucretler" style="max-width:760px">
        <div class="card-header"><h2>Ücret Bilgileri (Desi Bazlı)</h2></div>
        <div class="card-body">
            <p class="field-hint" style="margin-top:0">
                Alt/üst limit ve tutar zorunludur. Aralıklar çakışamaz, negatif değer girilemez. Boş satır kaydedilmez.
            </p>
            <div class="price-rows" id="priceRows">
                <div class="price-row" style="font-weight:600;color:var(--muted);font-size:13px">
                    <span>Alt Limit Desi</span><span>Üst Limit Desi</span><span>Tutar (TL)</span><span></span>
                </div>
                <?php foreach ($prices as $p): ?>
                    <div class="price-row">
                        <input type="text" name="min_desi[]" inputmode="decimal" value="<?= e((string) $p['min']) ?>" placeholder="0">
                        <input type="text" name="max_desi[]" inputmode="decimal" value="<?= e((string) $p['max']) ?>" placeholder="1">
                        <input type="text" name="price[]" inputmode="decimal" value="<?= e((string) $p['price']) ?>" placeholder="85">
                        <button type="button" class="btn btn-sm btn-rm" onclick="this.closest('.price-row').remove()">Sil</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:12px">
                <button type="button" class="btn btn-sm" id="addRow">+ Satır ekle</button>
            </div>
        </div>
    </div>

    <div class="form-actions" style="max-width:760px">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/settings/shipping.php')) ?>">Vazgeç</a>
    </div>
</form>

<script>
(function(){
    var add = document.getElementById('addRow');
    var rows = document.getElementById('priceRows');
    if (add && rows) {
        add.addEventListener('click', function(){
            var div = document.createElement('div');
            div.className = 'price-row';
            div.innerHTML = '<input type="text" name="min_desi[]" inputmode="decimal" placeholder="0">' +
                '<input type="text" name="max_desi[]" inputmode="decimal" placeholder="1">' +
                '<input type="text" name="price[]" inputmode="decimal" placeholder="85">' +
                '<button type="button" class="btn btn-sm btn-rm">Sil</button>';
            div.querySelector('button').addEventListener('click', function(){ div.remove(); });
            rows.appendChild(div);
        });
    }
})();
</script>

<?php
layout_bottom();
