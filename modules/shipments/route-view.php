<?php
declare(strict_types=1);

/**
 * modules/shipments/route-view.php — Rota Detay (yönetici).
 * Rota bilgileri + sürücü atama/durum + durak listesi (harita/ara/whatsapp/
 * durum güncelle/fotoğraf/not). Google Maps rota linki API'siz üretilir.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.view');

$id = (int) ($_GET['id'] ?? 0);
$route = get_route($id);
if (!$route) { flash('error', 'Rota bulunamadı veya erişim yetkiniz yok.'); redirect('modules/shipments/index.php'); }

$manage  = ship_can_manage();
$stops   = get_route_stops($id);
$map     = ship_route_maps_link($route, $stops);
$drivers = $manage ? ship_driver_options() : [];
$history = $manage ? route_status_history($id) : [];

$total = count($stops);
$done = 0; $problem = 0;
foreach ($stops as $s) {
    if (in_array((string) $s['status'], ship_stop_done_statuses(), true)) { $done++; }
    elseif (in_array((string) $s['status'], ship_stop_problem_statuses(), true)) { $problem++; }
}

layout_top('Rota: ' . $route['route_name'], 'shipments');
?>
<div class="page-head">
    <h1 class="page-title"><?= e((string) $route['route_name']) ?> <span class="badge <?= e(ship_route_status_class((string) $route['status'])) ?>"><?= e(ship_route_status_label((string) $route['status'])) ?></span></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Sevkiyat Takibi</a>
        <?php if (!empty($map['url'])): ?><a class="btn btn-sm" href="<?= e(url('modules/shipments/route-map.php?id=' . $id)) ?>" target="_blank" rel="noopener"><?= icon('route') ?>Google Maps'te Aç</a><?php endif; ?>
        <a class="btn btn-sm btn-primary" href="<?= e(url('modules/shipments/route-driver.php?id=' . $id)) ?>"><?= icon('truck') ?>Sevkiyatçı Ekranı</a>
        <?php if ($manage && can('shipments.edit')): ?><a class="btn btn-sm" href="<?= e(url('modules/shipments/route-create.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
    </div>
</div>
<?= render_flashes() ?>
<?php if (!empty($map['warn'])): ?><div class="alert alert-info">Durak sayısı Google Maps'in tek seferde desteklediği sınırın (23 ara durak) üzerinde. Harita linki ilk duraklarla üretildi.</div><?php endif; ?>

<div class="route-detail-grid">
    <!-- Rota bilgileri -->
    <div class="card">
        <div class="card-header"><strong>Rota Bilgileri</strong></div>
        <div class="card-body">
            <dl class="detail-list">
                <div><dt>Tarih</dt><dd><?= e($route['route_date'] ? fmt_date((string) $route['route_date']) : '—') ?></dd></div>
                <div><dt>Sevkiyatçı</dt><dd><?= e($route['driver_name'] !== '' ? $route['driver_name'] : 'Atanmadı') ?></dd></div>
                <div><dt>Durum</dt><dd><span class="badge <?= e(ship_route_status_class((string) $route['status'])) ?>"><?= e(ship_route_status_label((string) $route['status'])) ?></span></dd></div>
                <div><dt>İlerleme</dt><dd><strong><?= $done ?>/<?= $total ?></strong> tamamlandı<?= $problem > 0 ? ' · ' . $problem . ' sorunlu' : '' ?></dd></div>
                <div><dt>Başlangıç</dt><dd><?= e((string) ($route['start_point'] ?? '')) ?: '—' ?></dd></div>
                <div><dt>Bitiş</dt><dd><?= e((string) ($route['end_point'] ?? '')) ?: '—' ?></dd></div>
                <?php if (!empty($route['note'])): ?><div><dt>Not</dt><dd><?= nl2br(e((string) $route['note'])) ?></dd></div><?php endif; ?>
            </dl>

            <?php if ($manage && can('shipments.assign')): ?>
            <form method="post" action="<?= e(url('modules/shipments/route-assign.php')) ?>" class="route-assign-form">
                <?= csrf_field() ?>
                <input type="hidden" name="route_id" value="<?= $id ?>">
                <input type="hidden" name="action" value="assign">
                <div class="form-group"><label for="drv">Sürücü Ata</label>
                    <select id="drv" name="driver_user_id">
                        <option value="0">— Atanmadı —</option>
                        <?php foreach ($drivers as $uid => $dn): ?><option value="<?= (int) $uid ?>"<?= (int) $route['driver_user_id'] === (int) $uid ? ' selected' : '' ?>><?= e($dn) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm"><?= icon('check-circle') ?>Kaydet</button>
            </form>
            <?php if ((string) $route['status'] !== 'cancelled' && (string) $route['status'] !== 'completed'): ?>
            <form method="post" action="<?= e(url('modules/shipments/route-assign.php')) ?>" onsubmit="return confirm('Rota iptal edilsin mi?')" style="margin-top:8px">
                <?= csrf_field() ?>
                <input type="hidden" name="route_id" value="<?= $id ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="cancelled">
                <button type="submit" class="btn btn-sm btn-ghost"><?= icon('x-circle') ?>Rotayı İptal Et</button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Duraklar -->
    <div class="card">
        <div class="card-header"><strong>Duraklar (<?= $total ?>)</strong></div>
        <div class="card-body">
            <?php if (!$stops): ?>
                <div class="empty">Bu rotada durak yok.</div>
            <?php else: ?>
            <ol class="stop-list">
                <?php foreach ($stops as $s):
                    $sid = (int) $s['id'];
                    $mapLink = ship_stop_maps_link($s);
                    $tel = ship_tel_link((string) ($s['phone'] ?? ''));
                    $wa  = ship_wa_contact_link((string) ($s['whatsapp'] ?? $s['phone'] ?? ''));
                    $waMgr = ship_wa_manager_link_for_stop($s, $route);
                    $proofs = get_stop_proofs($sid);
                ?>
                <li class="stop-item" id="stop-<?= $sid ?>">
                    <div class="stop-item-head">
                        <span class="stop-num"><?= (int) $s['stop_order'] ?></span>
                        <div class="stop-item-title">
                            <strong><?= e((string) ($s['company_name'] ?? 'Adres')) ?></strong>
                            <span class="badge <?= e(ship_stop_status_class((string) $s['status'])) ?>"><?= e(ship_stop_status_label((string) $s['status'])) ?></span>
                            <span class="badge badge-muted"><?= e(ship_stop_operation_label((string) $s['operation_type'])) ?></span>
                        </div>
                    </div>
                    <div class="stop-item-info">
                        <?php if (!empty($s['contact_name'])): ?><span><?= icon('user', 'icon-xs') ?> <?= e((string) $s['contact_name']) ?></span><?php endif; ?>
                        <?php if (!empty($s['phone'])): ?><span><?= icon('phone', 'icon-xs') ?> <?= e((string) $s['phone']) ?></span><?php endif; ?>
                        <span><?= icon('warehouse', 'icon-xs') ?> <?= e(trim((string) ($s['address'] ?? '') . ' ' . (string) ($s['district'] ?? '') . ' ' . (string) ($s['city'] ?? ''))) ?: '—' ?></span>
                        <?php if (!empty($s['reference_no'])): ?><span>Ref: <?= e((string) $s['reference_no']) ?></span><?php endif; ?>
                        <?php if (!empty($s['completed_at'])): ?><span><?= icon('check-check', 'icon-xs') ?> <?= e((string) $s['completed_at']) ?></span><?php endif; ?>
                    </div>
                    <?php if (!empty($s['stop_note']) || !empty($s['driver_note'])): ?>
                    <div class="stop-item-notes">
                        <?php if (!empty($s['stop_note'])): ?><div><span class="muted small">Durak notu:</span> <?= e((string) $s['stop_note']) ?></div><?php endif; ?>
                        <?php if (!empty($s['driver_note'])): ?><div><span class="muted small">Sürücü notu:</span> <?= e((string) $s['driver_note']) ?></div><?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="stop-item-actions">
                        <?php if ($mapLink): ?><a class="btn btn-xs" href="<?= e($mapLink) ?>" target="_blank" rel="noopener"><?= icon('route', 'icon-xs') ?>Harita</a><?php endif; ?>
                        <?php if ($tel): ?><a class="btn btn-xs" href="<?= e($tel) ?>"><?= icon('phone', 'icon-xs') ?>Ara</a><?php endif; ?>
                        <?php if ($wa): ?><a class="btn btn-xs" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'icon-xs') ?>WhatsApp</a><?php endif; ?>
                        <?php if ($waMgr): ?><a class="btn btn-xs" href="<?= e($waMgr) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'icon-xs') ?>Yön. Bildir</a><?php endif; ?>
                    </div>

                    <?php if ($proofs): ?>
                    <div class="stop-proofs">
                        <?php foreach ($proofs as $p): ?>
                            <a href="<?= e(url((string) $p['file_path'])) ?>" target="_blank" rel="noopener" class="stop-proof-thumb"><img src="<?= e(url((string) $p['file_path'])) ?>" alt="Kanıt" loading="lazy"></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (can('shipments.status')): ?>
                    <details class="stop-update">
                        <summary class="btn btn-xs btn-primary"><?= icon('sliders-horizontal', 'icon-xs') ?>Durum Güncelle</summary>
                        <form method="post" action="<?= e(url('modules/shipments/stop-status.php')) ?>" class="stop-update-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="stop_id" value="<?= $sid ?>">
                            <input type="hidden" name="return" value="manager">
                            <div class="form-group"><label>Durum</label>
                                <select name="status">
                                    <?php foreach (ship_stop_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) $s['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Not</label><input type="text" name="driver_note" value="<?= e((string) ($s['driver_note'] ?? '')) ?>" placeholder="İsteğe bağlı"></div>
                            <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
                        </form>
                    </details>
                    <?php endif; ?>
                    <?php if (can('shipments.photo_upload')): ?>
                    <details class="stop-update">
                        <summary class="btn btn-xs"><?= icon('upload', 'icon-xs') ?>Fotoğraf Yükle</summary>
                        <form method="post" action="<?= e(url('modules/shipments/stop-photo.php')) ?>" enctype="multipart/form-data" class="stop-update-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="stop_id" value="<?= $sid ?>">
                            <input type="hidden" name="return" value="manager">
                            <div class="form-group"><input type="file" name="proof" accept="image/*" capture="environment" required></div>
                            <div class="form-group"><input type="text" name="note" placeholder="Fotoğraf notu (isteğe bağlı)"></div>
                            <button type="submit" class="btn btn-sm">Yükle</button>
                        </form>
                    </details>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($manage && $history): ?>
<div class="card">
    <div class="card-header"><strong>Durum Geçmişi</strong></div>
    <div class="card-body">
        <ul class="ship-history">
            <?php foreach ($history as $h): ?>
            <li>
                <span class="muted small"><?= e((string) $h['created_at']) ?></span>
                <?php if (!empty($h['stop_id'])): ?><span class="badge badge-muted">Durak</span><?php endif; ?>
                <?= e(ship_stop_status_label((string) ($h['new_status'] ?? '')) ?: ship_route_status_label((string) ($h['new_status'] ?? ''))) ?>
                <?php if (!empty($h['note'])): ?><span class="muted">— <?= e((string) $h['note']) ?></span><?php endif; ?>
                <?php if (!empty($h['full_name'])): ?><span class="muted small">(<?= e((string) $h['full_name']) ?>)</span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php layout_bottom();
