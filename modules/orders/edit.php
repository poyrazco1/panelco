<?php
declare(strict_types=1);
/** modules/orders/edit.php — Sipariş düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/customers.php';
auth_boot();
require_permission('orders.edit');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$o = get_order($id);
if (!$o) { flash('error', 'Sipariş bulunamadı.'); redirect('modules/orders/index.php'); }
$errors = [];
$vatDefault = (string) (function_exists('app_setting_get') ? (app_setting_get('default_vat', '20') ?? '20') : '20');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $h = order_header_from_input($_POST);
    $items = order_items_from_input($_POST);
    $errors = order_validate($h, $items);
    if (!$errors) {
        if (update_order($id, $h, $items, current_user_id())) {
            log_activity('order_edit', 'order', $id, null, 'success', 'Sipariş güncellendi');
            flash('success', 'Sipariş güncellendi.');
            redirect('modules/orders/view.php?id=' . $id);
        }
        $errors[] = 'Güncelleme başarısız.';
    }
    $o = array_merge($o, $h, ['items' => $items]);
}
$customers = customers_for_select();
layout_top('Sipariş Düzenle', 'orders');
?>
<div class="page-head"><h1 class="page-title">Sipariş Düzenle · <?= e((string) $o['order_no']) ?></h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/orders/view.php?id=' . $id)) ?>">← Detay</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/orders/edit.php')) ?>" id="quoteForm" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_order-form.php'; ?>
    <div class="form-actions" style="max-width:980px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/orders/view.php?id=' . $id)) ?>">Vazgeç</a>
    <?php if (can('orders.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/orders/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?></div>
</form>
<script>window.TSOFT_SEARCH_URL = <?= json_encode(url('api/tsoft-search.php'), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= e(asset('js/quotes.js')) ?>"></script>
<?php layout_bottom();
