<?php
declare(strict_types=1);

/** modules/international-customers/create.php — Yeni yurtdışı müşteri. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';

auth_boot();
require_permission('international_customers.create');

$errors = [];
$c = ['contact_permission' => 1, 'company_role' => (string) ($_GET['role'] ?? 'buyer')];
$selTypes = []; $selCats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $c = ic_fields_from_input($_POST);
    $selTypes = array_map('intval', (array) ($_POST['company_type_id'] ?? []));
    $selCats  = array_map('intval', (array) ($_POST['category_id_multi'] ?? []));
    $errors = ic_validate($c);
    if (!$errors) {
        $uid = current_user_id();
        $id = ic_create($c, $uid);
        if ($id > 0) {
            ic_set_company_types($id, $selTypes);
            ic_set_categories($id, $selCats);
            log_activity('intl_customer_create', 'international_customer', $id, null, 'success', 'Yurtdışı müşteri eklendi: ' . $c['company_name']);
            flash('success', 'Müşteri eklendi.');
            redirect('modules/international-customers/view.php?id=' . $id);
        }
        $errors[] = 'Müşteri kaydedilemedi.';
    }
}

$statuses = ic_statuses(); $types = ic_company_types(); $cats = ic_categories(); $users = ic_users_for_select();
layout_top('Yeni Yurtdışı Müşteri', 'international_customers');
?>
<div class="page-head">
    <h1 class="page-title">Yeni Yurtdışı Müşteri</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/international-customers/index.php')) ?>">← Liste</a></div>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" action="<?= e(url('modules/international-customers/create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_form.php'; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/international-customers/index.php')) ?>">Vazgeç</a>
    </div>
</form>
<?php layout_bottom();
