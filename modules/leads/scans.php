<?php
declare(strict_types=1);
/** modules/leads/scans.php — Lead tarama işleri listesi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead-scan.php';
auth_boot();
require_permission('leads.view');
$rows = lead_scans_list(['status' => (string) ($_GET['status'] ?? '')]);
layout_top('Lead Taramaları', 'leads');
?>
<div class="page-head">
    <h1 class="page-title">Lead Taramaları</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>">← Lead Yönetimi</a>
        <?php if (can('leads.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/leads/scan.php')) ?>"><?= icon('search') ?>Yeni Lead Tara</a><?php endif; ?>
    </div>
</div>
<?= render_flashes() ?>
<?php if (!$rows): ?>
<div class="empty-state"><?= icon('search', 'icon-lg') ?><p>Henüz tarama yok.</p>
    <?php if (can('leads.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/leads/scan.php')) ?>"><?= icon('plus') ?>Yeni Lead Tara</a><?php endif; ?>
</div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Tarama</th><th>Durum</th><th>Kombinasyon</th><th>Kaydedilen / Hedef</th><th>Temsilci</th><th class="nowrap">Tarih</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $pct = (int) $r['target_limit'] > 0 ? min(100, (int) round((int) $r['saved_count'] / (int) $r['target_limit'] * 100)) : 0; ?>
        <tr>
            <td><a href="<?= e(url('modules/leads/scan-view.php?id=' . (int) $r['id'])) ?>"><strong><?= e((string) ($r['name'] ?? ('Tarama #' . $r['id']))) ?></strong></a></td>
            <td><span class="badge <?= e(lead_scan_status_class((string) $r['status'])) ?>"><?= e(lead_scan_status_label((string) $r['status'])) ?></span></td>
            <td><?= (int) $r['combos_count'] ?></td>
            <td><strong><?= (int) $r['saved_count'] ?></strong> / <?= (int) $r['target_limit'] ?> <span class="muted small">(%<?= $pct ?>)</span></td>
            <td><?= e((string) ($r['assignee_name'] ?? '—')) ?></td>
            <td class="nowrap"><?= e(fmt_date((string) $r['created_at'])) ?></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/leads/scan-view.php?id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
