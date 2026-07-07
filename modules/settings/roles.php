<?php
declare(strict_types=1);

/**
 * modules/settings/roles.php
 * Rol listesi.
 */
require_once __DIR__ . '/../../includes/permissions.php';

auth_boot();
require_permission('settings');

/**
 * JSON yetkiyi okunur etikete çevirir.
 */
function permissions_summary(?string $json): string
{
    $arr = json_decode((string) $json, true);
    if (!is_array($arr) || !$arr) {
        return '—';
    }
    if (in_array('all', $arr, true)) {
        return 'Tüm modüller (Süper Admin)';
    }
    $modules = all_modules();
    // Modüle göre işlem sayısını topla (hem kaba hem ince anahtarları anlar).
    $byModule = [];
    foreach ($arr as $k) {
        $k = (string) $k;
        $dot = strpos($k, '.');
        $mod = $dot !== false ? substr($k, 0, $dot) : $k;
        $byModule[$mod] = $byModule[$mod] ?? 0;
        if ($dot !== false) { $byModule[$mod]++; }
    }
    $labels = [];
    foreach ($byModule as $mod => $count) {
        $label = $modules[$mod] ?? $mod;
        $labels[] = $count > 0 ? ($label . ' (' . $count . ')') : $label;
    }
    return implode(', ', $labels);
}

$rows = [];
try {
    $rows = db()->query(
        'SELECT r.id, r.name, r.slug, r.permissions, r.is_system,
                (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count
         FROM roles r ORDER BY r.id ASC'
    )->fetchAll();
} catch (Throwable $e) {
    log_error('roles list: ' . $e->getMessage());
}

layout_top('Roller', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Roller</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Ayarlar</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('modules/settings/role-create.php')) ?>">Yeni rol</a>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Henüz rol yok.</div></div></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Rol</th>
                    <th>Slug</th>
                    <th>Yetkiler</th>
                    <th>Kullanıcı</th>
                    <th style="text-align:right">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <?= e($r['name']) ?>
                            <?php if ((int) $r['is_system'] === 1): ?>
                                <span class="badge badge-info">Sistem</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="muted"><?= e($r['slug']) ?></span></td>
                        <td class="wrap"><?= e(permissions_summary($r['permissions'])) ?></td>
                        <td><?= (int) $r['user_count'] ?></td>
                        <td>
                            <div class="row-actions" style="justify-content:flex-end">
                                <a class="btn btn-sm" href="<?= e(url('modules/settings/role-edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil') ?>Düzenle</a>
                                <?php if ((int) $r['is_system'] !== 1): ?>
                                    <form method="post" action="<?= e(url('modules/settings/role-delete.php')) ?>"
                                          data-confirm="“<?= e($r['name']) ?>” rolünü silmek istediğinize emin misiniz?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Sil</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
layout_bottom();
