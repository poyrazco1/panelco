<?php
declare(strict_types=1);
/** modules/orders/create.php — Yeni sipariş. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/customers.php';
auth_boot();
require_permission('orders.create');

$errors = [];
$o = ['status' => 'draft', 'order_date' => date('Y-m-d'), 'currency' => 'TRY', 'payment_status' => 'pending', 'items' => []];
$vatDefault = (string) (function_exists('app_setting_get') ? (app_setting_get('default_vat', '20') ?? '20') : '20');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $h = order_header_from_input($_POST);
    $items = order_items_from_input($_POST);
    $errors = order_validate($h, $items);
    if (!$errors) {
        $id = create_order($h, $items, current_user_id());
        if ($id > 0) {
            log_activity('order_create', 'order', $id, null, 'success', 'Sipariş oluşturuldu');
            flash('success', 'Sipariş oluşturuldu.');
            redirect('modules/orders/view.php?id=' . $id);
        }
        $errors[] = 'Sipariş kaydedilemedi.';
    }
    $o = array_merge($o, $h, ['items' => $items]);
}
$customers = customers_for_select();
layout_top('Yeni Sipariş', 'orders');
?>
<div class="page-head"><h1 class="page-title">Yeni Sipariş</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/orders/index.php')) ?>">← Siparişler</a></div></div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/orders/create.php')) ?>" id="quoteForm" novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_order-form.php'; ?>
    <div class="form-actions" style="max-width:980px"><button type="submit" class="btn btn-primary">Kaydet</button><a class="btn" href="<?= e(url('modules/orders/index.php')) ?>">Vazgeç</a></div>
</form>
<script>window.TSOFT_SEARCH_URL = <?= json_encode(url('api/tsoft-search.php'), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= e(asset('js/quotes.js')) ?>"></script>
<?php layout_bottom();
