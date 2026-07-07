<?php
declare(strict_types=1);

/** modules/reports/index.php — Rapor merkezi: rapor seç + tarih/durum filtresi + tablo. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reports.php';
// Durum etiketleri için ilgili modül helper'ları:
require_once __DIR__ . '/../../includes/quotes.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/service.php';
require_once __DIR__ . '/../../includes/rma.php';
require_once __DIR__ . '/../../includes/inventory.php';

auth_boot();
require_permission('reports.view');

$available = reports_available();
$key = (string) ($_GET['report'] ?? array_key_first($available) ?? '');
$f = [
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to'   => (string) ($_GET['date_to'] ?? ''),
    'status'    => (string) ($_GET['status'] ?? ''),
];

$result = null;
if ($key !== '' && isset($available[$key])) {
    $result = report_run($key, $f);
}

$exportQs = http_build_query(array_merge(['report' => $key], array_filter($f, static fn ($v) => $v !== '')));

layout_top('Raporlar', 'reports');
?>
<div class="page-head"><h1 class="page-title">Raporlar</h1><div class="page-actions">
    <?php if ($result && can('reports.export')): ?><a class="btn btn-sm" href="<?= e(url('modules/reports/export.php?' . $exportQs)) ?>"><?= icon('download') ?>CSV</a><?php endif; ?>
    <?php if ($result && can('reports.print')): ?><a class="btn btn-sm" href="javascript:window.print()"><?= icon('printer') ?>Yazdır</a><?php endif; ?>
</div></div>

<?php if (!$available): ?>
    <div class="card"><div class="card-body"><div class="empty">Görüntüleyebileceğiniz rapor bulunmuyor.</div></div></div>
<?php else: ?>
<div class="tab-row">
    <?php foreach ($available as $k => $def): ?>
        <a class="tab-chip<?= $k === $key ? ' is-active' : '' ?>" href="<?= e(url('modules/reports/index.php?report=' . $k)) ?>"><?= e($def['label']) ?></a>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url('modules/reports/index.php')) ?>" class="toolbar">
    <input type="hidden" name="report" value="<?= e($key) ?>">
    <div class="form-group"><label for="date_from">Başlangıç</label><input type="date" id="date_from" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="form-group"><label for="date_to">Bitiş</label><input type="date" id="date_to" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <?php if ($result && !empty($result['def']['status_col']) && $result['def']['status_labels'] && function_exists($result['def']['status_labels'])): ?>
        <?php $slFn = str_replace('_label', 'es', $result['def']['status_labels']); // quote_status_label -> quote_statuses ?>
        <div class="form-group"><label for="status">Durum</label>
            <select id="status" name="status"><option value="">Tümü</option>
                <?php if (function_exists($slFn)): foreach ($slFn() as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= $f['status'] === $sv ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; endif; ?>
            </select></div>
    <?php endif; ?>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Uygula</button></div>
</form>

<?php if ($result && $result['rows']): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><?php foreach ($result['columns'] as $label => $field): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $row): ?>
            <tr><?php foreach ($result['columns'] as $label => $field): ?><td><?= e(report_format($field, $row[$field] ?? '', $result['def'])) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="muted small"><?= count($result['rows']) ?> kayıt.</p>
<?php else: ?>
    <div class="card"><div class="card-body"><div class="empty">Seçilen kriterlere uygun kayıt bulunamadı.</div></div></div>
<?php endif; ?>
<?php endif; ?>
<?php layout_bottom();
