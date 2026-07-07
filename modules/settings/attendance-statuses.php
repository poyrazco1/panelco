<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/attendance.php';

auth_boot();
require_permission('attendance');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'save') {
        $res = save_attendance_status([
            'name'                 => $_POST['name'] ?? '',
            'code'                 => $_POST['code'] ?? '',
            'color'                => $_POST['color'] ?? '',
            'is_paid'              => isset($_POST['is_paid']),
            'counts_as_workday'    => isset($_POST['counts_as_workday']),
            'counts_as_absence'    => isset($_POST['counts_as_absence']),
            'deducts_annual_leave' => isset($_POST['deducts_annual_leave']),
            'sort_order'           => (int) ($_POST['sort_order'] ?? 0),
            'is_active'            => isset($_POST['is_active']),
        ], (int) ($_POST['id'] ?? 0) ?: null);
        $res['ok'] ? flash('success', 'Durum kaydedildi.') : flash('error', implode(' ', $res['errors']));
    } elseif ($op === 'toggle') {
        toggle_attendance_status((int) ($_POST['id'] ?? 0), ($_POST['to'] ?? '') === 'active');
        flash('success', 'Durum güncellendi.');
    } elseif ($op === 'delete') {
        delete_attendance_status((int) ($_POST['id'] ?? 0)) ? flash('success', 'Durum silindi.') : flash('error', 'Kullanımda olan durum silinemez.');
    }
    http_response_code(303);
    redirect('modules/settings/attendance-statuses.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId > 0 ? get_attendance_status($editId) : null;
$statuses = get_attendance_statuses(false);

layout_top('Puantaj Kuralları', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Puantaj Kuralları (Durumlar)</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2><?= $edit ? 'Durumu Düzenle' : 'Yeni Durum' ?></h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('modules/settings/attendance-statuses.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="form-row">
                    <div class="form-group"><label for="as-name">Ad</label><input type="text" id="as-name" name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" required></div>
                    <div class="form-group"><label for="as-code">Kod</label><input type="text" id="as-code" name="code" value="<?= e((string) ($edit['code'] ?? '')) ?>" placeholder="ör. worked" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="as-color">Renk</label><input type="color" id="as-color" name="color" value="<?= e((string) ($edit['color'] ?? '#eef2f7')) ?>"></div>
                    <div class="form-group"><label for="as-sort">Sıra No</label><input type="number" id="as-sort" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></div>
                </div>
                <div class="form-check"><label><input type="checkbox" name="is_paid" <?= !$edit || (int) $edit['is_paid'] === 1 ? 'checked' : '' ?>> Ücretli</label></div>
                <div class="form-check"><label><input type="checkbox" name="counts_as_workday" <?= $edit && (int) $edit['counts_as_workday'] === 1 ? 'checked' : '' ?>> Çalışılmış gün sayılır</label></div>
                <div class="form-check"><label><input type="checkbox" name="counts_as_absence" <?= $edit && (int) $edit['counts_as_absence'] === 1 ? 'checked' : '' ?>> Eksik gün sayılır</label></div>
                <div class="form-check"><label><input type="checkbox" name="deducts_annual_leave" <?= $edit && (int) $edit['deducts_annual_leave'] === 1 ? 'checked' : '' ?>> Yıllık izinden düşer</label></div>
                <div class="form-check"><label><input type="checkbox" name="is_active" <?= !$edit || (int) $edit['is_active'] === 1 ? 'checked' : '' ?>> Aktif</label></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Güncelle' : 'Ekle' ?></button>
                    <?php if ($edit): ?><a class="btn" href="<?= e(url('modules/settings/attendance-statuses.php')) ?>">Vazgeç</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Tanımlı Durumlar</h2></div>
        <div class="card-body">
            <?php if (empty($statuses)): ?>
                <p class="muted">Durum yok.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Kısa</th><th>Ad</th><th>Kod</th><th>Çalışma</th><th>Eksik</th><th>Durum</th><th style="width:120px">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($statuses as $s): ?>
                        <tr>
                            <td><span class="att-chip" style="background:<?= e((string) ($s['color'] ?? '#eef2f7')) ?>"><?= e((string) $s['short']) ?></span></td>
                            <td><?= e((string) $s['name']) ?></td>
                            <td><code><?= e((string) $s['code']) ?></code></td>
                            <td><?= (int) $s['counts_as_workday'] === 1 ? 'Evet' : '—' ?></td>
                            <td><?= (int) $s['counts_as_absence'] === 1 ? 'Evet' : '—' ?></td>
                            <td><?= (int) $s['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/settings/attendance-statuses.php?edit=' . (int) $s['id'])) ?>" title="Düzenle"><?= icon('pencil') ?></a>
                                    <form method="post" action="<?= e(url('modules/settings/attendance-statuses.php')) ?>" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <input type="hidden" name="to" value="<?= (int) $s['is_active'] === 1 ? 'passive' : 'active' ?>">
                                        <button type="submit" class="btn btn-sm action-icon-btn" title="<?= (int) $s['is_active'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?>"><?= icon((int) $s['is_active'] === 1 ? 'x-circle' : 'check-circle') ?></button>
                                    </form>
                                    <form method="post" action="<?= e(url('modules/settings/attendance-statuses.php')) ?>" style="display:inline" data-confirm="Bu durum silinsin mi?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm action-icon-btn is-danger" title="Sil"><?= icon('trash-2') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
layout_bottom();
