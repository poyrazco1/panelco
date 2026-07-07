<?php
declare(strict_types=1);

/**
 * modules/service/index.php
 * Servis Kayıtları listesi (filtreli).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';

auth_boot();
require_permission('service');

$f = [
    'ref'       => trim((string) ($_GET['ref'] ?? '')),
    'customer'  => trim((string) ($_GET['customer'] ?? '')),
    'phone'     => trim((string) ($_GET['phone'] ?? '')),
    'brand'     => trim((string) ($_GET['brand'] ?? '')),
    'status'    => (string) ($_GET['status'] ?? ''),
    'approval'  => (string) ($_GET['approval'] ?? ''),
    'payment'   => (string) ($_GET['payment'] ?? ''),
    'repairer'  => (string) ($_GET['repairer'] ?? ''),
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to'   => (string) ($_GET['date_to'] ?? ''),
];

$rows       = get_service_records($f);
$statuses   = service_statuses();
$approvals  = service_approval_labels();
$payments   = service_payment_labels();
$repairers  = get_repairer_options(false);

layout_top('Servis Kayıtları', 'service');
?>

<div class="page-head">
    <h1 class="page-title">Servis Kayıtları</h1>
    <div class="page-actions">
        <a class="btn btn-primary btn-sm" href="<?= e(url('modules/service/intake.php')) ?>"><?= icon('plus') ?>Yeni Servis Kabul</a>
    </div>
</div>

<form method="get" action="<?= e(url('modules/service/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="ref">Referans</label><input type="text" id="ref" name="ref" value="<?= e($f['ref']) ?>"></div>
    <div class="form-group"><label for="customer">Müşteri</label><input type="text" id="customer" name="customer" value="<?= e($f['customer']) ?>"></div>
    <div class="form-group"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= e($f['phone']) ?>"></div>
    <div class="form-group"><label for="brand">Marka</label><input type="text" id="brand" name="brand" value="<?= e($f['brand']) ?>"></div>
    <div class="form-group">
        <label for="status">Durum</label>
        <select id="status" name="status"><option value="">Tümü</option>
            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="approval">Onay</label>
        <select id="approval" name="approval"><option value="">Tümü</option>
            <?php foreach ($approvals as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['approval'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="payment">Ödeme</label>
        <select id="payment" name="payment"><option value="">Tümü</option>
            <?php foreach ($payments as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['payment'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="repairer">Dış tamirci</label>
        <select id="repairer" name="repairer"><option value="">Tümü</option>
            <?php foreach ($repairers as $rid => $rn): ?><option value="<?= (int) $rid ?>"<?= $f['repairer'] === (string) $rid ? ' selected' : '' ?>><?= e($rn) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label for="date_from">Başlangıç</label><input type="date" id="date_from" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="form-group"><label for="date_to">Bitiş</label><input type="date" id="date_to" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Servis kaydı bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Referans</th>
                <th>Müşteri</th>
                <th>Telefon</th>
                <th>Cihaz</th>
                <th>Dış Tamirci</th>
                <th>Durum</th>
                <th>Onay</th>
                <th>Ödeme</th>
                <th>Güncellenme</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $s): ?>
                <?php $vurl = url('modules/service/view.php?id=' . (int) $s['id']); ?>
                <tr>
                    <td class="nowrap"><a href="<?= e($vurl) ?>"><strong><?= e($s['reference_code']) ?></strong></a></td>
                    <td class="wrap"><?= e($s['customer_name']) ?></td>
                    <td class="nowrap"><?= e($s['customer_phone'] ?? '—') ?></td>
                    <td class="wrap"><?= e(trim(($s['brand_name'] ?? '') . ' ' . ($s['device_model'] ?? ''))) ?: '—' ?></td>
                    <td><?= e($s['repairer_name'] ?? '—') ?></td>
                    <td><span class="badge <?= service_status_class((string) $s['status']) ?>"><?= e($statuses[$s['status']] ?? $s['status']) ?></span></td>
                    <td><?= e($approvals[$s['approval_status']] ?? $s['approval_status']) ?></td>
                    <td><?= e($payments[$s['payment_status']] ?? $s['payment_status']) ?></td>
                    <td class="nowrap"><?= fmt_date($s['updated_at'] ?? $s['created_at']) ?></td>
                    <td class="nowrap">
                        <a class="btn btn-sm" href="<?= e($vurl) ?>" title="Görüntüle"><?= icon('eye') ?>Aç</a>
                        <a class="btn btn-sm" href="<?= e(url('modules/service/form.php?id=' . (int) $s['id'])) ?>" title="Form/Yazdır" target="_blank" rel="noopener"><?= icon('printer') ?>Form</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
layout_bottom();
