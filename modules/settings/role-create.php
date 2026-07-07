<?php
declare(strict_types=1);

/**
 * modules/settings/role-create.php
 * Yeni rol oluşturma. Slug isimden otomatik üretilir.
 */
require_once __DIR__ . '/../../includes/permissions.php';

auth_boot();
require_permission('settings');

$modules  = all_modules();
$errors   = [];
$name     = '';
$desc     = '';
$selected = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $selected = isset($_POST['perms']) && is_array($_POST['perms']) ? array_values($_POST['perms']) : [];

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Rol adı en az 2 karakter olmalıdır.';
    }

    if (!$errors) {
        try {
            $chk = db()->prepare('SELECT COUNT(*) FROM roles WHERE name = :n');
            $chk->execute([':n' => $name]);
            if ((int) $chk->fetchColumn() > 0) {
                $errors[] = 'Bu isimde bir rol zaten var.';
            }
        } catch (Throwable $e) {
            $errors[] = safe_error('İşlem sırasında hata oluştu.', 'role-create uniq: ' . $e->getMessage());
        }
    }

    if (!$errors) {
        try {
            $perms = sanitize_permissions($selected);
            $slug  = role_unique_slug(slugify($name));
            db()->prepare(
                'INSERT INTO roles (name, slug, description, permissions, is_system)
                 VALUES (:n, :s, :d, :p, 0)'
            )->execute([':n' => $name, ':s' => $slug, ':d' => ($desc !== '' ? $desc : null), ':p' => $perms]);
            log_activity('role_create', 'role', (int) db()->lastInsertId(), $slug, 'success', 'Rol oluşturuldu: ' . $name);
            flash('success', 'Rol oluşturuldu.');
            redirect('modules/settings/roles.php');
        } catch (Throwable $e) {
            $errors[] = safe_error('Rol oluşturulamadı.', 'role-create insert: ' . $e->getMessage());
        }
    }
}

$allChecked = in_array('all', $selected, true);

layout_top('Yeni Rol', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Yeni Rol</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/roles.php')) ?>">← Roller</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:900px">
    <div class="card-body">
        <form method="post" action="<?= e(url('modules/settings/role-create.php')) ?>"
              data-lock-on-submit novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Rol adı</label>
                <input type="text" id="name" name="name" value="<?= e($name) ?>" required>
                <div class="field-hint">Slug otomatik üretilir (örn. “Bölge Müdürü” → bolge-muduru).</div>
            </div>

            <div class="form-group">
                <label for="description">Açıklama (isteğe bağlı)</label>
                <input type="text" id="description" name="description" value="<?= e($desc) ?>">
            </div>

            <div class="form-group">
                <label>Yetkiler</label>
                <?php $isSystem = false; require __DIR__ . '/_permissions-picker.php'; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Oluştur</button>
                <a class="btn" href="<?= e(url('modules/settings/roles.php')) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>

<?php
layout_bottom();
