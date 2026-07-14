<?php
declare(strict_types=1);

/**
 * modules/settings/user-edit.php
 * Kullanıcı düzenleme. Şifre boş bırakılırsa mevcut şifre korunur.
 * "En az bir aktif yönetici" kuralı korunur; kullanıcı kendini pasifleştiremez.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/email_system.php';

auth_boot();
require_permission('settings');

$me = current_user();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Geçersiz kullanıcı.');
    redirect('modules/settings/users.php');
}

$roles = [];
try {
    $roles = db()->query('SELECT id, name FROM roles ORDER BY name ASC')->fetchAll();
} catch (Throwable $e) {
    log_error('user-edit roles: ' . $e->getMessage());
}

try {
    $st = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $user = $st->fetch();
} catch (Throwable $e) {
    log_error('user-edit fetch: ' . $e->getMessage());
    $user = null;
}

if (!$user) {
    flash('error', 'Kullanıcı bulunamadı.');
    redirect('modules/settings/users.php');
}

$isSelf   = ($id === $me['id']);
$errors   = [];
$username = $user['username'];
$email    = $user['email'];
$fullName = $user['full_name'];
$roleId   = $user['role_id'] !== null ? (string) $user['role_id'] : '';
$isActive = (int) $user['is_active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $fullName  = trim($_POST['full_name'] ?? '');
    $password  = $_POST['password'] ?? '';
    $roleId    = trim($_POST['role_id'] ?? '');
    $isActive  = $isSelf ? 1 : (isset($_POST['is_active']) ? 1 : 0); // kendini pasifleştiremez
    $roleIdVal = ($roleId === '') ? null : (int) $roleId;

    if ($username === '' || mb_strlen($username) < 3) {
        $errors[] = 'Kullanıcı adı en az 3 karakter olmalıdır.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'Geçerli bir e-posta girin.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Şifre en az 8 karakter olmalıdır (boş bırakılırsa değişmez).';
    }

    // Benzersizlik (kendisi hariç)
    if (!$errors) {
        try {
            $chk = db()->prepare('SELECT COUNT(*) FROM users WHERE (username = :u OR email = :e) AND id <> :id');
            $chk->execute([':u' => $username, ':e' => $email, ':id' => $id]);
            if ((int) $chk->fetchColumn() > 0) {
                $errors[] = 'Bu kullanıcı adı veya e-posta başka bir hesapta kayıtlı.';
            }
        } catch (Throwable $e) {
            $errors[] = safe_error('İşlem sırasında hata oluştu.', 'user-edit uniq: ' . $e->getMessage());
        }
    }

    // "En az bir aktif yönetici kalmalı" değişmezi
    if (!$errors) {
        $newRoleIsAdmin    = $roleIdVal !== null && in_array($roleIdVal, admin_role_ids(), true);
        $othersActiveAdmins = count_active_admins($id);
        $thisContributes    = ($isActive === 1 && $newRoleIsAdmin) ? 1 : 0;
        if ($othersActiveAdmins + $thisContributes < 1) {
            $errors[] = 'En az bir aktif yönetici kalmalıdır. Bu değişiklik uygulanamaz.';
        }
    }

    if (!$errors) {
        try {
            if ($password !== '') {
                $sql = 'UPDATE users SET username=:u, email=:e, full_name=:f, role_id=:r,
                        is_active=:a, password_hash=:h WHERE id=:id';
                $params = [
                    ':u' => $username, ':e' => $email, ':f' => $fullName, ':r' => $roleIdVal,
                    ':a' => $isActive, ':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id,
                ];
            } else {
                $sql = 'UPDATE users SET username=:u, email=:e, full_name=:f, role_id=:r,
                        is_active=:a WHERE id=:id';
                $params = [
                    ':u' => $username, ':e' => $email, ':f' => $fullName, ':r' => $roleIdVal,
                    ':a' => $isActive, ':id' => $id,
                ];
            }
            db()->prepare($sql)->execute($params);
            user_email_pref_save($id, [
                'corporate_email'     => $_POST['pref_corporate_email'] ?? '',
                'department'          => $_POST['pref_department'] ?? '',
                'default_account_id'  => (int) ($_POST['pref_default_account_id'] ?? 0),
                'can_receive_replies' => isset($_POST['pref_can_receive_replies']),
                'auto_cc'             => isset($_POST['pref_auto_cc']),
                'is_active'           => isset($_POST['pref_is_active']),
            ]);
            flash('success', 'Kullanıcı güncellendi.');
            redirect('modules/settings/users.php');
        } catch (Throwable $e) {
            $errors[] = safe_error('Kullanıcı güncellenemedi.', 'user-edit update: ' . $e->getMessage());
        }
    }
}

$pref = user_email_pref_get($id);
$deptAccounts = dept_accounts_all();
layout_top('Kullanıcıyı Düzenle', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Kullanıcıyı Düzenle</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/users.php')) ?>">← Kullanıcılar</a>
    </div>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-error"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:680px">
    <div class="card-body">
        <form method="post" action="<?= e(url('modules/settings/user-edit.php')) ?>"
              data-lock-on-submit novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="username">Kullanıcı adı</label>
                    <input type="text" id="username" name="username" value="<?= e($username) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">E-posta</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="full_name">Ad Soyad</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($fullName) ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Yeni şifre</label>
                    <input type="password" id="password" name="password" autocomplete="new-password">
                    <div class="field-hint">Değiştirmek istemiyorsanız boş bırakın.</div>
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
                <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:240px<?= $isSelf ? ';opacity:.6' : '' ?>">
                    <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>
                           <?= $isSelf ? 'disabled' : '' ?> style="width:auto">
                    <span>Hesap aktif</span>
                </label>
                <?php if ($isSelf): ?>
                    <div class="field-hint">Kendi hesabınızı pasifleştiremezsiniz.</div>
                <?php endif; ?>
            </div>

            <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:8px">
                <h3 style="margin:0 0 10px;font-size:15px">E-Posta / Gönderim Tercihleri</h3>
                <div class="form-row">
                    <div class="form-group"><label for="pref_corporate_email">Kurumsal e-posta</label>
                        <input type="email" id="pref_corporate_email" name="pref_corporate_email" value="<?= e((string) ($pref['corporate_email'] ?? '')) ?>">
                        <div class="field-hint">Reply-To bu adrese döner. Boşsa hesap e-postası kullanılır.</div></div>
                    <div class="form-group"><label for="pref_department">Departman</label>
                        <input type="text" id="pref_department" name="pref_department" value="<?= e((string) ($pref['department'] ?? '')) ?>"></div>
                </div>
                <div class="form-group"><label for="pref_default_account_id">Varsayılan gönderim hesabı</label>
                    <select id="pref_default_account_id" name="pref_default_account_id">
                        <option value="">— Yok —</option>
                        <?php foreach ($deptAccounts as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"<?= (int) ($pref['default_account_id'] ?? 0) === (int) $a['id'] ? ' selected' : '' ?>><?= e((string) $a['department_name']) ?> · <?= e((string) $a['from_email']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-check"><label><input type="checkbox" name="pref_can_receive_replies" <?= (int) ($pref['can_receive_replies'] ?? 1) === 1 ? 'checked' : '' ?>> Mail cevaplarını alabilir</label></div>
                <div class="form-check"><label><input type="checkbox" name="pref_auto_cc" <?= (int) ($pref['auto_cc'] ?? 0) === 1 ? 'checked' : '' ?>> Gönderdiği belgelerde CC'ye otomatik eklensin</label></div>
                <div class="form-check"><label><input type="checkbox" name="pref_is_active" <?= (int) ($pref['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Gönderim tercihi aktif</label></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <a class="btn" href="<?= e(url('modules/settings/users.php')) ?>">Vazgeç</a>
            </div>
        </form>
    </div>
</div>

<?php
layout_bottom();
