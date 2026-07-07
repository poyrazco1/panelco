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
        $id = (int) ($_POST['id'] ?? 0);
        $res = save_leave_type([
            'name'                   => $_POST['name'] ?? '',
            'code'                   => $_POST['code'] ?? '',
            'is_paid'                => isset($_POST['is_paid']),
            'deducts_annual_balance' => isset($_POST['deducts_annual_balance']),
            'allow_hourly'           => isset($_POST['allow_hourly']),
            'allow_half_day'         => isset($_POST['allow_half_day']),
            'sort_order'             => (int) ($_POST['sort_order'] ?? 0),
            'is_active'              => isset($_POST['is_active']),
        ], $id > 0 ? $id : null);
        if ($res['ok']) { flash('success', 'İzin türü kaydedildi.'); }
        else { flash('error', implode(' ', $res['errors'])); }
    } elseif ($op === 'toggle') {
        toggle_leave_type((int) ($_POST['id'] ?? 0), ($_POST['to'] ?? '') === 'active');
        flash('success', 'Durum güncellendi.');
    } elseif ($op === 'delete') {
        delete_leave_type((int) ($_POST['id'] ?? 0));
        flash('success', 'İzin türü silindi.');
    }
    http_response_code(303);
    redirect('modules/settings/leave-types.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId > 0 ? get_leave_type($editId) : null;
$types = get_leave_types(false);

layout_top('İzin Türleri', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">İzin Türleri</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2><?= $edit ? 'İzin Türünü Düzenle' : 'Yeni İzin Türü' ?></h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('modules/settings/leave-types.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="form-group">
                    <label for="lt-name">Ad</label>
                    <input type="text" id="lt-name" name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="lt-code">Kod</label>
                    <input type="text" id="lt-code" name="code" value="<?= e((string) ($edit['code'] ?? '')) ?>" placeholder="ör. annual" required>
                    <p class="field-hint">Harf/rakam/alt çizgi. Benzersiz olmalı.</p>
                </div>
                <div class="form-group">
                    <label for="lt-sort">Sıra No</label>
                    <input type="number" id="lt-sort" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-check"><label><input type="checkbox" name="is_paid" <?= !$edit || (int) $edit['is_paid'] === 1 ? 'checked' : '' ?>> Ücretli izin</label></div>
                <div class="form-check"><label><input type="checkbox" name="deducts_annual_balance" <?= $edit && (int) $edit['deducts_annual_balance'] === 1 ? 'checked' : '' ?>> Yıllık izin bakiyesinden düşer</label></div>
                <div class="form-check"><label><input type="checkbox" name="allow_hourly" <?= $edit && (int) $edit['allow_hourly'] === 1 ? 'checked' : '' ?>> Saatlik kullanılabilir</label></div>
                <div class="form-check"><label><input type="checkbox" name="allow_half_day" <?= !$edit || (int) $edit['allow_half_day'] === 1 ? 'checked' : '' ?>> Yarım gün kullanılabilir</label></div>
                <div class="form-check"><label><input type="checkbox" name="is_active" <?= !$edit || (int) $edit['is_active'] === 1 ? 'checked' : '' ?>> Aktif</label></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Güncelle' : 'Ekle' ?></button>
                    <?php if ($edit): ?><a class="btn" href="<?= e(url('modules/settings/leave-types.php')) ?>">Vazgeç</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Tanımlı Türler</h2></div>
        <div class="card-body">
            <?php if (empty($types)): ?>
                <p class="muted">Henüz izin türü yok.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Ad</th><th>Kod</th><th>Ücretli</th><th>Bakiye</th><th>Durum</th><th style="width:120px">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($types as $t): ?>
                        <tr>
                            <td><?= e((string) $t['name']) ?></td>
                            <td><code><?= e((string) $t['code']) ?></code></td>
                            <td><?= (int) $t['is_paid'] === 1 ? 'Evet' : 'Hayır' ?></td>
                            <td><?= (int) $t['deducts_annual_balance'] === 1 ? '<span class="badge badge-info">Düşer</span>' : '<span class="badge badge-muted">—</span>' ?></td>
                            <td><?= (int) $t['is_active'] === 1 ? '<span class="badge badge-success">' . icon('check-circle', 'icon-sm') . 'Aktif</span>' : '<span class="badge badge-muted">' . icon('x-circle', 'icon-sm') . 'Pasif</span>' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/settings/leave-types.php?edit=' . (int) $t['id'])) ?>" title="Düzenle"><?= icon('pencil') ?></a>
                                    <form method="post" action="<?= e(url('modules/settings/leave-types.php')) ?>" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="to" value="<?= (int) $t['is_active'] === 1 ? 'passive' : 'active' ?>">
                                        <button type="submit" class="btn btn-sm action-icon-btn" title="<?= (int) $t['is_active'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?>"><?= icon((int) $t['is_active'] === 1 ? 'x-circle' : 'check-circle') ?></button>
                                    </form>
                                    <form method="post" action="<?= e(url('modules/settings/leave-types.php')) ?>" style="display:inline" data-confirm="Bu izin türü silinsin mi?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
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
