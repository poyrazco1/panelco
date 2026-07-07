<?php
declare(strict_types=1);

/** modules/quotes/create.php — Yeni teklif (satırlı + T-Soft arama). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/quotes.php';
require_once __DIR__ . '/../../includes/customers.php';

auth_boot();
require_permission('quotes.create');

$errors = [];
$q = ['status' => 'draft', 'quote_date' => date('Y-m-d'), 'currency' => 'TRY', 'vat_mode' => 'excl', 'items' => []];
$vatDefault = (string) (function_exists('app_setting_get') ? (app_setting_get('default_vat', '20') ?? '20') : '20');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $h = quote_header_from_input($_POST);
    $items = quote_items_from_input($_POST);
    $errors = quote_validate($h, $items);
    if (!$errors) {
        $id = create_quote($h, $items, current_user_id());
        if ($id > 0) {
            log_activity('quote_create', 'quote', $id, null, 'success', 'Teklif oluşturuldu');
            flash('success', 'Teklif oluşturuldu.');
            redirect('modules/quotes/view.php?id=' . $id);
        }
        $errors[] = 'Teklif kaydedilemedi.';
    }
    $q = array_merge($q, $h, ['items' => $items]);
}

$customers = customers_for_select();
layout_top('Yeni Teklif', 'quotes');
?>
<div class="page-head">
    <h1 class="page-title">Yeni Teklif</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/quotes/index.php')) ?>">← Teklifler</a></div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<form method="post" action="<?= e(url('modules/quotes/create.php')) ?>" id="quoteForm" novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_quote-form.php'; ?>
    <div class="form-actions" style="max-width:980px">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/quotes/index.php')) ?>">Vazgeç</a>
    </div>
</form>

<script>
    window.TSOFT_SEARCH_URL = <?= json_encode(url('api/tsoft-search.php'), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(asset('js/quotes.js')) ?>"></script>
<?php layout_bottom();
