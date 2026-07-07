<?php
declare(strict_types=1);

/**
 * modules/settings/role-edit.php
 * Rol düzenleme. Sistem rolünün (Yönetici) yetkileri kilitlidir (tam yetki korunur).
 * Slug isimden yeniden üretilir.
 */
require_once __DIR__ . '/../../includes/permissions.php';

auth_boot();
require_permission('settings');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Geçersiz rol.');
    redirect('modules/settings/roles.php');
}

try {
    $st = db()->prepare('SELECT * FROM roles WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $role = $st->fetch();
} catch (Throwable $e) {
    log_error('role-edit fetch: ' . $e->getMessage());
    $role = null;
}

if (!$role) {
    flash('error', 'Rol bulunamadı.');
    redirect('modules/settings/roles.php');
}

$modules  = all_modules();
$isSystem = (int) $role['is_system'] === 1;
$errors   = [];
$name     = $role['name'];
$desc     = (string) ($role['description'] ?? '');
$selected = json_decode((string) $role['permissions'], true);
$selected = is_array($selected) ? $selected : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if ($isSystem) {
        $selected = ['all']; // sistem rolü daima tam yetki
    } else {
        $selected = isset($_POST['perms']) && is_array($_POST['perms']) ? array_values($_POST['perms']) : [];
    }

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Rol adı en az 2 karakter olmalıdır.';
    }

    if (!$errors) {
        try {
            $chk = db()->prepare('SELECT COUNT(*) FROM roles WHERE name = :n AND id <> :id');
            $chk->execute([':n' => $name, ':id' => $id]);
            if ((int) $chk->fetchColumn() > 0) {
                $errors[] = 'Bu isimde başka bir rol var.';
            }
        } catch (Throwable $e) {
            $errors[] = safe_error('İşlem sırasında hata oluştu.', 'role-edit uniq: ' . $e->getMessage());
        }
    }

    if (!$errors) {
        try {
            $perms = sanitize_permissions($selected);
            $slug  = role_unique_slug(slugify($name), $id);
            db()->prepare('UPDATE roles SET name = :n, slug = :s, description = :d, permissions = :p WHERE id = :id')
                ->execute([
                    ':n' => $name, ':s' => $slug,
                    ':d' => ($desc !== '' ? $desc : null),
                    ':p' => $perms, ':id' => $id,
                ]);
            log_activity('role_edit', 'role', $id, $slug, 'success', 'Rol güncellendi: ' . $name);
            flash('success', 'Rol güncellendi.');
            redirect('modules/settings/roles.php');
        } catch (Throwable $e) {
            $errors[] = safe_error('Rol güncellenemedi.', 'role-edit update: ' . $e->getMessage());
        }
    }
}

$allChecked = in_array('all', $selected, true);

layout_top('Rolü Düzenle', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Rolü Düzenle</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/roles.php')) ?>">← Roller</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:900px">
    <div class="card-body">
        <form method="post" action="<?= e(url('modules/settings/role-edit.php')) ?>"
              data-lock-on-submit novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <div class="form-group">
                <label for="name">Rol adı</label>
                <input type="text" id="name" name="name" value="<?= e($name) ?>" required>
                <div class="field-hint">Mevcut slug: <?= e($role['slug']) ?> (isim değişirse yeniden üretilir).</div>
            </div>

            <div class="form-group">
                <label for="description">Açıklama (isteğe bağlı)</label>
                <input type="text" id="description" name="description" value="<?= e($desc) ?>">
            </div>

            <div class="form-group">
                <label>Yetkiler</label>
                <?php require __DIR__ . '/_permissions-picker.php'; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <a class="btn" href="<?= e(url('modules/settings/roles.php')) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>

<?php
layout_bottom();
