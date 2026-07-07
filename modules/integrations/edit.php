<?php
declare(strict_types=1);

/** modules/integrations/edit.php — Entegrasyon ayarları + bağlantı testi + log. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/integrations.php';

auth_boot();
require_permission('integrations.edit');

$key = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
$def = integration_def($key);
if (!$def) { flash('error', 'Tanımsız entegrasyon.'); redirect('modules/integrations/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    csrf_check();
    if (!empty($def['readonly'])) {
        $errors[] = 'Bu entegrasyonun ayarları config.php üzerinden yönetilir.';
    } else {
        $ok = save_integration($key, [
            'is_active' => isset($_POST['is_active']),
            'api_url'   => $_POST['api_url'] ?? '',
            'username'  => $_POST['username'] ?? '',
            'secret'    => (array) ($_POST['secret'] ?? []),
            'config'    => (array) ($_POST['config'] ?? []),
        ]);
        if ($ok) {
            log_activity('integration_save', 'integration', null, $key, 'success', 'Entegrasyon güncellendi: ' . $def['name']);
            flash('success', 'Ayarlar kaydedildi.');
            redirect('modules/integrations/edit.php?key=' . $key);
        }
        $errors[] = vault_is_configured() ? 'Kaydetme başarısız.' : 'Gizli bilgileri şifrelemek için VAULT_KEY gerekli.';
    }
}

$row = get_integration($key);
$config = integration_config($key);
$secrets = integration_secrets($key);
$logs = integration_logs($key, 15);

layout_top('Entegrasyon: ' . $def['name'], 'integrations');
?>
<div class="page-head"><h1 class="page-title"><?= e($def['name']) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/integrations/index.php')) ?>">← Entegrasyonlar</a>
    <form method="post" action="<?= e(url('modules/integrations/test.php')) ?>" style="display:inline">
        <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>">
        <button type="submit" class="btn btn-sm"><?= icon('refresh-cw') ?>Bağlantıyı Test Et</button>
    </form>
</div></div>
<?= render_flashes() ?>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<?php if (!empty($def['readonly'])): ?>
    <div class="alert alert-info">Bu entegrasyonun ayarları <code>config.php</code> içinde tanımlıdır (T-Soft: <code>TSOFT_BASE_URL</code> vb.). Buradan yalnızca bağlantıyı test edebilirsiniz.</div>
<?php else: ?>
<form method="post" action="<?= e(url('modules/integrations/edit.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="key" value="<?= e($key) ?>">
    <input type="hidden" name="action" value="save">
    <div class="card" style="max-width:720px"><div class="card-header"><h2>Ayarlar</h2></div><div class="card-body">
        <div class="form-check"><label><input type="checkbox" name="is_active" value="1"<?= (int) ($row['is_active'] ?? 0) === 1 ? ' checked' : '' ?>> Aktif</label></div>
        <?php if (!empty($def['api_url'])): ?>
            <div class="form-group"><label for="api_url">API URL</label><input type="text" id="api_url" name="api_url" value="<?= e((string) ($row['api_url'] ?? '')) ?>" placeholder="https://..."></div>
        <?php endif; ?>
        <?php if (!empty($def['username'])): ?>
            <div class="form-group"><label for="username">Kullanıcı adı</label><input type="text" id="username" name="username" value="<?= e((string) ($row['username'] ?? '')) ?>" autocomplete="off"></div>
        <?php endif; ?>
        <?php foreach ($def['secret_fields'] as $f => $label): $has = trim((string) ($secrets[$f] ?? '')) !== ''; ?>
            <div class="form-group"><label for="secret_<?= e($f) ?>"><?= e($label) ?></label>
                <input type="password" id="secret_<?= e($f) ?>" name="secret[<?= e($f) ?>]" value="" autocomplete="new-password" placeholder="<?= $has ? '•••••••• (değiştirmek için doldurun)' : '' ?>">
            </div>
        <?php endforeach; ?>
        <?php foreach ($def['config_fields'] as $cf => $type): ?>
            <?php if ($type === 'bool'): ?>
                <div class="form-check"><label><input type="checkbox" name="config[<?= e($cf) ?>]" value="1"<?= (int) ($config[$cf] ?? 0) === 1 ? ' checked' : '' ?>> <?= e(ucfirst(str_replace('_', ' ', $cf))) ?></label></div>
            <?php else: ?>
                <div class="form-group"><label for="config_<?= e($cf) ?>"><?= e(ucfirst(str_replace('_', ' ', $cf))) ?></label><input type="text" id="config_<?= e($cf) ?>" name="config[<?= e($cf) ?>]" value="<?= e((string) ($config[$cf] ?? '')) ?>"></div>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="field-hint">Gizli bilgiler AES-256-GCM ile şifreli saklanır ve ekranda maskeli gösterilir.</div>
    </div></div>
    <div class="form-actions" style="max-width:720px"><button type="submit" class="btn btn-primary">Kaydet</button></div>
</form>
<?php endif; ?>

<div class="card" style="max-width:720px"><div class="card-header"><h2>Bağlantı Logları</h2></div><div class="card-body">
    <?php if (!$logs): ?><div class="empty">Henüz log yok.</div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>İşlem</th><th>Durum</th><th>Mesaj</th><th class="nowrap">Tarih</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr><td><?= e((string) $l['action']) ?></td>
                <td><span class="badge <?= (string) $l['status'] === 'success' ? 'badge-success' : ((string) $l['status'] === 'failed' ? 'badge-danger' : 'badge-info') ?>"><?= e((string) $l['status']) ?></span></td>
                <td class="wrap"><?= e((string) ($l['message'] ?? '')) ?></td>
                <td class="nowrap"><?= e(fmt_date((string) $l['created_at'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div></div>
<?php layout_bottom();
