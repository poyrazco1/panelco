<?php
declare(strict_types=1);

/** modules/shipments/reports.php — Sevkiyat raporları + kartlar + CSV. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.reports');

$f = [
    'date_from' => (string) ($_GET['date_from'] ?? ''), 'date_to' => (string) ($_GET['date_to'] ?? ''),
    'courier_id' => (int) ($_GET['courier_id'] ?? 0), 'status' => (string) ($_GET['status'] ?? ''),
    'city' => trim((string) ($_GET['city'] ?? '')), 'type' => (string) ($_GET['type'] ?? ''),
];
$rows = get_shipments($f);

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sevkiyat-rapor-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Sevkiyat No', 'Tarih', 'Müşteri', 'İl', 'İlçe', 'Tip', 'Sevkiyatçı', 'Öncelik', 'Durum'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [(string) $r['shipment_no'], (string) ($r['shipment_date'] ?? ''), (string) ($r['customer_name'] ?? ''), (string) ($r['addr_city'] ?? ''), (string) ($r['addr_district'] ?? ''), shipment_type_label((string) $r['shipment_type']), (string) ($r['courier_name'] ?? ''), (shipment_priorities()[$r['priority']] ?? $r['priority']), shipment_status_label((string) $r['status'])], ';');
    }
    fclose($out); exit;
}

// Kart sayıları (filtrelenmiş küme üzerinden)
$cards = ['total' => count($rows), 'completed' => 0, 'failed' => 0, 'pending' => 0];
$byCourier = [];
foreach ($rows as $r) {
    $st = (string) $r['status'];
    if (in_array($st, ['delivered', 'collected'], true)) { $cards['completed']++; }
    elseif ($st === 'failed') { $cards['failed']++; }
    elseif ($st !== 'cancelled') { $cards['pending']++; }
    $cn = (string) ($r['courier_name'] ?? '—');
    $byCourier[$cn] = $byCourier[$cn] ?? ['total' => 0, 'done' => 0];
    $byCourier[$cn]['total']++;
    if (in_array($st, ['delivered', 'collected'], true)) { $byCourier[$cn]['done']++; }
}
$exportQs = http_build_query(array_merge($f, ['export' => 'csv']));
$couriers = get_personnel_options(false);

layout_top('Sevkiyat Raporları', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Sevkiyat Raporları</h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Sevkiyatlar</a>
    <a class="btn btn-sm" href="<?= e(url('modules/shipments/reports.php?' . $exportQs)) ?>"><?= icon('download') ?>CSV</a>
    <a class="btn btn-sm" href="javascript:window.print()"><?= icon('printer') ?>Yazdır</a>
</div></div>

<form method="get" action="<?= e(url('modules/shipments/reports.php')) ?>" class="toolbar">
    <div class="form-group"><label for="date_from">Başlangıç</label><input type="date" id="date_from" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="form-group"><label for="date_to">Bitiş</label><input type="date" id="date_to" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <div class="form-group"><label for="courier_id">Sevkiyatçı</label><select id="courier_id" name="courier_id"><option value="0">Tümü</option>
        <?php foreach ($couriers as $cid => $cn): ?><option value="<?= (int) $cid ?>"<?= $f['courier_id'] === (int) $cid ? ' selected' : '' ?>><?= e($cn) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="status">Durum</label><select id="status" name="status"><option value="">Tümü</option>
        <?php foreach (shipment_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label for="city">İl</label><input type="text" id="city" name="city" value="<?= e($f['city']) ?>"></div>
    <div class="form-group"><label for="type">Tip</label><select id="type" name="type"><option value="">Tümü</option>
        <?php foreach (shipment_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Uygula</button></div>
</form>

<div class="rma-stats">
    <div class="rma-stat"><div class="rs-label">Toplam</div><div class="rs-value"><?= (int) $cards['total'] ?></div></div>
    <div class="rma-stat is-closed"><div class="rs-label">Tamamlanan</div><div class="rs-value"><?= (int) $cards['completed'] ?></div></div>
    <div class="rma-stat is-loss"><div class="rs-label">Başarısız</div><div class="rs-value"><?= (int) $cards['failed'] ?></div></div>
    <div class="rma-stat is-open"><div class="rs-label">Bekleyen</div><div class="rs-value"><?= (int) $cards['pending'] ?></div></div>
</div>

<div class="card" style="max-width:640px"><div class="card-header"><h2>Sevkiyatçı Performansı</h2></div><div class="card-body">
    <?php if (!$byCourier): ?><div class="empty">Kayıt yok.</div>
    <?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Sevkiyatçı</th><th>Toplam</th><th>Tamamlanan</th><th>Oran</th></tr></thead><tbody>
        <?php foreach ($byCourier as $cn => $st): $rate = $st['total'] > 0 ? round($st['done'] / $st['total'] * 100) : 0; ?>
            <tr><td><?= e($cn) ?></td><td><?= (int) $st['total'] ?></td><td><?= (int) $st['done'] ?></td><td><?= (int) $rate ?> %</td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div></div>

<div class="table-wrap"><table class="table">
    <thead><tr><th>No</th><th>Tarih</th><th>Müşteri</th><th>İl/İlçe</th><th>Tip</th><th>Sevkiyatçı</th><th>Durum</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['shipment_no']) ?></td><td class="nowrap"><?= e((string) ($r['shipment_date'] ?? '')) ?></td><td><?= e((string) ($r['customer_name'] ?? '')) ?></td>
            <td><?= e(trim((string) ($r['addr_city'] ?? '') . ' / ' . (string) ($r['addr_district'] ?? ''), ' /')) ?></td>
            <td><?= e(shipment_type_label((string) $r['shipment_type'])) ?></td><td><?= e((string) ($r['courier_name'] ?? '')) ?></td>
            <td><span class="badge <?= e(shipment_status_class((string) $r['status'])) ?>"><?= e(shipment_status_label((string) $r['status'])) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php layout_bottom();
