<?php
declare(strict_types=1);

/** modules/forms/external-links.php — Dış form token linkleri listesi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';

auth_boot();
require_permission('forms.external.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tid = (int) ($_POST['id'] ?? 0);
    $active = (string) ($_POST['active'] ?? '') === '1';
    form_center_toggle_token($tid, $active);
    flash('success', $active ? 'Link aktifleştirildi.' : 'Link pasife alındı.');
    http_response_code(303);
    redirect('modules/forms/external-links.php');
}

$rows = form_center_external_tokens();

layout_top('Dış Form Linkleri', 'settings');
?>
<div class="page-head"><h1 class="page-title">Dış Form Linkleri</h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a>
    <a class="btn btn-primary btn-sm" href="<?= e(url('modules/forms/external-link-create.php')) ?>"><?= icon('plus') ?>Yeni Link</a>
</div></div>
<?= render_flashes() ?>

<?php if (!$rows): ?>
<div class="empty-state empty-compact"><p>Henüz dış form linki yok.</p></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Başlık / Form</th><th>Kullanım</th><th>Son Kullanım</th><th>Durum</th><th class="nowrap">Link</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t):
        $pub = url('public-form.php') . '?token=' . (string) $t['token'];
        $expired = !empty($t['expires_at']) && strtotime((string) $t['expires_at']) < time();
        $usedUp = $t['max_uses'] !== null && (int) $t['used_count'] >= (int) $t['max_uses'];
    ?>
        <tr>
            <td><strong><?= e((string) ($t['title'] ?? $t['form_name'])) ?></strong><div class="small muted"><?= e((string) $t['form_name']) ?></div></td>
            <td><?= (int) $t['used_count'] ?><?= $t['max_uses'] !== null ? ' / ' . (int) $t['max_uses'] : '' ?></td>
            <td class="nowrap"><?= e((string) ($t['expires_at'] ?? '')) ?: '∞' ?></td>
            <td>
                <?php if ((int) $t['is_active'] !== 1): ?><span class="badge badge-muted">Pasif</span>
                <?php elseif ($expired): ?><span class="badge badge-danger">Süresi Doldu</span>
                <?php elseif ($usedUp): ?><span class="badge badge-danger">Doldu</span>
                <?php else: ?><span class="badge badge-success">Aktif</span><?php endif; ?>
            </td>
            <td class="nowrap"><a href="<?= e($pub) ?>" target="_blank" rel="noopener"><?= icon('link', 'icon-xs') ?>Aç</a></td>
            <td class="nowrap">
                <form method="post" action="<?= e(url('modules/forms/external-links.php')) ?>" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="active" value="<?= (int) $t['is_active'] === 1 ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-xs"><?= (int) $t['is_active'] === 1 ? 'Pasife Al' : 'Aktifleştir' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
