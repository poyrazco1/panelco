<?php
declare(strict_types=1);
/** modules/international-customers/categories.php — Şirket tipleri + kategori ağacı yönetimi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
auth_boot();
require_permission('international_customers.edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $uid = current_user_id();
    try {
        switch ($action) {
            case 'type_add':
                $n = trim((string) ($_POST['name'] ?? ''));
                if ($n !== '') { db()->prepare('INSERT INTO company_types (name, sort_order) VALUES (:n,:s)')->execute([':n' => $n, ':s' => (int) ($_POST['sort_order'] ?? 0)]); }
                break;
            case 'type_toggle':
                db()->prepare('UPDATE company_types SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
                break;
            case 'cat_add':
                $n = trim((string) ($_POST['name'] ?? ''));
                $pid = (int) ($_POST['parent_id'] ?? 0) ?: null;
                if ($n !== '') { db()->prepare('INSERT INTO international_customer_categories (parent_id, name, sort_order, created_by) VALUES (:p,:n,:s,:by)')->execute([':p' => $pid, ':n' => $n, ':s' => (int) ($_POST['sort_order'] ?? 0), ':by' => $uid]); }
                break;
            case 'cat_toggle':
                db()->prepare('UPDATE international_customer_categories SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
                break;
        }
        flash('success', 'İşlem tamamlandı.');
    } catch (Throwable $e) { log_error('ic categories: ' . $e->getMessage()); flash('error', 'İşlem başarısız.'); }
    http_response_code(303);
    redirect('modules/international-customers/categories.php');
}

$types = db()->query('SELECT * FROM company_types ORDER BY sort_order, id')->fetchAll();
$cats  = db()->query('SELECT * FROM international_customer_categories ORDER BY COALESCE(parent_id,0), sort_order, id')->fetchAll();
$parents = array_filter($cats, static fn($c) => empty($c['parent_id']));
layout_top('Şirket Tipleri & Kategoriler', 'settings');
?>
<div class="page-head"><h1 class="page-title">Şirket Tipleri & Kategoriler</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div></div>
<?= render_flashes() ?>
<div class="form-grid-2">
<div class="card"><div class="card-header"><strong>Şirket Tipleri</strong></div><div class="card-body">
    <ul class="def-list">
    <?php foreach ($types as $t): ?>
        <li><span<?= (int) $t['is_active'] === 0 ? ' class="muted"' : '' ?>><?= e($t['name']) ?></span>
            <form method="post" action="<?= e(url('modules/international-customers/categories.php')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="type_toggle"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-xs"><?= (int) $t['is_active'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?></button></form></li>
    <?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(url('modules/international-customers/categories.php')) ?>" class="inline-form mt"><?= csrf_field() ?><input type="hidden" name="action" value="type_add">
        <div class="form-row"><div class="form-group"><input type="text" name="name" placeholder="Yeni tip" required></div><div class="form-group"><button class="btn btn-sm btn-primary"><?= icon('plus') ?>Ekle</button></div></div>
    </form>
</div></div>

<div class="card"><div class="card-header"><strong>Kategori Ağacı</strong></div><div class="card-body">
    <ul class="def-list">
    <?php foreach ($cats as $c): ?>
        <li style="<?= !empty($c['parent_id']) ? 'padding-left:22px' : '' ?>"><span<?= (int) $c['is_active'] === 0 ? ' class="muted"' : '' ?>><?= !empty($c['parent_id']) ? '↳ ' : '' ?><?= e($c['name']) ?></span>
            <form method="post" action="<?= e(url('modules/international-customers/categories.php')) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="cat_toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-xs"><?= (int) $c['is_active'] === 1 ? 'Pasifleştir' : 'Aktifleştir' ?></button></form></li>
    <?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(url('modules/international-customers/categories.php')) ?>" class="inline-form mt"><?= csrf_field() ?><input type="hidden" name="action" value="cat_add">
        <div class="form-row">
            <div class="form-group"><label>Üst Kategori</label><select name="parent_id"><option value="">— Ana kategori —</option><?php foreach ($parents as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Ad</label><input type="text" name="name" required></div>
        </div>
        <button class="btn btn-sm btn-primary"><?= icon('plus') ?>Kategori Ekle</button>
    </form>
</div></div>
</div>
<?php layout_bottom();
