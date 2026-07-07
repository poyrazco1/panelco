<?php
declare(strict_types=1);

/**
 * modules/rma/index.php
 * İade-Değişim Yönetimi listesi + özet kartları + filtreler.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/rma.php';

auth_boot();
require_permission('rma');

$f = [
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to'   => (string) ($_GET['date_to'] ?? ''),
    'customer'  => trim((string) ($_GET['customer'] ?? '')),
    'phone'     => trim((string) ($_GET['phone'] ?? '')),
    'platform'  => (string) ($_GET['platform'] ?? ''),
    'type'      => (string) ($_GET['type'] ?? ''),
    'supplier'  => trim((string) ($_GET['supplier'] ?? '')),
    'status'    => (string) ($_GET['status'] ?? ''),
    'tracking'  => trim((string) ($_GET['tracking'] ?? '')),
    'model'     => trim((string) ($_GET['model'] ?? '')),
    'search'    => trim((string) ($_GET['q'] ?? '')),
];

$rows      = get_rma_records($f);
$summary   = rma_summary();
$statuses  = rma_statuses();
$types     = rma_process_types();
$platforms = rma_platforms();

// mevcut filtreleri export linkine taşı
$exportQs = http_build_query(array_filter($f, static fn ($v) => $v !== ''));

layout_top('İade-Değişim Yönetimi', 'rma');
?>

<div class="page-head">
    <h1 class="page-title">İade-Değişim Yönetimi</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/rma/import.php')) ?>"><?= icon('upload') ?>CSV İçe Aktar</a>
        <a class="btn btn-sm" href="<?= e(url('modules/rma/export.php' . ($exportQs !== '' ? '?' . $exportQs : ''))) ?>"><?= icon('download') ?>CSV Dışa Aktar</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('modules/rma/create.php')) ?>"><?= icon('plus') ?>Yeni Kayıt</a>
    </div>
</div>

<div class="rma-stats">
    <div class="rma-stat"><div class="rs-label">Toplam Kayıt</div><div class="rs-value"><?= (int) $summary['total'] ?></div></div>
    <div class="rma-stat is-open"><div class="rs-label">Açık Süreç</div><div class="rs-value"><?= (int) $summary['open'] ?></div></div>
    <div class="rma-stat is-closed"><div class="rs-label">Kapanan Süreç</div><div class="rs-value"><?= (int) $summary['closed'] ?></div></div>
    <div class="rma-stat is-loss"><div class="rs-label">Toplam Zarar</div><div class="rs-value"><?= fmt_money((float) $summary['loss']) ?> TL</div></div>
    <div class="rma-stat"><div class="rs-label">İade</div><div class="rs-value"><?= (int) $summary['iade'] ?></div></div>
    <div class="rma-stat"><div class="rs-label">Değişim</div><div class="rs-value"><?= (int) $summary['degisim'] ?></div></div>
    <div class="rma-stat"><div class="rs-label">İptal</div><div class="rs-value"><?= (int) $summary['iptal'] ?></div></div>
</div>

<form method="get" action="<?= e(url('modules/rma/index.php')) ?>" class="toolbar">
    <div class="form-group"><label for="q">Ara</label><input type="text" id="q" name="q" value="<?= e($f['search']) ?>" placeholder="Müşteri, telefon, ürün, tedarikçi, takip no"></div>
    <div class="form-group"><label for="date_from">Başlangıç</label><input type="date" id="date_from" name="date_from" value="<?= e($f['date_from']) ?>"></div>
    <div class="form-group"><label for="date_to">Bitiş</label><input type="date" id="date_to" name="date_to" value="<?= e($f['date_to']) ?>"></div>
    <div class="form-group"><label for="customer">Firma / Müşteri</label><input type="text" id="customer" name="customer" value="<?= e($f['customer']) ?>"></div>
    <div class="form-group"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= e($f['phone']) ?>"></div>
    <div class="form-group">
        <label for="platform">Platform</label>
        <select id="platform" name="platform"><option value="">Tümü</option>
            <?php foreach ($platforms as $pl): ?><option value="<?= e($pl) ?>"<?= $f['platform'] === $pl ? ' selected' : '' ?>><?= e($pl) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="type">İşlem tipi</label>
        <select id="type" name="type"><option value="">Tümü</option>
            <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['type'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="status">Durum</label>
        <select id="status" name="status"><option value="">Tümü</option>
            <?php foreach ($statuses as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="form-group"><label for="supplier">Tedarikçi</label><input type="text" id="supplier" name="supplier" value="<?= e($f['supplier']) ?>"></div>
    <div class="form-group"><label for="tracking">Kargo takip no</label><input type="text" id="tracking" name="tracking" value="<?= e($f['tracking']) ?>"></div>
    <div class="form-group"><label for="model">Ürün modeli</label><input type="text" id="model" name="model" value="<?= e($f['model']) ?>"></div>
    <div class="form-group"><button type="submit" class="btn btn-sm"><?= icon('filter') ?>Filtrele</button></div>
</form>

<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><div class="empty">Kayıt bulunamadı.</div></div></div>
<?php else: ?>
<?php
$reasons_lookup = null; // sebep serbest metin; doğrudan gösterilir
// çoklu değerleri badge listesine çeviren yardımcı
$multi = static function (?string $joined): string {
    $joined = (string) $joined;
    if ($joined === '') { return '—'; }
    $parts = array_filter(array_map('trim', explode('|', $joined)), static fn ($x) => $x !== '');
    if (!$parts) { return '—'; }
    $out = '<div class="multi-list">';
    foreach ($parts as $p) { $out .= '<span>' . e($p) . '</span>'; }
    return $out . '</div>';
};
?>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th class="nowrap">İşlem Tarihi</th>
                <th>Firma / Ad-Soyad</th>
                <th class="nowrap">Telefon</th>
                <th class="nowrap">Fatura Tarihi</th>
                <th>Platform</th>
                <th>Ürün Modeli / Ürünler</th>
                <th>Adet</th>
                <th>İade / Değişim / İptal</th>
                <th>Sebep</th>
                <th>Açıklama</th>
                <th>Tedarikçi</th>
                <th class="nowrap">Zarar</th>
                <th>Müşteriden Alınan Ürünler</th>
                <th>Müşt. Alınan Kargo Takipleri</th>
                <th>Müşteriye Gönderilen Ürünler</th>
                <th>Müşt. Gönderilen Kargo Takipleri</th>
                <th>Durum</th>
                <th class="nowrap">Takip</th>
                <th class="nowrap">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $vurl = url('modules/rma/view.php?id=' . (int) $r['id']);
                $rc = (int) ($r['received_count'] ?? 0);
                $sc = (int) ($r['sent_count'] ?? 0);
                $recvSummary = $rc > 1 ? ($rc . ' ürün') : ((string) ($r['received_names'] ?? '') !== '' ? (string) $r['received_names'] : ((string) ($r['product_model'] ?? '') ?: '—'));
                $sentSummary = $sc > 1 ? ($sc . ' ürün') : ((string) ($r['sent_names'] ?? '') ?: '—');
                $pub = rma_public_url($r);
                ?>
                <tr>
                    <td class="nowrap"><?= e($r['process_date'] ?? '—') ?></td>
                    <td class="wrap"><a href="<?= e($vurl) ?>"><?= e($r['customer_name']) ?></a><?php if (!empty($r['reference_code'])): ?><div class="small muted"><?= e($r['reference_code']) ?></div><?php endif; ?></td>
                    <td class="nowrap"><?= e($r['customer_phone'] ?? '—') ?></td>
                    <td class="nowrap"><?= e($r['invoice_date'] ?? '—') ?></td>
                    <td><?= e($r['platform'] ?? '—') ?></td>
                    <td class="cell-trunc" title="<?= e((string) ($r['received_names'] ?? $r['product_model'] ?? '')) ?>"><?= e($recvSummary) ?></td>
                    <td><?= (int) ($r['received_qty'] ?? 0) ?: (int) $r['quantity'] ?></td>
                    <td><?= e($types[$r['process_type']] ?? $r['process_type']) ?></td>
                    <td class="cell-trunc" title="<?= e($r['reason_type'] ?? '') ?>"><?= e($r['reason_type'] ?? '—') ?></td>
                    <td class="cell-trunc" title="<?= e($r['description'] ?? '') ?>"><?= e($r['description'] ?? '—') ?></td>
                    <td class="cell-trunc" title="<?= e($r['supplier'] ?? '') ?>"><?= e($r['supplier'] ?? '—') ?></td>
                    <td class="nowrap"><?= fmt_money((float) ($r['loss_amount'] ?? 0)) ?> TL</td>
                    <td class="cell-trunc"><?= $multi($r['received_names'] ?? '') ?></td>
                    <td class="nowrap"><?= $multi($r['received_tracks'] ?? '') ?></td>
                    <td class="cell-trunc"><?= $multi($r['sent_names'] ?? '') ?></td>
                    <td class="nowrap"><?= $multi($r['sent_tracks'] ?? '') ?></td>
                    <td><span class="badge <?= rma_status_class((string) $r['status']) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span></td>
                    <td class="nowrap">
                        <?php if ((int) ($r['public_tracking_enabled'] ?? 0) === 1 && $pub): ?>
                            <a class="btn btn-sm" href="<?= e($pub) ?>" target="_blank" rel="noopener"><?= icon('link') ?>Takip</a>
                        <?php else: ?><span class="muted small">kapalı</span><?php endif; ?>
                    </td>
                    <td class="nowrap">
                        <a class="btn btn-sm" href="<?= e($vurl) ?>"><?= icon('eye') ?>Aç</a>
                        <a class="btn btn-sm" href="<?= e(url('modules/rma/edit.php?id=' . (int) $r['id'])) ?>"><?= icon('pencil') ?>Düzenle</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="muted small" style="margin-top:8px">Toplam <?= count($rows) ?> kayıt. Çok ürünlü kayıtlar “N ürün” olarak özetlenir; ayrıntı için kayda tıklayın.</p>
<?php endif; ?>

<?php
layout_bottom();
