<?php
declare(strict_types=1);

/**
 * modules/shipments/reports.php — Rota/durak raporu + CSV.
 * Durak bazlı liste: tarih, rota, sevkiyatçı, firma, işlem tipi, durum,
 * tamamlanma saati, fotoğraf, not. Filtreler + CSV dışa aktarma.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.reports');

$f = [
    'date_from'      => (string) ($_GET['date_from'] ?? ''),
    'date_to'        => (string) ($_GET['date_to'] ?? ''),
    'driver_user_id' => (int) ($_GET['driver_user_id'] ?? 0),
    'status'         => (string) ($_GET['status'] ?? ''),
    'city'           => trim((string) ($_GET['city'] ?? '')),
    'district'       => trim((string) ($_GET['district'] ?? '')),
    'operation_type' => (string) ($_GET['operation_type'] ?? ''),
    'has_photo'      => (string) ($_GET['has_photo'] ?? ''),
];
$rows = ship_stop_report($f);

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sevkiyat-rota-rapor-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Tarih', 'Rota', 'Sevkiyatçı', 'Firma', 'İl', 'İlçe', 'İşlem Tipi', 'Durum', 'Tamamlanma', 'Fotoğraf', 'Not'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            (string) ($r['route_date'] ?? ''),
            (string) ($r['route_name'] ?? ''),
            (string) ($r['driver_name'] ?? ''),
            (string) ($r['company_name'] ?? ''),
            (string) ($r['city'] ?? ''),
            (string) ($r['district'] ?? ''),
            ship_stop_operation_label((string) $r['operation_type']),
            ship_stop_status_label((string) $r['status']),
            (string) ($r['completed_at'] ?? ''),
            ((int) $r['photo_count'] > 0 ? 'Var' : 'Yok'),
            (string) ($r['driver_note'] ?? $r['stop_note'] ?? ''),
        ], ';');
    }
    fclose($out); exit;
}

$exportQs = http_build_query(array_merge(array_filter($f), ['export' => 'csv']));
$drivers = ship_driver_options();

// Özet
$sumDone = 0; $sumProblem = 0;
foreach ($rows as $r) {
    if (in_array((string) $r['status'], ship_stop_done_statuses(), true)) { $sumDone++; }
    elseif (in_array((string) $r['status'], ship_stop_problem_statuses(), true)) { $sumProblem++; }
}

layout_top('Sevkiyat Raporları', 'shipments');
?>
<div class="page-head">
    <h1 class="page-title">Sevkiyat Raporları</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Sevkiyat Takibi</a>
        <a class="btn btn-sm" href="<?= e(url('modules/shipments/reports.php?' . $exportQs)) ?>"><?= icon('download') ?>CSV</a>
    </div>
</div>
<?= render_flashes() ?>

<form method="get" action="<?= e(url('modules/shipments/reports.php')) ?>" class="toolbar">
    <div class="form-group"><label for="date_from">Başlangıç</label><input type="date" id="date_from" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="form-group"><label for="date_to">Bitiş</label><input type="date" id="date_to" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <div class="form-group"><label for="driver_user_id">Sevkiyatçı</label><select id="driver_user_id" name="driver_user_id"><option value="0">Tümü</option>
        <?php foreach ($drivers as $uid => $dn): ?><option value="<?= (int) $uid ?>"<?= $f['driver_user_id'] === (int) $uid ? ' selected' : '' ?>><?= e($dn) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="status">Durum</label><select id="status" name="status"><option value="">Tümü</option>
        <?php foreach (ship_stop_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="operation_type">İşlem</label><select id="operation_type" name="operation_type"><option value="">Tümü</option>
        <?php foreach (ship_stop_operations() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['operation_type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="city">İl</label><input type="text" id="city" name="city" value="<?= e($f['city']) ?>"></div>
    <div class="form-group"><label for="district">İlçe</label><input type="text" id="district" name="district" value="<?= e($f['district']) ?>"></div>
    <div class="form-group"><label for="has_photo">Fotoğraf</label><select id="has_photo" name="has_photo">
        <option value="">Tümü</option><option value="1"<?= $f['has_photo'] === '1' ? ' selected' : '' ?>>Var</option><option value="0"<?= $f['has_photo'] === '0' ? ' selected' : '' ?>>Yok</option></select></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Uygula</button></div>
</form>

<div class="ship-summary ship-summary-sm">
    <div class="ship-sum-card"><span class="ship-sum-body"><span class="ship-sum-val"><?= count($rows) ?></span><span class="ship-sum-lbl">Toplam Durak</span></span></div>
    <div class="ship-sum-card"><span class="ship-sum-body"><span class="ship-sum-val"><?= $sumDone ?></span><span class="ship-sum-lbl">Tamamlanan</span></span></div>
    <div class="ship-sum-card ship-sum-warn"><span class="ship-sum-body"><span class="ship-sum-val"><?= $sumProblem ?></span><span class="ship-sum-lbl">Sorunlu</span></span></div>
</div>

<?php if (!$rows): ?>
<div class="empty-state empty-compact"><p>Bu filtrelere uygun kayıt bulunmuyor.</p></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Tarih</th><th>Rota</th><th>Sevkiyatçı</th><th>Firma</th><th>İl/İlçe</th><th>İşlem</th><th>Durum</th><th>Tamamlanma</th><th>Foto</th><th>Not</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="nowrap"><?= e($r['route_date'] ? fmt_date((string) $r['route_date']) : '—') ?></td>
            <td><a href="<?= e(url('modules/shipments/route-view.php?id=' . (int) $r['route_id'])) ?>"><?= e((string) $r['route_name']) ?></a></td>
            <td><?= e($r['driver_name'] !== '' ? $r['driver_name'] : '—') ?></td>
            <td><?= e((string) ($r['company_name'] ?? '—')) ?></td>
            <td><?= e(trim((string) ($r['city'] ?? '') . ' / ' . (string) ($r['district'] ?? ''), ' /')) ?: '—' ?></td>
            <td><?= e(ship_stop_operation_label((string) $r['operation_type'])) ?></td>
            <td><span class="badge <?= e(ship_stop_status_class((string) $r['status'])) ?>"><?= e(ship_stop_status_label((string) $r['status'])) ?></span></td>
            <td class="nowrap"><?= e((string) ($r['completed_at'] ?? '')) ?: '—' ?></td>
            <td><?= (int) $r['photo_count'] > 0 ? '<span class="badge badge-success">Var</span>' : '<span class="badge badge-muted">Yok</span>' ?></td>
            <td><?= e((string) ($r['driver_note'] ?? $r['stop_note'] ?? '')) ?: '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
