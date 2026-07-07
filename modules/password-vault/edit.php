<?php
declare(strict_types=1);

/** modules/password-vault/edit.php — Kasa kaydı düzenle. Şifre boş bırakılırsa değişmez. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/vault.php';

auth_boot();
require_permission('password_vault.edit');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$v  = get_vault_item($id);
if (!$v) { flash('error', 'Kayıt bulunamadı veya görüntüleme yetkiniz yok.'); redirect('modules/password-vault/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = vault_fields_from_input($_POST);
    $errors = vault_validate($d);
    if (!$errors) {
        if (update_vault_item($id, $d, current_user_id())) {
            log_activity('vault_edit', 'vault', $id, null, 'success', 'Kasa kaydı güncellendi: ' . $d['title']);
            flash('success', 'Kayıt güncellendi.');
            redirect('modules/password-vault/view.php?id=' . $id);
        }
        $errors[] = 'Güncelleme başarısız.';
    }
    // Formu geri doldur (şifre alanı hariç — güvenlik)
    $v = array_merge($v, $d);
}

$users = [];
try { foreach (db()->query('SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll() as $u) { $users[(int) $u['id']] = $u['full_name'] ?: $u['username']; } } catch (Throwable $e) {}

layout_top('Kasa Kaydını Düzenle', 'password_vault');
?>
<div class="page-head"><h1 class="page-title">Kasa Kaydını Düzenle</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/password-vault/view.php?id=' . $id)) ?>">← Detay</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/password-vault/edit.php')) ?>" autocomplete="off" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php $isEdit = true; require __DIR__ . '/_vault-form.php'; ?>
    <div class="form-actions" style="max-width:820px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/password-vault/view.php?id=' . $id)) ?>">Vazgeç</a>
    <?php if (can('password_vault.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/password-vault/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?></div>
</form>
<?php layout_bottom();
