<?php
declare(strict_types=1);
/** modules/reconciliation/index.php — Mutabakat listesi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation.php';
auth_boot();
require_permission('reconciliation.view');
$f = ['agreement' => (string) ($_GET['agreement'] ?? ''), 'search' => trim((string) ($_GET['q'] ?? ''))];
$rows = get_reconciliations($f);
layout_top('Mutabakat', 'reconciliation');
?>
<div class="page-head"><h1 class="page-title">Mutabakat</h1><div class="page-actions">
<?php if (can('reconciliation.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/reconciliation/create.php')) ?>"><?= icon('plus') ?>Yeni Mutabakat</a><?php endif; ?></div></div>
<?= render_flashes() ?>
<form method="get" action="<?= e(url('modules/reconciliation/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="No, müşteri, cari kod"></div>
    <div class="form-group"><label for="agreement">Sonuç</label><select id="agreement" name="agreement"><option value="">Tümü</option>
        <?php foreach (recon_agreements() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['agreement'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>
<?php if (!$rows): ?><div class="card"><div class="card-body"><div class="empty">Mutabakat kaydı bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>No</th><th>Müşteri</th><th>Dönem</th><th class="nowrap">Bakiye</th><th>Sonuç</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td><a href="<?= e(url('modules/reconciliation/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['recon_no']) ?></strong></a></td>
        <td><?= e($r['customer_name']) ?></td><td><?= e((string) ($r['period'] ?? '—')) ?></td>
        <td class="nowrap"><?= e(fmt_money((float) $r['balance']) . ' ' . quote_currency_symbol((string) $r['currency'])) ?></td>
        <td><span class="badge <?= e(recon_agreement_class((string) $r['agreement'])) ?>"><?= e(recon_agreement_label((string) $r['agreement'])) ?></span></td>
        <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/reconciliation/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
        <?php if (can('reconciliation.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/reconciliation/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
