<?php
declare(strict_types=1);

/**
 * modules/settings/shipping.php
 * Kargo yöntemleri listesi.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipping.php';

auth_boot();
require_permission('shipping');

$search = trim((string) ($_GET['q'] ?? ''));
$filter = (string) ($_GET['active'] ?? '');
$order  = (string) ($_GET['order'] ?? 'sort');

$rows = shipping_all($search, $filter, $order);

layout_top('Kargo Yöntemleri', 'shipping');
?>

<div class="page-head">
    <h1 class="page-title">Kargo Yöntemleri</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Ayarlar</a>
        <a class="btn-add" href="<?= e(url('modules/settings/shipping-create.php')) ?>" title="Yeni kargo" aria-label="Yeni kargo">+</a>
    </div>
</div>

<form method="get" action="<?= e(url('modules/settings/shipping.php')) ?>" class="toolbar">
    <div class="form-group">
        <label for="q">Ara</label>
        <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Kargo adı">
    </div>
    <div class="form-group">
        <label for="active">Durum</label>
        <select id="active" name="active">
            <option value=""<?= $filter === '' ? ' selected' : '' ?>>Tümü</option>
            <option value="active"<?= $filter === 'active' ? ' selected' : '' ?>>Aktif</option>
            <option value="passive"<?= $filter === 'passive' ? ' selected' : '' ?>>Pasif</option>
        </select>
    </div>
    <div class="form-group">
        <label for="order">Sıralama</label>
        <select id="order" name="order">
            <option value="sort"<?= $order === 'sort' ? ' selected' : '' ?>>Sıra No</option>
            <option value="name"<?= $order === 'name' ? ' selected' : '' ?>>Ada göre</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-sm">Uygula</button>
    </div>
</form>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Kargo yöntemi bulunamadı.</div></div></div>
<?php else: ?>
<form method="post" action="<?= e(url('modules/settings/shipping-bulk.php')) ?>"
      data-confirm="Seçili kargolar için işlem uygulansın mı?">
    <?= csrf_field() ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:34px"><input type="checkbox" id="checkAll" aria-label="Tümünü seç"></th>
                    <th style="width:60px">Düzenle</th>
                    <th>Görsel</th>
                    <th>Ad</th>
                    <th>Ücret Bilgileri</th>
                    <th>Ücretsiz Kargo Limiti</th>
                    <th>Sıra No</th>
                    <th>Aktif</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $editUrl = url('modules/settings/shipping-edit.php?id=' . (int) $r['id']); ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" class="rowCheck" aria-label="Seç"></td>
                        <td><a class="btn btn-sm" href="<?= e($editUrl) ?>" title="Düzenle"><?= icon('pencil') ?></a></td>
                        <td><?= shipping_logo_html($r['logo_path'] ?? null, (string) $r['name']) ?></td>
                        <td class="wrap"><?= e($r['name']) ?></td>
                        <td>
                            <?php $pc = (int) $r['price_count']; ?>
                            <?php if ($pc > 0): ?>
                                <a class="price-link" href="<?= e($editUrl) ?>#ucretler"><?= $pc ?> Ücretlendirme</a>
                            <?php else: ?>
                                <a class="price-empty" href="<?= e($editUrl) ?>#ucretler">Ücret Bilgisi Giriniz!</a>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['free_shipping_limit'] !== null ? fmt_money((float) $r['free_shipping_limit']) . ' TL' : '—' ?></td>
                        <td><?= (int) $r['sort_order'] ?></td>
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
