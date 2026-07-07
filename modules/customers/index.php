<?php
declare(strict_types=1);

/**
 * modules/customers/index.php
 * Müşteri listesi — kategori sekmeleri + arama + filtre.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/customers.php';

auth_boot();
require_permission('customers.view');

$f = [
    'type'   => (string) ($_GET['type'] ?? ''),
    'active' => (string) ($_GET['active'] ?? ''),
    'search' => trim((string) ($_GET['q'] ?? '')),
];
$rows   = get_customers($f);
$counts = customer_type_counts();
$types  = customer_types();

layout_top('Müşteriler', 'customers');
?>

<div class="page-head">
    <h1 class="page-title">Müşteriler</h1>
    <div class="page-actions">
        <?php if (can('customers.create')): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('modules/customers/create.php')) ?>"><?= icon('plus') ?>Yeni Müşteri</a>
        <?php endif; ?>
    </div>
</div>

<?= render_flashes() ?>

<div class="tab-row">
    <a class="tab-chip<?= $f['type'] === '' ? ' is-active' : '' ?>" href="<?= e(url('modules/customers/index.php')) ?>">Tümü <span class="tab-count"><?= (int) $counts['all'] ?></span></a>
    <?php foreach ($types as $k => $l): ?>
        <a class="tab-chip<?= $f['type'] === $k ? ' is-active' : '' ?>" href="<?= e(url('modules/customers/index.php?type=' . $k)) ?>"><?= e($l) ?> <span class="tab-count"><?= (int) ($counts[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url('modules/customers/index.php')) ?>" class="toolbar">
    <?php if ($f['type'] !== ''): ?><input type="hidden" name="type" value="<?= e($f['type']) ?>"><?php endif; ?>
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Firma, yetkili, telefon, e-posta, şehir"></div>
    <div class="form-group"><label for="active">Durum</label>
        <select id="active" name="active"><option value="">Tümü</option>
            <option value="1"<?= $f['active'] === '1' ? ' selected' : '' ?>>Aktif</option>
            <option value="0"<?= $f['active'] === '0' ? ' selected' : '' ?>>Pasif</option>
        </select>
    </div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Müşteri bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Firma</th>
                <th>Yetkili</th>
                <th class="nowrap">Telefon</th>
                <th>E-posta</th>
                <th>Şehir</th>
                <th>Tip</th>
                <th>Durum</th>
                <th class="nowrap">İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?= e(url('modules/customers/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['company_name']) ?></strong></a><?php if ($r['code']): ?><div class="small muted"><?= e($r['code']) ?></div><?php endif; ?></td>
                    <td><?= e((string) ($r['contact_name'] ?? '')) ?: '—' ?></td>
                    <td class="nowrap"><?= e((string) ($r['phone'] ?? '')) ?: '—' ?></td>
                    <td><?= e((string) ($r['email'] ?? '')) ?: '—' ?></td>
                    <td><?= e((string) ($r['city'] ?? '')) ?: '—' ?></td>
                    <td><span class="badge badge-info"><?= e(customer_type_label((string) $r['customer_type'])) ?></span></td>
                    <td><?= (int) $r['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                    <td class="nowrap">
                        <a class="btn btn-xs" href="<?= e(url('modules/customers/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
                        <?php if (can('customers.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/customers/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
layout_bottom();
