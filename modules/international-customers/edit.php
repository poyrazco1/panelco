<?php
declare(strict_types=1);

/** modules/international-customers/edit.php — Yurtdışı müşteri düzenle (kayıt no değişmez). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';

auth_boot();
require_permission('international_customers.edit');

$id = (int) ($_GET['id'] ?? 0);
$existing = ic_get($id);
if (!$existing) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/international-customers/index.php'); }

$errors = [];
$c = $existing;
$selTypes = ic_customer_type_ids($id);
$selCats  = ic_customer_category_ids($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $c = ic_fields_from_input($_POST) + ['id' => $id, 'record_no' => $existing['record_no']];
    $selTypes = array_map('intval', (array) ($_POST['company_type_id'] ?? []));
    $selCats  = array_map('intval', (array) ($_POST['category_id_multi'] ?? []));
    $errors = ic_validate($c);
    if (!$errors) {
        $uid = current_user_id();
        if (ic_update($id, $c, $uid)) {
            ic_set_company_types($id, $selTypes);
            ic_set_categories($id, $selCats);
            log_activity('intl_customer_update', 'international_customer', $id, (string) $existing['record_no'], 'success', 'Yurtdışı müşteri güncellendi');
            flash('success', 'Değişiklikler kaydedildi.');
            redirect('modules/international-customers/view.php?id=' . $id);
        }
        $errors[] = 'Güncelleme başarısız.';
    }
}

$statuses = ic_statuses(); $types = ic_company_types(); $cats = ic_categories(); $users = ic_users_for_select();
layout_top('Müşteri Düzenle', 'international_customers');
?>
<div class="page-head">
    <h1 class="page-title">Müşteri Düzenle <span class="muted small">· <?= e($existing['record_no']) ?></span></h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/international-customers/view.php?id=' . $id)) ?>">← Karta Dön</a></div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/international-customers/edit.php?id=' . $id)) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_form.php'; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/international-customers/view.php?id=' . $id)) ?>">Vazgeç</a>
        <?php if (can('international_customers.delete')): ?><a class="btn btn-danger" href="<?= e(url('modules/international-customers/delete.php?id=' . $id)) ?>"><?= icon('trash-2') ?>Sil</a><?php endif; ?>
    </div>
</form>
<?php layout_bottom();
