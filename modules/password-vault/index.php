<?php
declare(strict_types=1);

/** modules/password-vault/index.php — Kasa listesi (şifreler GÖSTERİLMEZ). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/vault.php';

auth_boot();
require_permission('password_vault.view');

$f = ['category' => (string) ($_GET['category'] ?? ''), 'search' => trim((string) ($_GET['q'] ?? ''))];
$rows = get_vault_items($f);
$counts = vault_counts();

$vaultReady = vault_is_configured();
$isSuperAdmin = in_array('all', function_exists('current_permissions') ? current_permissions() : [], true);

layout_top('Şifre Kasası', 'password_vault');
?>
<div class="page-head"><h1 class="page-title">Şifre Kasası</h1><div class="page-actions">
    <?php if (can('password_vault.create')): ?>
        <?php if ($vaultReady): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('modules/password-vault/create.php')) ?>"><?= icon('plus') ?>Yeni Kayıt</a>
        <?php else: ?>
            <button type="button" class="btn btn-primary btn-sm" disabled title="Güvenlik anahtarı yapılandırılmadan kayıt eklenemez"><?= icon('plus') ?>Yeni Kayıt</button>
        <?php endif; ?>
    <?php endif; ?>
</div></div>
<?= render_flashes() ?>

<?php if (!$vaultReady): ?>
    <div class="alert alert-error">
        <strong>Şifre Kasası güvenlik anahtarı yapılandırılmamış.</strong> Şifreler kaydedilemez veya görüntülenemez.
        <?php if ($isSuperAdmin): ?>
            <div style="margin-top:8px;font-weight:400">
                Çözüm: <code>tools/generate-vault-key.php</code> ile bir anahtar üretip <code>config.php</code> içine
                <code>VAULT_KEY</code> olarak ekleyin.
                <a class="btn btn-xs" style="margin-left:6px" href="<?= e(url('tools/generate-vault-key.php')) ?>"><?= icon('shield', 'icon-xs') ?>Anahtar Üret</a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="tab-row">
    <a class="tab-chip<?= $f['category'] === '' ? ' is-active' : '' ?>" href="<?= e(url('modules/password-vault/index.php')) ?>">Tümü <span class="tab-count"><?= (int) $counts['all'] ?></span></a>
    <?php foreach (vault_categories() as $k => $l): if (empty($counts[$k])) { continue; } ?>
        <a class="tab-chip<?= $f['category'] === $k ? ' is-active' : '' ?>" href="<?= e(url('modules/password-vault/index.php?category=' . $k)) ?>"><?= e($l) ?> <span class="tab-count"><?= (int) $counts[$k] ?></span></a>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url('modules/password-vault/index.php')) ?>" class="toolbar">
    <?php if ($f['category'] !== ''): ?><input type="hidden" name="category" value="<?= e($f['category']) ?>"><?php endif; ?>
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Başlık, kullanıcı adı, URL, sorumlu"></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('search') ?>Ara</button></div>
</form>

<?php if (!$rows): ?><div class="card"><div class="card-body"><div class="empty">Kayıt bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Başlık</th><th>Kategori</th><th>Kullanıcı adı</th><th>Sorumlu</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('modules/password-vault/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['title']) ?></strong></a></td>
            <td><?= e(vault_category_label((string) $r['category'])) ?></td>
            <td><?= e((string) ($r['username'] ?? '')) ?: '—' ?></td>
            <td><?= e((string) ($r['responsible_person'] ?? '')) ?: '—' ?></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/password-vault/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
            <?php if (can('password_vault.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/password-vault/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
