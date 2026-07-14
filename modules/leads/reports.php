<?php
declare(strict_types=1);

/** modules/leads/reports.php — Lead & tarama raporları (§14). */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead_reports.php';

auth_boot();
require_permission('leads.view');

$sum      = lead_report_summary();
$byStatus = lead_report_by_status();
$bySource = lead_report_by_source();
$byPerson = lead_report_by_personnel();
$byCity   = lead_report_by_city();
$scan     = lead_report_scan_stats();
$daily    = lead_report_daily_new();
$maxDaily = max(1, ...array_map(static fn($d) => $d['count'], $daily ?: [['count' => 1]]));

layout_top('Lead Raporları', 'leads');
$bar = static function (array $rows, string $keyLabel, string $keyCount) {
    $max = max(1, ...array_map(static fn($r) => (int) $r[$keyCount], $rows ?: [[$keyCount => 1]]));
    ob_start();
    foreach ($rows as $r) {
        $pct = (int) round((int) $r[$keyCount] / $max * 100);
        echo '<div style="display:flex;align-items:center;gap:10px;margin:4px 0">'
           . '<div style="width:180px;flex:0 0 180px" class="nowrap">' . e((string) $r[$keyLabel]) . '</div>'
           . '<div style="flex:1;background:var(--border,#eee);border-radius:4px;overflow:hidden;height:16px"><div style="width:' . $pct . '%;background:#1F2A44;height:100%"></div></div>'
           . '<div style="width:48px;text-align:right"><strong>' . (int) $r[$keyCount] . '</strong></div></div>';
    }
    return ob_get_clean();
};
?>
<div class="page-head">
    <h1 class="page-title">Lead Raporları</h1>
    <div class="page-actions">
        <?php if (can('leads.export')): ?><a class="btn btn-sm" href="<?= e(url('modules/leads/export.php')) ?>"><?= icon('download') ?>CSV</a><?php endif; ?>
        <a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>"><?= icon('arrow-left') ?>Lead Listesi</a>
    </div>
</div>
<?= render_flashes() ?>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:18px">
    <div class="card"><div class="card-body"><div class="muted">Toplam Lead</div><div style="font-size:26px;font-weight:700"><?= (int) $sum['total'] ?></div></div></div>
    <div class="card"><div class="card-body"><div class="muted">Dönüşüm</div><div style="font-size:26px;font-weight:700;color:#15803d"><?= e((string) $sum['conversion']) ?>%</div><div class="muted small"><?= (int) $sum['success'] ?> başarılı</div></div></div>
    <div class="card"><div class="card-body"><div class="muted">Açık (işlemde)</div><div style="font-size:26px;font-weight:700"><?= (int) $sum['open'] ?></div></div></div>
    <div class="card"><div class="card-body"><div class="muted">Başarısız</div><div style="font-size:26px;font-weight:700;color:#b91c1c"><?= (int) $sum['failure'] ?></div></div></div>
    <div class="card"><div class="card-body"><div class="muted">Çöp kutusu</div><div style="font-size:26px;font-weight:700"><?= (int) $sum['trashed'] ?></div></div></div>
</div>

<div class="card"><div class="card-header"><h2>Google Places Tarama & Maliyet</h2></div><div class="card-body">
    <div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px">
        <div><div class="muted">Toplam tarama</div><strong style="font-size:20px"><?= (int) $scan['scans'] ?></strong></div>
        <div><div class="muted">Bulunan sonuç</div><strong style="font-size:20px"><?= (int) $scan['results'] ?></strong></div>
        <div><div class="muted">Yeni / Kopya</div><strong style="font-size:20px"><?= (int) $scan['new'] ?> / <?= (int) $scan['dup'] ?></strong></div>
        <div><div class="muted">API çağrısı</div><strong style="font-size:20px"><?= (int) $scan['api_calls'] ?></strong></div>
        <div><div class="muted">Tahmini maliyet</div><strong style="font-size:20px">$<?= e(number_format((float) $scan['est_cost'], 2)) ?></strong></div>
        <div><div class="muted">Bu ay (çağrı / $)</div><strong style="font-size:20px"><?= (int) $scan['usage_month'] ?> / $<?= e(number_format((float) ($scan['cost_month'] ?? 0), 2)) ?></strong></div>
    </div>
    <div class="field-hint" style="margin-top:8px">Maliyet, çağrı türü başına yaklaşık Google fiyatına göre tahminidir; kesin fatura Google Cloud konsolundadır.</div>
</div></div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:18px;margin-top:18px">
    <div class="card"><div class="card-header"><h2>Duruma Göre</h2></div><div class="card-body">
        <?php if (!$byStatus): ?><div class="empty">Veri yok.</div><?php else: foreach ($byStatus as $s): ?>
            <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border,#eee)">
                <span><span class="badge <?= e($s['class']) ?>"><?= e($s['label']) ?></span></span><strong><?= (int) $s['count'] ?></strong></div>
        <?php endforeach; endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h2>Sorumlu Personel</h2></div><div class="card-body">
        <?= $byPerson ? $bar($byPerson, 'name', 'count') : '<div class="empty">Veri yok.</div>' ?>
    </div></div>

    <div class="card"><div class="card-header"><h2>Kaynak</h2></div><div class="card-body">
        <?= $bySource ? $bar($bySource, 'source', 'count') : '<div class="empty">Veri yok.</div>' ?>
    </div></div>

    <div class="card"><div class="card-header"><h2>Şehir</h2></div><div class="card-body">
        <?= $byCity ? $bar($byCity, 'city', 'count') : '<div class="empty">Veri yok.</div>' ?>
    </div></div>
</div>

<div class="card" style="margin-top:18px"><div class="card-header"><h2>Son 14 Gün — Yeni Lead</h2></div><div class="card-body">
    <?php if (!$daily): ?><div class="empty">Veri yok.</div><?php else: ?>
    <div style="display:flex;align-items:flex-end;gap:6px;height:140px">
        <?php foreach ($daily as $d): $h = (int) round((int) $d['count'] / $maxDaily * 120); ?>
            <div style="flex:1;text-align:center" title="<?= e($d['date']) ?>: <?= (int) $d['count'] ?>">
                <div style="background:#1F2A44;height:<?= max(2, $h) ?>px;border-radius:3px 3px 0 0"></div>
                <div class="muted" style="font-size:10px;margin-top:3px"><?= e(substr((string) $d['date'], 5)) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div></div>
<?php layout_bottom();
