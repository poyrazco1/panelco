<?php
declare(strict_types=1);

/**
 * modules/service/repairers.php
 * Dış Tamirciler listesi.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';

auth_boot();
require_permission('service');

$search = trim((string) ($_GET['q'] ?? ''));
$filter = (string) ($_GET['active'] ?? '');
$rows = get_repairers(false, $search, $filter);

layout_top('Dış Tamirciler', 'service');
?>

<div class="page-head">
    <h1 class="page-title">Dış Tamirciler</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/service/index.php')) ?>">← Servis</a>
        <a class="btn-add" href="<?= e(url('modules/service/repairer-create.php')) ?>" title="Yeni tamirci" aria-label="Yeni tamirci">+</a>
    </div>
</div>

<form method="get" action="<?= e(url('modules/service/repairers.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Ad, firma, uzmanlık"></div>
    <div class="form-group">
        <label for="active">Durum</label>
        <select id="active" name="active">
            <option value=""<?= $filter === '' ? ' selected' : '' ?>>Tümü</option>
            <option value="active"<?= $filter === 'active' ? ' selected' : '' ?>>Aktif</option>
            <option value="passive"<?= $filter === 'passive' ? ' selected' : '' ?>>Pasif</option>
        </select>
    </div>
    <div class="form-group"><button type="submit" class="btn btn-sm">Uygula</button></div>
</form>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Dış tamirci bulunamadı.</div></div></div>
<?php else: ?>
<form method="post" action="<?= e(url('modules/service/repairer-bulk.php')) ?>" data-confirm="Seçili tamirciler için işlem uygulansın mı?">
    <?= csrf_field() ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:34px"><input type="checkbox" id="checkAll" aria-label="Tümünü seç"></th>
                    <th style="width:60px">Düzenle</th>
                    <th>Ad</th>
                    <th>Firma</th>
                    <th>Telefon</th>
                    <th>Uzmanlık</th>
                    <th>Aktif</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" class="rowCheck" aria-label="Seç"></td>
                        <td><a class="btn btn-sm" href="<?= e(url('modules/service/repairer-edit.php?id=' . (int) $r['id'])) ?>" title="Düzenle"><?= icon('pencil') ?></a></td>
                        <td class="wrap"><?= e($r['name']) ?></td>
                        <td class="wrap"><?= e($r['company_name'] ?? '—') ?></td>
                        <td class="nowrap"><?= e($r['phone'] ?? '—') ?></td>
                        <td><?= e($r['specialty'] ?? '—') ?></td>
                        <td><?php if ((int) $r['is_active'] === 1): ?><span class="badge badge-success"><?= icon('check-circle','icon-sm') ?>Aktif</span><?php else: ?><span class="badge badge-muted"><?= icon('x-circle','icon-sm') ?>Pasif</span><?php endif; ?></td>
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
    if (all) { all.addEventListener('change', function(){ for (var i=0;i<boxes.length;i++){ boxes[i].checked = all.checked; } }); }
})();
</script>
<?php endif; ?>

<?php
layout_bottom();
