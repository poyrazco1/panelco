<?php
declare(strict_types=1);

/**
 * modules/settings/users.php
 * Kullanıcı listesi + aktif/pasif değiştirme (POST + CSRF).
 */
require_once __DIR__ . '/../../includes/permissions.php';

auth_boot();
require_permission('settings');

$me = current_user();

/* -------- Aktif/pasif değiştirme (POST) -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    csrf_check();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        flash('error', 'Geçersiz kullanıcı.');
        redirect('modules/settings/users.php');
    }
    if ($id === $me['id']) {
        flash('error', 'Kendi hesabınızın durumunu değiştiremezsiniz.');
        redirect('modules/settings/users.php');
    }

    try {
        $st = db()->prepare('SELECT is_active, role_id FROM users WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch();

        if (!$row) {
            flash('info', 'Kullanıcı bulunamadı.');
            redirect('modules/settings/users.php');
        }

        $newActive     = ((int) $row['is_active'] === 1) ? 0 : 1;
        $targetIsAdmin = is_admin_role($row['role_id'] !== null ? (int) $row['role_id'] : null);
        $postAdmins    = count_active_admins($id) + (($newActive === 1 && $targetIsAdmin) ? 1 : 0);

        if ($postAdmins < 1) {
            flash('error', 'En az bir aktif yönetici kalmalıdır.');
            redirect('modules/settings/users.php');
        }

        db()->prepare('UPDATE users SET is_active = :a WHERE id = :id')
            ->execute([':a' => $newActive, ':id' => $id]);
        flash('success', 'Kullanıcı durumu güncellendi.');
    } catch (Throwable $e) {
        flash('error', safe_error('Durum güncellenemedi.', 'user toggle: ' . $e->getMessage()));
    }
    redirect('modules/settings/users.php');
}

/* -------- Sayfalama -------- */
$perPage = 20;
try {
    $st = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :k LIMIT 1');
    $st->execute([':k' => 'items_per_page']);
    $v = (int) $st->fetchColumn();
    if ($v >= 5 && $v <= 200) {
        $perPage = $v;
    }
} catch (Throwable $e) {
    log_error('users perPage: ' . $e->getMessage());
}

$page  = max(1, (int) ($_GET['page'] ?? 1));
$rows  = [];
$pages = 1;
try {
    $total  = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $pages  = max(1, (int) ceil($total / $perPage));
    $page   = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $sql = 'SELECT u.id, u.username, u.full_name, u.email, u.is_active, u.created_at,
                   r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            ORDER BY u.id ASC
            LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
    $rows = db()->query($sql)->fetchAll();
} catch (Throwable $e) {
    log_error('users list: ' . $e->getMessage());
}

layout_top('Kullanıcılar', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Kullanıcılar</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Ayarlar</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('modules/settings/user-create.php')) ?>">Yeni kullanıcı</a>
    </div>
</div>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body">
        <div class="empty">Henüz kullanıcı yok.</div>
    </div></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Kullanıcı adı</th>
                    <th>Ad Soyad</th>
                    <th>E-posta</th>
                    <th>Rol</th>
                    <th>Durum</th>
                    <th style="text-align:right">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $isSelf = ((int) $r['id'] === $me['id']); ?>
                    <tr>
                        <td><?= e($r['username']) ?><?= $isSelf ? ' <span class="badge badge-muted">siz</span>' : '' ?></td>
                        <td class="wrap"><?= e($r['full_name'] !== '' ? $r['full_name'] : '—') ?></td>
                        <td class="wrap"><?= e($r['email']) ?></td>
                        <td><?= e($r['role_name'] ?? 'Atanmamış') ?></td>
                        <td>
                            <?php if ((int) $r['is_active'] === 1): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-muted">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions" style="justify-content:flex-end">
                                <a class="btn btn-sm" href="<?= e(url('modules/settings/user-edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil') ?>Düzenle</a>
                                <?php if (!$isSelf): ?>
                                    <form method="post" action="<?= e(url('modules/settings/users.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm">
                                            <?= (int) $r['is_active'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= e(url('modules/settings/user-delete.php')) ?>"
                                          data-confirm="“<?= e($r['username']) ?>” kullanıcısını silmek istediğinize emin misiniz?">
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

    <?php if ($pages > 1): ?>
        <div class="row-actions" style="margin-top:16px">
            <?php if ($page > 1): ?>
                <a class="btn btn-sm" href="<?= e(url('modules/settings/users.php?page=' . ($page - 1))) ?>">← Önceki</a>
            <?php endif; ?>
            <span class="btn btn-sm" style="pointer-events:none">Sayfa <?= (int) $page ?> / <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-sm" href="<?= e(url('modules/settings/users.php?page=' . ($page + 1))) ?>">Sonraki →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
layout_bottom();
