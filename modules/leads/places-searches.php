<?php
declare(strict_types=1);

/** modules/leads/places-searches.php — Google Places tarama geçmişi. */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead_scan_places.php';

auth_boot();
require_permission('leads.view');

$rows = gp_recent_searches(50);

layout_top('Tarama Geçmişi', 'leads');
?>
<div class="page-head">
    <h1 class="page-title">Google Places Tarama Geçmişi</h1>
    <div class="page-actions">
        <?php if (can('leads.create')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/leads/places-scan.php')) ?>"><?= icon('search') ?>Yeni Tarama</a><?php endif; ?>
        <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>"><?= icon('arrow-left') ?>Lead Listesi</a>
    </div>
</div>
<?= render_flashes() ?>
<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Henüz tarama yapılmadı.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>Tarih</th><th>Arama</th><th>Bölge</th><th class="nowrap">Sonuç</th><th class="nowrap">Yeni</th><th class="nowrap">Kopya</th><th class="nowrap">Çağrı</th><th>Durum</th><th>Kullanıcı</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $cls = match ((string) $r['status']) { 'done' => 'badge-success', 'error' => 'badge-danger', default => 'badge-warning' }; ?>
        <tr>
            <td><?= (int) $r['id'] ?></td>
            <td class="nowrap"><?= e((string) $r['created_at']) ?></td>
            <td><?= e((string) $r['keyword']) ?: '<span class="muted">—</span>' ?> <span class="muted" style="font-size:12px">(<?= e((string) $r['search_type']) ?>)</span></td>
            <td><?= e(trim((((string) $r['district']) ? $r['district'] . ', ' : '') . (string) $r['city'] . ' ' . (string) $r['country'])) ?: '—' ?></td>
            <td class="nowrap"><?= (int) $r['result_count'] ?></td>
            <td class="nowrap"><?= (int) $r['new_count'] ?></td>
            <td class="nowrap"><?= (int) $r['dup_count'] ?></td>
            <td class="nowrap"><?= (int) $r['api_calls'] ?></td>
            <td><span class="badge <?= $cls ?>"><?= e((string) $r['status']) ?></span><?php if ($r['error_message']): ?><div class="muted" style="font-size:11px"><?= e((string) $r['error_message']) ?></div><?php endif; ?></td>
            <td><?= e((string) ($r['created_by_name'] ?? '')) ?: '—' ?></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/leads/places-scan.php?search_id=' . (int) $r['id'])) ?>"><?= icon('eye', 'icon-xs') ?>Aç</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
