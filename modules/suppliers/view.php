<?php
declare(strict_types=1);

/** modules/suppliers/view.php — Tedarikçi detayı. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/suppliers.php';

auth_boot();
require_permission('suppliers.view');

$id = (int) ($_GET['id'] ?? 0);
$s  = get_supplier_by_id($id);
if (!$s) { flash('error', 'Tedarikçi bulunamadı.'); redirect('modules/suppliers/index.php'); }

layout_top('Tedarikçi: ' . $s['company_name'], 'suppliers');
$row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head">
    <h1 class="page-title"><?= e($s['company_name']) ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/suppliers/index.php')) ?>">← Tedarikçiler</a>
        <?php if (can('suppliers.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/suppliers/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
    </div>
</div>

<?= render_flashes() ?>

<div class="card" style="max-width:900px">
    <div class="card-header"><h2>Tedarikçi Bilgileri</h2>
        <span><?= (int) $s['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></span>
    </div>
    <div class="card-body">
        <div class="dl">
            <?= $row('Kod', $s['code']) ?>
            <?= $row('Yetkili kişi', $s['contact_name']) ?>
            <?= $row('Telefon', $s['phone']) ?>
            <?= $row('WhatsApp', $s['whatsapp']) ?>
            <?= $row('E-posta', $s['email']) ?>
            <?= $row('Web sitesi', $s['website']) ?>
            <?= $row('Ülke', $s['country']) ?>
            <?= $row('Şehir', $s['city']) ?>
            <?= $row('Adres', $s['address']) ?>
            <?= $row('Ürün grupları', $s['product_groups']) ?>
            <?= $row('Ödeme şartları', $s['payment_terms']) ?>
            <?= $row('Teslimat süresi', $s['delivery_time']) ?>
            <?= $row('Para birimi', $s['currency']) ?>
            <?= $row('Notlar', $s['notes']) ?>
        </div>
    </div>
</div>

<div class="card" style="max-width:900px"><div class="card-header"><h2>Alım / Teklif Geçmişi</h2></div>
    <div class="card-body"><div class="empty">Bu tedarikçiye ait alım/teklif geçmişi altyapısı hazır; kayıtlar ilerleyen fazlarda ilişkilendirilecek.</div></div>
</div>

<?php layout_bottom();
