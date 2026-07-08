<?php
declare(strict_types=1);
/** modules/product-lists/edit.php — Ürün listesi düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/product-lists.php';
auth_boot();
require_permission('product_lists.edit');

$id = (int) ($_GET['id'] ?? 0);
$existing = pl_get($id);
if (!$existing) { flash('error', 'Liste bulunamadı.'); redirect('modules/product-lists/index.php'); }

$errors = [];
$pl = $existing;
$items = pl_items($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pl = pl_fields_from_input($_POST) + ['id' => $id, 'list_no' => $existing['list_no']];
    $items = pl_items_from_post($_POST);
    if ($pl['title'] === '') { $errors[] = 'Başlık zorunludur.'; }
    if (!$errors) {
        if (pl_update($id, $pl, current_user_id())) {
            pl_save_items($id, $items);
            log_activity('product_list_update', 'product_list', $id, (string) $existing['list_no'], 'success', 'Ürün listesi güncellendi');
            flash('success', 'Değişiklikler kaydedildi.');
            redirect('modules/product-lists/view.php?id=' . $id);
        }
        $errors[] = 'Güncelleme başarısız.';
    }
}

layout_top('Liste Düzenle', 'product_lists');
?>
<div class="page-head"><h1 class="page-title">Liste Düzenle <span class="muted small">· <?= e($existing['list_no']) ?></span></h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/product-lists/view.php?id=' . $id)) ?>">← Görünüm</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/product-lists/edit.php?id=' . $id)) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/product-lists/view.php?id=' . $id)) ?>">Vazgeç</a>
        <?php if (can('product_lists.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/product-lists/delete.php?id=' . $id)) ?>"><?= icon('trash-2') ?>Sil</a><?php endif; ?>
    </div>
</form>
<?php layout_bottom();
