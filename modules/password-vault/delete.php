<?php
declare(strict_types=1);

/** modules/password-vault/delete.php — Kasa kaydı sil (yumuşak). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/vault.php';

auth_boot();
require_permission('password_vault.delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$v  = get_vault_item($id);
if (!$v) { flash('error', 'Kayıt bulunamadı veya yetkiniz yok.'); redirect('modules/password-vault/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (delete_vault_item($id, current_user_id())) {
        log_activity('vault_delete', 'vault', $id, null, 'success', 'Kasa kaydı silindi: ' . $v['title']);
        flash('success', 'Kayıt silindi.');
    } else {
        flash('error', 'Silme başarısız.');
    }
    redirect('modules/password-vault/index.php');
}

layout_top('Kasa Kaydı Sil', 'password_vault');
?>
<div class="page-head"><h1 class="page-title">Kasa Kaydı Sil</h1></div>
<div class="card" style="max-width:520px"><div class="card-body">
    <p><strong><?= e($v['title']) ?></strong> kaydını silmek istediğinize emin misiniz?</p>
    <form method="post" action="<?= e(url('modules/password-vault/delete.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-actions"><button type="submit" class="btn btn-danger"><?= icon('trash-2') ?>Evet, sil</button><a class="btn" href="<?= e(url('modules/password-vault/view.php?id=' . $id)) ?>">Vazgeç</a></div>
    </form>
</div></div>
<?php layout_bottom();
