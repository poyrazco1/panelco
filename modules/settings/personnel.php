<?php
declare(strict_types=1);

/**
 * modules/settings/personnel.php
 * Personel listesi.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/personnel.php';

auth_boot();
require_permission('personnel');

$filters = [
    'search'     => trim((string) ($_GET['q'] ?? '')),
    'department' => (string) ($_GET['dep'] ?? ''),
    'status'     => (string) ($_GET['status'] ?? ''),
    'active'     => (string) ($_GET['active'] ?? ''),
    'order'      => (string) ($_GET['order'] ?? 'sort'),
];

$rows        = get_personnel($filters);
$departments = personnel_departments();
$statuses    = personnel_status_labels();
?>
<?php
layout_top('Personeller', 'personnel');
?>

<div class="page-head">
    <h1 class="page-title">Personeller</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Ayarlar</a>
        <a class="btn-add" href="<?= e(url('modules/settings/personnel-create.php')) ?>" title="Yeni personel" aria-label="Yeni personel">+</a>
    </div>
</div>

<form method="get" action="<?= e(url('modules/settings/personnel.php')) ?>" class="toolbar">
    <div class="form-group">
        <label for="q">Ara</label>
        <input type="text" id="q" name="q" value="<?= e($filters['search']) ?>" placeholder="Ad, telefon, e-posta, departman">
    </div>
    <div class="form-group">
        <label for="dep">Departman</label>
        <select id="dep" name="dep">
            <option value="">Tümü</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= e($d) ?>"<?= $filters['department'] === $d ? ' selected' : '' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="status">Çalışma durumu</label>
        <select id="status" name="status">
            <option value="">Tümü</option>
            <?php foreach ($statuses as $k => $lbl): ?>
                <option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="active">Kayıt</label>
        <select id="active" name="active">
            <option value=""<?= $filters['active'] === '' ? ' selected' : '' ?>>Tümü</option>
            <option value="active"<?= $filters['active'] === 'active' ? ' selected' : '' ?>>Aktif</option>
            <option value="passive"<?= $filters['active'] === 'passive' ? ' selected' : '' ?>>Pasif</option>
        </select>
    </div>
    <div class="form-group">
        <label for="order">Sıralama</label>
        <select id="order" name="order">
            <option value="sort"<?= $filters['order'] === 'sort' ? ' selected' : '' ?>>Sıra No</option>
            <option value="name"<?= $filters['order'] === 'name' ? ' selected' : '' ?>>Ada göre</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-sm">Uygula</button>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Personel bulunamadı.</div></div></div>
<?php else: ?>
<form method="post" action="<?= e(url('modules/settings/personnel-bulk.php')) ?>"
      data-confirm="Seçili personeller için işlem uygulansın mı?">
    <?= csrf_field() ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:34px"><input type="checkbox" id="checkAll" aria-label="Tümünü seç"></th>
                    <th style="width:60px">Düzenle</th>
                    <th>Personel</th>
                    <th>Departman</th>
                    <th>Görev</th>
                    <th>Telefon</th>
                    <th>Panel Kullanıcısı</th>
                    <th>Durum</th>
                    <th>Aktif</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $editUrl = url('modules/settings/personnel-edit.php?id=' . (int) $r['id']);
                    $stKey = (string) $r['employment_status'];
                    $stCls = ['active' => 'badge-success', 'passive' => 'badge-muted', 'on_leave' => 'badge-leave', 'left' => 'badge-left'][$stKey] ?? 'badge-muted';
                    ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" class="rowCheck" aria-label="Seç"></td>
                        <td><a class="btn btn-sm" href="<?= e($editUrl) ?>" title="Düzenle"><?= icon('pencil') ?></a></td>
                        <td>
                            <div class="staff-cell">
                                <?= personnel_photo_html($r['photo_path'] ?? null, (string) $r['full_name']) ?>
                                <div style="min-width:0">
                                    <div class="st-main"><?= e($r['full_name']) ?></div>
                                    <?php if (!empty($r['email'])): ?><div class="st-sub"><?= e($r['email']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= e($r['department'] ?? '—') ?></td>
                        <td><?= e($r['position'] ?? '—') ?></td>
                        <td class="nowrap"><?= e($r['phone'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($r['u_username'])): ?>
                                <span class="badge badge-info"><?= e($r['u_username']) ?></span>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $stCls ?>"><?= e($statuses[$stKey] ?? $stKey) ?></span></td>
                        <td>
                            <?php if ((int) $r['is_active'] === 1): ?>
                                <span class="badge badge-success"><?= icon('check-circle','icon-sm') ?>Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-muted"><?= icon('x-circle','icon-sm') ?>Pasif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row-actions" style="margin-top:16px;align-items:center">
        <label for="bulk_action" class="small muted" style="margin:0">Seçilenler:</label>
        <select name="action" id="bulk_action" style="width:auto;min-width:180px">
            <option value="">— İşlem seçin —</option>
            <option value="activate">Aktif yap</option>
            <option value="deactivate">Pasif yap</option>
            <option value="delete">Sil</option>
        </select>
        <button type="submit" class="btn btn-sm">Uygula</button>
    </div>
</form>

<script>
(function(){
    var all = document.getElementById('checkAll');
    var boxes = document.querySelectorAll('.rowCheck');
    if (all) {
        all.addEventListener('change', function(){
            for (var i=0;i<boxes.length;i++){ boxes[i].checked = all.checked; }
        });
    }
})();
</script>
<?php endif; ?>

<?php
layout_bottom();
