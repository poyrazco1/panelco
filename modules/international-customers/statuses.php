<?php
declare(strict_types=1);
/** modules/international-customers/statuses.php — Yurtdışı müşteri durumları yönetimi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.edit');

$colors = ['info' => 'Mavi', 'leave' => 'Turuncu', 'success' => 'Yeşil', 'danger' => 'Kırmızı', 'muted' => 'Gri'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $uid = current_user_id();
    try {
        if ($action === 'add') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $color = (string) ($_POST['color'] ?? 'muted');
            if (!isset($colors[$color])) { $color = 'muted'; }
            if ($name !== '') {
                db()->prepare('INSERT INTO international_customer_statuses (name, color, sort_order, created_by) VALUES (:n,:c,:s,:by)')
                    ->execute([':n' => $name, ':c' => $color, ':s' => (int) ($_POST['sort_order'] ?? 0), ':by' => $uid]);
                flash('success', 'Durum eklendi.');
            }
        } elseif ($action === 'update') {
            $sid = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $color = (string) ($_POST['color'] ?? 'muted');
            if (!isset($colors[$color])) { $color = 'muted'; }
            db()->prepare('UPDATE international_customer_statuses SET name=:n, color=:c, sort_order=:s, is_active=:a, updated_by=:by WHERE id=:id')
                ->execute([':n' => $name, ':c' => $color, ':s' => (int) ($_POST['sort_order'] ?? 0), ':a' => isset($_POST['is_active']) ? 1 : 0, ':by' => $uid, ':id' => $sid]);
            flash('success', 'Durum güncellendi.');
        } elseif ($action === 'delete') {
            // Kullanımda mı? Fiziksel silme yerine pasifleştir.
            $sid = (int) ($_POST['id'] ?? 0);
            db()->prepare('UPDATE international_customer_statuses SET is_active = 0 WHERE id = :id')->execute([':id' => $sid]);
            flash('success', 'Durum pasifleştirildi.');
        }
    } catch (Throwable $e) { log_error('ic statuses: ' . $e->getMessage()); flash('error', 'İşlem başarısız.'); }
    http_response_code(303);
    redirect('modules/international-customers/statuses.php');
}

$rows = db()->query('SELECT * FROM international_customer_statuses ORDER BY sort_order ASC, id ASC')->fetchAll();
layout_top('Müşteri Durumları', 'settings');
?>
<div class="page-head"><h1 class="page-title">Yurtdışı Müşteri Durumları</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div></div>
<?= render_flashes() ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Durum</th><th>Renk</th><th>Sıra</th><th>Aktif</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr><form method="post" action="<?= e(url('modules/international-customers/statuses.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <td><input type="text" name="name" value="<?= e($r['name']) ?>"></td>
        <td><select name="color"><?php foreach ($colors as $ck => $cl): ?><option value="<?= e($ck) ?>"<?= $r['color'] === $ck ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?></select></td>
        <td style="width:80px"><input type="number" name="sort_order" value="<?= (int) $r['sort_order'] ?>" style="width:64px"></td>
        <td><label class="check-inline"><input type="checkbox" name="is_active" value="1"<?= (int) $r['is_active'] === 1 ? ' checked' : '' ?>></label></td>
        <td class="nowrap"><button class="btn btn-xs btn-primary">Kaydet</button></form>
            <form method="post" action="<?= e(url('modules/international-customers/statuses.php')) ?>" style="display:inline" onsubmit="return confirm('Pasifleştirilsin mi?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-xs"><?= icon('trash-2', 'icon-xs') ?></button></form></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<div class="card" style="max-width:640px"><div class="card-header"><strong>Yeni Durum</strong></div><div class="card-body">
    <form method="post" action="<?= e(url('modules/international-customers/statuses.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group"><label>Ad</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Renk</label><select name="color"><?php foreach ($colors as $ck => $cl): ?><option value="<?= e($ck) ?>"><?= e($cl) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Sıra</label><input type="number" name="sort_order" value="0"></div>
        </div>
        <button class="btn btn-primary btn-sm"><?= icon('plus') ?>Ekle</button>
    </form>
</div></div>
<?php layout_bottom();
