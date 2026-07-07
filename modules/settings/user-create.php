<?php
declare(strict_types=1);

/**
 * modules/settings/user-create.php
 * Yeni kullanıcı oluşturma.
 */
require_once __DIR__ . '/../../includes/permissions.php';

auth_boot();
require_permission('settings');

// Rol seçenekleri
$roles = [];
try {
    $roles = db()->query('SELECT id, name FROM roles ORDER BY name ASC')->fetchAll();
} catch (Throwable $e) {
    log_error('user-create roles: ' . $e->getMessage());
}

$errors   = [];
$username = $email = $fullName = '';
$roleId   = '';
$isActive = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId   = trim($_POST['role_id'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($username === '' || mb_strlen($username) < 3) {
        $errors[] = 'Kullanıcı adı en az 3 karakter olmalıdır.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'Geçerli bir e-posta girin.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Şifre en az 8 karakter olmalıdır.';
    }
    $roleIdVal = ($roleId === '') ? null : (int) $roleId;

    if (!$errors) {
        try {
            $chk = db()->prepare('SELECT COUNT(*) FROM users WHERE username = :u OR email = :e');
            $chk->execute([':u' => $username, ':e' => $email]);
            if ((int) $chk->fetchColumn() > 0) {
                $errors[] = 'Bu kullanıcı adı veya e-posta zaten kayıtlı.';
            }
        } catch (Throwable $e) {
            $errors[] = safe_error('İşlem sırasında hata oluştu.', 'user-create uniq: ' . $e->getMessage());
        }
    }

    if (!$errors) {
        try {
            db()->prepare(
                'INSERT INTO users (username, email, full_name, password_hash, role_id, is_active)
                 VALUES (:u, :e, :f, :h, :r, :a)'
            )->execute([
                ':u' => $username,
                ':e' => $email,
                ':f' => $fullName,
                ':h' => password_hash($password, PASSWORD_DEFAULT),
                ':r' => $roleIdVal,
                ':a' => $isActive,
            ]);
            flash('success', 'Kullanıcı oluşturuldu.');
            redirect('modules/settings/users.php');
        } catch (Throwable $e) {
            $errors[] = safe_error('Kullanıcı oluşturulamadı.', 'user-create insert: ' . $e->getMessage());
        }
    }
}

layout_top('Yeni Kullanıcı', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Yeni Kullanıcı</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/users.php')) ?>">← Kullanıcılar</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:680px">
    <div class="card-body">
        <form method="post" action="<?= e(url('modules/settings/user-create.php')) ?>"
              data-lock-on-submit novalidate>
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="username">Kullanıcı adı</label>
                    <input type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label for="email">E-posta</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="off" required>
                </div>
            </div>

            <div class="form-group">
                <label for="full_name">Ad Soyad</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($fullName) ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required>
                    <div class="field-hint">En az 8 karakter.</div>
                </div>
                <div class="form-group">
                    <label for="role_id">Rol</label>
                    <select id="role_id" name="role_id">
                        <option value="">— Rol yok —</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>"<?= (string) $roleId === (string) $role['id'] ? ' selected' : '' ?>>
                                <?= e($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:220px">
                    <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?> style="width:auto">
                    <span>Hesap aktif</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Oluştur</button>
                <a class="btn" href="<?= e(url('modules/settings/users.php')) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>

<?php
layout_bottom();
