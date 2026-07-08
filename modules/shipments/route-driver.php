<?php
declare(strict_types=1);

/**
 * modules/shipments/route-driver.php — Sevkiyatçı Mobil Görev Ekranı.
 * Çok sade: bugünkü rota, durak listesi, sıradaki durak belirgin, büyük
 * aksiyon butonları. 3 tıkla iş biter: Haritada Aç → Durum → Fotoğraf/Tamamla.
 * Sürücü yalnızca kendi rotasını görür (get_route görünürlük kontrolü).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.view');

$id = (int) ($_GET['id'] ?? 0);
$route = get_route($id);
if (!$route) { flash('error', 'Rota bulunamadı veya size atanmamış.'); redirect('modules/shipments/index.php'); }

$stops = get_route_stops($id);
$total = count($stops);
$done = 0;
foreach ($stops as $s) { if (in_array((string) $s['status'], ['delivered','collected','delivered_collected','cancelled'], true)) { $done++; } }

// Sıradaki durak: ilk "bitmemiş" durak
$nextStopId = 0;
foreach ($stops as $s) {
    if (!in_array((string) $s['status'], ['delivered','collected','delivered_collected','cancelled'], true)) { $nextStopId = (int) $s['id']; break; }
}
$canStatus = can('shipments.status');
$canPhoto  = can('shipments.photo_upload');

layout_top('Sevkiyatçı: ' . $route['route_name'], 'shipments');
?>
<div class="driver-wrap">
    <div class="driver-head">
        <a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Rotalarım</a>
        <span class="badge <?= e(ship_route_status_class((string) $route['status'])) ?>"><?= e(ship_route_status_label((string) $route['status'])) ?></span>
    </div>
    <h1 class="driver-title"><?= e((string) $route['route_name']) ?></h1>
    <p class="driver-sub"><?= e($route['route_date'] ? fmt_date((string) $route['route_date']) : '') ?> · <strong><?= $done ?>/<?= $total ?></strong> durak tamamlandı</p>
    <?= render_flashes() ?>

    <?php if (!$stops): ?>
        <div class="empty-state empty-compact"><p>Bu rotada durak yok.</p></div>
    <?php else: ?>
    <div class="driver-stops">
        <?php foreach ($stops as $s):
            $sid = (int) $s['id'];
            $isDone = in_array((string) $s['status'], ['delivered','collected','delivered_collected'], true);
            $isCancelled = (string) $s['status'] === 'cancelled';
            $isNext = $sid === $nextStopId;
            $mapLink = ship_stop_maps_link($s);
            $tel = ship_tel_link((string) ($s['phone'] ?? ''));
            $wa  = ship_wa_contact_link((string) ($s['whatsapp'] ?? $s['phone'] ?? ''));
            $doneStatus = ship_operation_done_status((string) $s['operation_type']);
            $proofCount = stop_proof_count($sid);
        ?>
        <div class="driver-stop<?= $isNext ? ' is-next' : '' ?><?= $isDone ? ' is-done' : '' ?>" id="stop-<?= $sid ?>">
            <div class="driver-stop-head">
                <span class="driver-stop-num"><?= (int) $s['stop_order'] ?></span>
                <div class="driver-stop-title">
                    <strong><?= e((string) ($s['company_name'] ?? 'Adres')) ?></strong>
                    <div class="driver-stop-badges">
                        <span class="badge <?= e(ship_stop_status_class((string) $s['status'])) ?>"><?= e(ship_stop_status_label((string) $s['status'])) ?></span>
                        <span class="badge badge-muted"><?= e(ship_stop_operation_label((string) $s['operation_type'])) ?></span>
                        <?php if ($isNext): ?><span class="badge badge-info">Sıradaki</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="driver-stop-addr">
                <?= icon('warehouse', 'icon-xs') ?> <?= e(trim((string) ($s['address'] ?? '') . ' ' . (string) ($s['district'] ?? '') . ' ' . (string) ($s['city'] ?? ''))) ?: '—' ?>
                <?php if (!empty($s['stop_note'])): ?><div class="muted small">Not: <?= e((string) $s['stop_note']) ?></div><?php endif; ?>
            </div>

            <!-- Büyük iletişim/harita butonları -->
            <div class="driver-actions-row">
                <?php if ($mapLink): ?><a class="driver-btn" href="<?= e($mapLink) ?>" target="_blank" rel="noopener"><?= icon('route', 'icon-sm') ?><span>Haritada Aç</span></a><?php endif; ?>
                <?php if ($tel): ?><a class="driver-btn" href="<?= e($tel) ?>"><?= icon('phone', 'icon-sm') ?><span>Ara</span></a><?php endif; ?>
                <?php if ($wa): ?><a class="driver-btn" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'icon-sm') ?><span>WhatsApp</span></a><?php endif; ?>
            </div>

            <?php if (!$isCancelled && $canStatus): ?>
            <!-- Tek tık tamamlama -->
            <?php if (!$isDone): ?>
            <form method="post" action="<?= e(url('modules/shipments/stop-status.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="stop_id" value="<?= $sid ?>"><input type="hidden" name="return" value="driver">
                <input type="hidden" name="status" value="<?= e($doneStatus) ?>">
                <button type="submit" class="driver-btn driver-btn-done"><?= icon('check-circle', 'icon-sm') ?><span><?= e(ship_stop_status_label($doneStatus)) ?> · Tamamla</span></button>
            </form>
            <?php endif; ?>

            <!-- Diğer durumlar + not -->
            <details class="driver-more">
                <summary>Durum Seç / Not Yaz</summary>
                <form method="post" action="<?= e(url('modules/shipments/stop-status.php')) ?>" class="driver-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="stop_id" value="<?= $sid ?>"><input type="hidden" name="return" value="driver">
                    <label>Durum</label>
                    <select name="status">
                        <?php foreach (ship_stop_statuses() as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) $s['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                    <label>Not</label>
                    <textarea name="driver_note" rows="2" placeholder="Teslimat/toplama notu"><?= e((string) ($s['driver_note'] ?? '')) ?></textarea>
                    <button type="submit" class="driver-btn driver-btn-primary"><?= icon('check-circle', 'icon-sm') ?><span>Durumu Kaydet</span></button>
                </form>
            </details>
            <?php endif; ?>

            <?php if ($canPhoto): ?>
            <details class="driver-more">
                <summary><?= icon('upload', 'icon-xs') ?> Fotoğraf Yükle<?= $proofCount > 0 ? ' (' . $proofCount . ')' : '' ?></summary>
                <form method="post" action="<?= e(url('modules/shipments/stop-photo.php')) ?>" enctype="multipart/form-data" class="driver-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="stop_id" value="<?= $sid ?>"><input type="hidden" name="return" value="driver">
                    <input type="file" name="proof" accept="image/*" capture="environment" required>
                    <input type="text" name="note" placeholder="Fotoğraf notu (isteğe bağlı)">
                    <button type="submit" class="driver-btn driver-btn-primary"><?= icon('upload', 'icon-sm') ?><span>Fotoğrafı Yükle</span></button>
                </form>
            </details>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php layout_bottom();
