<?php
declare(strict_types=1);

/** modules/quotes/edit.php — Teklif düzenle. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/quotes.php';
require_once __DIR__ . '/../../includes/customers.php';

auth_boot();
require_permission('quotes.edit');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$q  = get_quote($id);
if (!$q) { flash('error', 'Teklif bulunamadı.'); redirect('modules/quotes/index.php'); }

$errors = [];
$vatDefault = (string) (function_exists('app_setting_get') ? (app_setting_get('default_vat', '20') ?? '20') : '20');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $h = quote_header_from_input($_POST);
    $items = quote_items_from_input($_POST);
    $errors = quote_validate($h, $items);
    if (!$errors) {
        if (update_quote($id, $h, $items, current_user_id())) {
            log_activity('quote_edit', 'quote', $id, null, 'success', 'Teklif güncellendi');
            flash('success', 'Teklif güncellendi.');
            redirect('modules/quotes/view.php?id=' . $id);
        }
        $errors[] = 'Güncelleme başarısız.';
    }
    $q = array_merge($q, $h, ['items' => $items]);
}

$customers = customers_for_select();
layout_top('Teklif Düzenle', 'quotes');
?>
<div class="page-head">
    <h1 class="page-title">Teklif Düzenle · <?= e((string) $q['quote_no']) ?></h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/quotes/view.php?id=' . $id)) ?>">← Detay</a></div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<form method="post" action="<?= e(url('modules/quotes/edit.php')) ?>" id="quoteForm" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php require __DIR__ . '/_quote-form.php'; ?>
    <div class="form-actions" style="max-width:980px">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/quotes/view.php?id=' . $id)) ?>">Vazgeç</a>
        <?php if (can('quotes.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/quotes/delete.php?id=' . $id)) ?>" style="margin-left:auto"><?= icon('trash-2') ?>Sil</a><?php endif; ?>
    </div>
</form>

<script>
    window.TSOFT_SEARCH_URL = <?= json_encode(url('api/tsoft-search.php'), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(asset('js/quotes.js')) ?>"></script>
<?php layout_bottom();
