<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'save') {
        $res = save_holiday([
            'holiday_date' => $_POST['holiday_date'] ?? '',
            'title'        => $_POST['title'] ?? '',
            'is_recurring' => isset($_POST['is_recurring']),
            'is_active'    => isset($_POST['is_active']),
        ], (int) ($_POST['id'] ?? 0) ?: null);
        if ($res['ok']) { flash('success', 'Tatil kaydedildi.'); }
        else { flash('error', implode(' ', $res['errors'])); }
    } elseif ($op === 'delete') {
        delete_holiday((int) ($_POST['id'] ?? 0));
        flash('success', 'Tatil silindi.');
    }
    http_response_code(303);
    redirect('modules/settings/holidays.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    foreach (get_company_holidays() as $h) { if ((int) $h['id'] === $editId) { $edit = $h; break; } }
}
$holidays = get_company_holidays();

layout_top('Resmi Tatiller', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Resmi Tatiller</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2><?= $edit ? 'Tatili Düzenle' : 'Yeni Tatil' ?></h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('modules/settings/holidays.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="form-group">
                    <label for="h-date">Tarih</label>
                    <input type="date" id="h-date" name="holiday_date" value="<?= e((string) ($edit['holiday_date'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="h-title">Başlık</label>
                    <input type="text" id="h-title" name="title" value="<?= e((string) ($edit['title'] ?? '')) ?>" placeholder="ör. Cumhuriyet Bayramı" required>
                </div>
                <div class="form-check"><label><input type="checkbox" name="is_recurring" <?= $edit && (int) $edit['is_recurring'] === 1 ? 'checked' : '' ?>> Her yıl tekrarlanır (yalnızca gün/ay dikkate alınır)</label></div>
                <div class="form-check"><label><input type="checkbox" name="is_active" <?= !$edit || (int) $edit['is_active'] === 1 ? 'checked' : '' ?>> Aktif</label></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Güncelle' : 'Ekle' ?></button>
                    <?php if ($edit): ?><a class="btn" href="<?= e(url('modules/settings/holidays.php')) ?>">Vazgeç</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Tanımlı Tatiller</h2></div>
        <div class="card-body">
            <?php if (empty($holidays)): ?>
                <p class="muted">Henüz tatil tanımı yok. İzin gün hesabında tatiller düşülemez.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Tarih</th><th>Başlık</th><th>Tekrar</th><th>Durum</th><th style="width:90px">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($holidays as $h): ?>
                        <tr>
                            <td><?= e(fmt_date((string) $h['holiday_date'])) ?></td>
                            <td><?= e((string) $h['title']) ?></td>
                            <td><?= (int) $h['is_recurring'] === 1 ? '<span class="badge badge-info">Yıllık</span>' : '<span class="badge badge-muted">Tek</span>' ?></td>
                            <td><?= (int) $h['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/settings/holidays.php?edit=' . (int) $h['id'])) ?>" title="Düzenle"><?= icon('pencil') ?></a>
                                    <form method="post" action="<?= e(url('modules/settings/holidays.php')) ?>" style="display:inline" data-confirm="Bu tatil silinsin mi?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
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
