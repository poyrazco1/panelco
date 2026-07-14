<?php
declare(strict_types=1);

/** modules/shipments/view.php — Sevkiyat detayı + durum/atama/fotoğraf + yönetici WhatsApp. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.view');

$id = (int) ($_GET['id'] ?? 0);
$s  = get_shipment($id);
if (!$s) { flash('error', 'Sevkiyat bulunamadı veya yetkiniz yok.'); redirect('modules/shipments/index.php'); }

$seeAll   = shipment_can_see_all();
$maps     = shipment_maps_link(['location_url' => $s['location_url'] ?? '', 'address' => $s['addr_text'] ?? '', 'district' => $s['addr_district'] ?? '', 'city' => $s['addr_city'] ?? '', 'lat' => '', 'lng' => '']);
$wa       = can('shipments.whatsapp') ? shipment_wa_manager_link($s) : null;
$history  = shipment_status_history($id);
$couriers = $seeAll ? get_personnel_options(false) : [];

layout_top('Sevkiyat ' . $s['shipment_no'], 'shipments');
$row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head"><h1 class="page-title">Sevkiyat · <?= e((string) $s['shipment_no']) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Sevkiyatlar</a>
    <?php if ($maps): ?><a class="btn btn-sm" href="<?= e($maps) ?>" target="_blank" rel="noopener"><?= icon('route') ?>Haritada Aç</a><?php endif; ?>
    <?php if (can('shipments.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/shipments/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>

<div class="card" style="max-width:900px"><div class="card-header"><h2><?= e((string) ($s['customer_name'] ?? ($s['addr_company'] ?? 'Sevkiyat'))) ?></h2>
    <span>
        <span class="badge <?= e(shipment_status_class((string) $s['status'])) ?>"><?= e(shipment_status_label((string) $s['status'])) ?></span>
        <?php $p = (string) $s['priority']; ?><span class="badge <?= $p === 'critical' ? 'badge-danger' : ($p === 'urgent' ? 'badge-leave' : 'badge-muted') ?>"><?= e(shipment_priorities()[$p] ?? $p) ?></span>
    </span></div>
    <div class="card-body"><div class="dl">
        <?= $row('Sevkiyat tipi', shipment_type_label((string) $s['shipment_type'])) ?>
        <?= $row('Adres', trim((string) ($s['addr_text'] ?? '') . ' ' . (string) ($s['addr_district'] ?? '') . ' ' . (string) ($s['addr_city'] ?? ''))) ?>
        <?= $row('İlgili kayıt', trim((shipment_related_types()[$s['related_type']] ?? (string) $s['related_type']) . ' ' . (string) ($s['related_ref'] ?? ''))) ?>
        <?= $row('Sevkiyat tarihi', $s['shipment_date']) ?>
        <?= $row('Planlanan teslim', $s['planned_date']) ?>
        <?= $row('Sevkiyatçı', $s['courier_name']) ?>
        <?= $row('Araç', $s['vehicle_info']) ?>
        <?= $row('Açıklama', $s['description']) ?>
        <?php if ($seeAll): ?><?= $row('Yönetici notu', $s['manager_note']) ?><?php endif; ?>
        <?= $row('Sevkiyatçı notu', $s['courier_note']) ?>
        <?php if (!empty($s['fail_reason'])): ?><?= $row('Başarısızlık nedeni', shipment_fail_reasons()[$s['fail_reason']] ?? $s['fail_reason']) ?><?php endif; ?>
    </div></div>
</div>

<?php if ($wa): ?>
<div class="card" style="max-width:900px"><div class="card-body">
    <a class="btn btn-sm btn-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('message-circle') ?>Yöneticiye WhatsApp ile bildir</a>
    <span class="birthday-note">Otomatik gönderilmez; butona tıklayınca WhatsApp açılır.</span>
</div></div>
<?php endif; ?>

<!-- Durum / atama / tamamlama -->
<div class="card" style="max-width:900px"><div class="card-header"><h2>İşlemler</h2></div><div class="card-body" style="display:flex;flex-direction:column;gap:14px">
    <?php if (can('shipments.status')): ?>
    <form method="post" action="<?= e(url('modules/shipments/status.php')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-group" style="margin:0"><label for="new_status">Durum</label>
            <select id="new_status" name="status"><?php foreach (shipment_statuses() as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= (string) $s['status'] === $sv ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select></div>
        <div class="form-group" id="failWrap" style="margin:0;display:none"><label for="fail_reason">Başarısızlık nedeni</label>
            <select id="fail_reason" name="fail_reason"><?php foreach (shipment_fail_reasons() as $fv => $fl): ?><option value="<?= e($fv) ?>"><?= e($fl) ?></option><?php endforeach; ?></select></div>
        <div class="form-group" style="margin:0;flex:1 1 200px"><label for="note">Not</label><input type="text" id="note" name="note" placeholder="Teslimat/toplama notu"></div>
        <button type="submit" class="btn btn-sm">Durumu Güncelle</button>
    </form>
    <?php endif; ?>

    <?php if (can('shipments.assign')): ?>
    <form method="post" action="<?= e(url('modules/shipments/assign.php')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-group" style="margin:0"><label for="courier_id">Sevkiyatçı ata</label>
            <select id="courier_id" name="courier_id"><option value="0">— Kaldır —</option>
                <?php foreach ($couriers as $cid => $cn): ?><option value="<?= (int) $cid ?>"<?= (int) ($s['courier_id'] ?? 0) === (int) $cid ? ' selected' : '' ?>><?= e($cn) ?></option><?php endforeach; ?></select></div>
        <button type="submit" class="btn btn-sm">Ata</button>
    </form>
    <?php endif; ?>

    <?php if (can('shipments.complete')): ?>
    <form method="post" action="<?= e(url('modules/shipments/complete.php')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-group" style="margin:0;flex:1 1 220px"><label for="cnote">Tamamlama notu</label><input type="text" id="cnote" name="note"></div>
        <button type="submit" class="btn btn-sm btn-primary"><?= icon('check-circle') ?>Tamamlandı</button>
    </form>
    <?php endif; ?>
</div></div>

<!-- Fotoğraflar -->
<div class="card" style="max-width:900px"><div class="card-header"><h2>Teslimat / Toplama Fotoğrafları</h2></div><div class="card-body">
    <?php if (can('shipments.photo_upload')): ?>
    <form method="post" action="<?= e(url('modules/shipments/upload-photo.php')) ?>" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-group" style="margin:0"><label for="photo">Fotoğraf (kamera)</label><input type="file" id="photo" name="photo" accept="image/*" capture="environment" required></div>
        <div class="form-group" style="margin:0"><label for="photo_type">Tür</label><select id="photo_type" name="photo_type"><option value="delivery">Teslimat</option><option value="collection">Toplama</option></select></div>
        <button type="submit" class="btn btn-sm"><?= icon('upload') ?>Yükle</button>
    </form>
    <?php endif; ?>
    <?php if (!empty($s['photos'])): ?>
        <div class="ship-photos">
            <?php foreach ($s['photos'] as $ph): if (!is_file(APP_ROOT . '/' . ltrim((string) $ph['file_path'], '/'))) continue; ?>
                <a href="<?= e(asset(ltrim((string) $ph['file_path'], '/'))) ?>" target="_blank" rel="noopener" class="ship-photo">
                    <img src="<?= e(asset(ltrim((string) $ph['file_path'], '/'))) ?>" alt="">
                    <span><?= e((string) $ph['photo_type'] === 'collection' ? 'Toplama' : 'Teslimat') ?> · <?= e(fmt_date((string) $ph['created_at'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?><div class="empty">Henüz fotoğraf yüklenmemiş.</div><?php endif; ?>
</div></div>

<!-- Durum geçmişi -->
<div class="card" style="max-width:900px"><div class="card-header"><h2>Durum Geçmişi</h2></div><div class="card-body">
    <?php if (!$history): ?><div class="empty">Kayıt yok.</div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Durum</th><th>Not</th><th>Kullanıcı</th><th class="nowrap">Tarih</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr><td><?php if (!empty($h['old_status'])): ?><span class="muted"><?= e(shipment_status_label((string) $h['old_status'])) ?></span> → <?php endif; ?><strong><?= e(shipment_status_label((string) $h['new_status'])) ?></strong></td>
                <td class="wrap"><?= e((string) ($h['note'] ?? '')) ?></td>
                <td><?= e((string) ($h['full_name'] ?? '')) ?></td>
                <td class="nowrap"><?= e(fmt_date((string) $h['created_at'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div></div>

<script>
(function () {
    var sel = document.getElementById('new_status'), fw = document.getElementById('failWrap');
    if (!sel || !fw) { return; }
    function t(){ fw.style.display = sel.value === 'failed' ? '' : 'none'; }
    sel.addEventListener('change', t); t();
})();
</script>
<?php
$docType = 'shipment'; $docId = $id; $docRow = $s; $docBackUrl = 'modules/shipments/view.php?id=' . $id;
require __DIR__ . '/../../includes/_document_send_ui.php';
layout_bottom();
