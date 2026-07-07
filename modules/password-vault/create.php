<?php
declare(strict_types=1);

/** modules/password-vault/create.php — Yeni kasa kaydı. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/vault.php';

auth_boot();
require_permission('password_vault.create');

$errors = [];
$v = ['category' => 'other'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v = vault_fields_from_input($_POST);
    $errors = vault_validate($v);
    if (!$errors) {
        $id = create_vault_item($v, current_user_id());
        if ($id > 0) {
            log_activity('vault_create', 'vault', $id, null, 'success', 'Kasa kaydı eklendi: ' . $v['title']);
            flash('success', 'Kayıt eklendi.');
            redirect('modules/password-vault/view.php?id=' . $id);
        }
        $errors[] = 'Kayıt eklenemedi.';
    }
}

$users = [];
try { foreach (db()->query('SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll() as $u) { $users[(int) $u['id']] = $u['full_name'] ?: $u['username']; } } catch (Throwable $e) {}

layout_top('Yeni Kasa Kaydı', 'password_vault');
?>
<div class="page-head"><h1 class="page-title">Yeni Kasa Kaydı</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/password-vault/index.php')) ?>">← Kasa</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/password-vault/create.php')) ?>" autocomplete="off" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php $isEdit = false; require __DIR__ . '/_vault-form.php'; ?>
    <div class="form-actions" style="max-width:820px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/password-vault/index.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
