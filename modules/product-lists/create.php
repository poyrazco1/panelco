<?php
declare(strict_types=1);
/** modules/product-lists/create.php — Yeni ürün listesi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/product-lists.php';
auth_boot();
require_permission('product_lists.create');

$errors = [];
$pl = ['list_type' => (string) ($_GET['type'] ?? 'sale')];
$items = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pl = pl_fields_from_input($_POST);
    $items = pl_items_from_post($_POST);
    if ($pl['title'] === '') { $errors[] = 'Başlık zorunludur.'; }
    if (!$errors) {
        $id = pl_create($pl, current_user_id());
        if ($id > 0) {
            pl_save_items($id, $items);
            log_activity('product_list_create', 'product_list', $id, null, 'success', 'Ürün listesi oluşturuldu: ' . $pl['title']);
            flash('success', 'Liste oluşturuldu.');
            redirect('modules/product-lists/view.php?id=' . $id);
        }
        $errors[] = 'Liste kaydedilemedi.';
    }
}

layout_top('Yeni Ürün Listesi', 'product_lists');
?>
<div class="page-head"><h1 class="page-title">Yeni <?= e(pl_type_label((string) $pl['list_type'])) ?></h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/product-lists/index.php')) ?>">← Liste</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/product-lists/create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_form.php'; ?>
    <div class="form-actions" style="max-width:900px"><button class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/product-lists/index.php')) ?>">Vazgeç</a></div>
</form>
<?php layout_bottom();
