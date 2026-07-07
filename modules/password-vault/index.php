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

layout_top('Şifre Kasası', 'password_vault');
?>
<div class="page-head"><h1 class="page-title">Şifre Kasası</h1><div class="page-actions">
    <?php if (can('password_vault.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/password-vault/create.php')) ?>"><?= icon('plus') ?>Yeni Kayıt</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>

<?php if (!vault_is_configured()): ?>
    <div class="alert alert-error">Şifre kasası şifreleme anahtarı (VAULT_KEY) yapılandırılmamış. Şifreler görüntülenemez/kaydedilemez.</div>
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
