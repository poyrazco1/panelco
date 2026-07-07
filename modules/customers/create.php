<?php
declare(strict_types=1);

/**
 * modules/customers/create.php
 * Yeni müşteri oluştur. POST + CSRF + yetki.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/customers.php';

auth_boot();
require_permission('customers.create');

$errors = [];
$c = ['is_active' => 1, 'customer_type' => (string) ($_GET['type'] ?? 'tr')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $c = customer_fields_from_input($_POST);
    $errors = customer_validate($c);
    if (!$errors) {
        $id = create_customer($c, current_user_id());
        if ($id > 0) {
            log_activity('customer_create', 'customer', $id, null, 'success', 'Müşteri eklendi: ' . $c['company_name']);
            flash('success', 'Müşteri eklendi.');
            redirect('modules/customers/view.php?id=' . $id);
        }
        $errors[] = 'Müşteri kaydedilemedi.';
    }
}

layout_top('Yeni Müşteri', 'customers');
?>
<div class="page-head">
    <h1 class="page-title">Yeni Müşteri</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/customers/index.php')) ?>">← Müşteriler</a></div>
</div>

<?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

<form method="post" action="<?= e(url('modules/customers/create.php')) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php require __DIR__ . '/_customer-form.php'; ?>
    <div class="form-actions" style="max-width:900px">
        <button type="submit" class="btn btn-primary">Kaydet</button>
        <a class="btn" href="<?= e(url('modules/customers/index.php')) ?>">Vazgeç</a>
    </div>
</form>

<?php
layout_bottom();
