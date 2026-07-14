<?php
declare(strict_types=1);
/** modules/leads/index.php — Lead listesi + durum sekmeleri + filtre. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';
auth_boot();
require_permission('leads.view');
$f = ['status' => (string) ($_GET['status'] ?? ''), 'search' => trim((string) ($_GET['q'] ?? '')), 'scan_id' => (int) ($_GET['scan_id'] ?? 0)];
$rows = get_leads($f);
$counts = lead_counts();
layout_top('Lead Yönetimi', 'leads');
?>
<div class="page-head"><h1 class="page-title">Lead Yönetimi</h1><div class="page-actions">
    <?php if (can('leads.view')): ?><a class="btn btn-sm" href="<?= e(url('modules/leads/reports.php')) ?>"><?= icon('bar-chart-3') ?>Raporlar</a><?php endif; ?>
    <?php if (can('leads.view')): ?><a class="btn btn-sm" href="<?= e(url('modules/leads/places-searches.php')) ?>"><?= icon('history') ?>Taramalar</a><?php endif; ?>
    <?php if (can('leads.edit')): ?><a class="btn btn-sm" href="<?= e(url('modules/leads/settings.php')) ?>"><?= icon('settings') ?>API & Şablon</a><?php endif; ?>
    <?php if (can('leads.export')): ?><a class="btn btn-sm" href="<?= e(url('modules/leads/export.php')) ?>"><?= icon('download') ?>CSV</a><?php endif; ?>
    <?php if (can('leads.create')): ?><a class="btn btn-sm" href="<?= e(url('modules/leads/create.php')) ?>"><?= icon('plus') ?>Yeni Lead</a><?php endif; ?>
    <?php if (can('leads.create')): ?><a class="btn btn-sm" href="<?= e(url('modules/leads/scan.php')) ?>"><?= icon('search') ?>Eklenti ile Tara</a><?php endif; ?>
    <?php if (can('leads.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/leads/places-scan.php')) ?>"><?= icon('map-pin') ?>Google ile Tara</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>
<div class="tab-row">
    <a class="tab-chip<?= $f['status'] === '' ? ' is-active' : '' ?>" href="<?= e(url('modules/leads/index.php')) ?>">Tümü <span class="tab-count"><?= (int) $counts['all'] ?></span></a>
    <?php foreach (lead_statuses() as $k => $lab): if (empty($counts[$k])) continue; ?>
        <a class="tab-chip<?= $f['status'] === $k ? ' is-active' : '' ?>" href="<?= e(url('modules/leads/index.php?status=' . $k)) ?>"><?= e($lab) ?> <span class="tab-count"><?= (int) $counts[$k] ?></span></a>
    <?php endforeach; ?>
</div>
<form method="get" action="<?= e(url('modules/leads/index.php')) ?>" class="toolbar">
    <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($f['status']) ?>"><?php endif; ?>
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Firma, yetkili, telefon, şehir, sektör"></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('search') ?>Ara</button></div>
</form>
<?php if (!$rows): ?><div class="card"><div class="card-body"><div class="empty">Lead bulunamadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Firma</th><th>Yetkili</th><th class="nowrap">Telefon</th><th>Şehir</th><th>Sektör</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="<?= e(url('modules/leads/view.php?id=' . (int) $r['id'])) ?>"><strong><?= e($r['company_name']) ?></strong></a></td>
            <td><?= e((string) ($r['contact_name'] ?? '')) ?: '—' ?></td>
            <td class="nowrap"><?= e((string) ($r['phone'] ?? '')) ?: '—' ?></td>
            <td><?= e((string) ($r['city'] ?? '')) ?: '—' ?></td>
            <td><?= e((string) ($r['sector'] ?? '')) ?: '—' ?></td>
            <td><span class="badge <?= e(lead_status_class((string) $r['status'])) ?>"><?= e(lead_status_label((string) $r['status'])) ?></span></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/leads/view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a>
            <?php if (can('leads.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/leads/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
