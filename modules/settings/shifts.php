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
        $res = save_work_schedule([
            'name'             => $_POST['name'] ?? '',
            'start_time'       => $_POST['start_time'] ?? '',
            'end_time'         => $_POST['end_time'] ?? '',
            'break_minutes'    => (int) ($_POST['break_minutes'] ?? 0),
            'weekly_work_days' => array_map('intval', (array) ($_POST['weekly_work_days'] ?? [])),
            'is_default'       => isset($_POST['is_default']),
            'is_active'        => isset($_POST['is_active']),
        ], (int) ($_POST['id'] ?? 0) ?: null);
        $res['ok'] ? flash('success', 'Program kaydedildi.') : flash('error', implode(' ', $res['errors']));
    } elseif ($op === 'delete') {
        delete_work_schedule((int) ($_POST['id'] ?? 0));
        flash('success', 'Program silindi.');
    }
    http_response_code(303);
    redirect('modules/settings/shifts.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId > 0 ? get_work_schedule($editId) : null;
$schedules = get_work_schedules(false);
$labels = leave_weekday_labels();
$editDays = $edit ? array_map('intval', array_filter(explode(',', (string) $edit['weekly_work_days']), static fn($x) => $x !== '')) : [1, 2, 3, 4, 5];

layout_top('Vardiya / Mesai Ayarları', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Vardiya / Mesai Ayarları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2><?= $edit ? 'Programı Düzenle' : 'Yeni Program' ?></h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('modules/settings/shifts.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="form-group"><label for="sc-name">Ad</label><input type="text" id="sc-name" name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" placeholder="ör. Standart Mesai" required></div>
                <div class="form-row">
                    <div class="form-group"><label for="sc-start">Başlangıç</label><input type="time" id="sc-start" name="start_time" value="<?= e(substr((string) ($edit['start_time'] ?? '09:00'), 0, 5)) ?>"></div>
                    <div class="form-group"><label for="sc-end">Bitiş</label><input type="time" id="sc-end" name="end_time" value="<?= e(substr((string) ($edit['end_time'] ?? '18:00'), 0, 5)) ?>"></div>
                    <div class="form-group"><label for="sc-break">Mola (dk)</label><input type="number" id="sc-break" name="break_minutes" min="0" value="<?= (int) ($edit['break_minutes'] ?? 60) ?>"></div>
                </div>
                <div class="form-group">
                    <label>Haftalık Çalışma Günleri</label>
                    <div class="check-inline">
                        <?php foreach ($labels as $w => $lbl): ?>
                            <label><input type="checkbox" name="weekly_work_days[]" value="<?= (int) $w ?>" <?= in_array($w, $editDays, true) ? 'checked' : '' ?>> <?= e($lbl) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-check"><label><input type="checkbox" name="is_default" <?= $edit && (int) $edit['is_default'] === 1 ? 'checked' : '' ?>> Varsayılan program (ay oluştururken kullanılır)</label></div>
                <div class="form-check"><label><input type="checkbox" name="is_active" <?= !$edit || (int) $edit['is_active'] === 1 ? 'checked' : '' ?>> Aktif</label></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Güncelle' : 'Ekle' ?></button>
                    <?php if ($edit): ?><a class="btn" href="<?= e(url('modules/settings/shifts.php')) ?>">Vazgeç</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Programlar</h2></div>
        <div class="card-body">
            <?php if (empty($schedules)): ?>
                <p class="muted">Program yok.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Ad</th><th>Mesai</th><th>Mola</th><th>Günler</th><th>Durum</th><th style="width:90px">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($schedules as $sc):
                        $dayNums = array_map('intval', array_filter(explode(',', (string) $sc['weekly_work_days']), static fn($x) => $x !== ''));
                        $dayTxt = implode(', ', array_map(static fn($n) => mb_substr($labels[$n] ?? '', 0, 2), $dayNums));
                    ?>
                        <tr>
                            <td><?= e((string) $sc['name']) ?><?= (int) $sc['is_default'] === 1 ? ' <span class="badge badge-info">Varsayılan</span>' : '' ?></td>
                            <td><?= e(substr((string) ($sc['start_time'] ?? '—'), 0, 5)) ?> – <?= e(substr((string) ($sc['end_time'] ?? '—'), 0, 5)) ?></td>
                            <td><?= (int) $sc['break_minutes'] ?> dk</td>
                            <td><span class="muted small"><?= e($dayTxt) ?></span></td>
                            <td><?= (int) $sc['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/settings/shifts.php?edit=' . (int) $sc['id'])) ?>" title="Düzenle"><?= icon('pencil') ?></a>
                                    <form method="post" action="<?= e(url('modules/settings/shifts.php')) ?>" style="display:inline" data-confirm="Program silinsin mi?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $sc['id'] ?>">
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
