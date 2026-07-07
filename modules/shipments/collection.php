<?php
declare(strict_types=1);

/**
 * modules/shipments/collection.php — Toplama işlemleri.
 * Liste + yeni toplama + detay (ürün işaretleme / eksik / durum). CSRF korumalı.
 * Sevkiyatçı yalnızca kendi sevkiyatına bağlı toplamaları görür.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.collection_view');

$id = (int) ($_GET['id'] ?? 0);

/* ---- POST işlemleri ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create' && can('shipments.collection_create')) {
        $items = [];
        foreach ((array) ($_POST['item_name'] ?? []) as $i => $nm) {
            $items[] = ['name' => (string) $nm, 'product_code' => $_POST['item_code'][$i] ?? '', 'barcode' => $_POST['item_barcode'][$i] ?? '', 'qty' => $_POST['item_qty'][$i] ?? 1, 'note' => $_POST['item_note'][$i] ?? ''];
        }
        $cid = create_collection((int) ($_POST['shipment_id'] ?? 0), $_POST, $items, current_user_id());
        if ($cid > 0) { log_activity('collection_create', 'collection', $cid, null, 'success', 'Toplama oluşturuldu'); flash('success', 'Toplama kaydı oluşturuldu.'); redirect('modules/shipments/collection.php?id=' . $cid); }
        flash('error', 'Toplama kaydedilemedi.');
        redirect('modules/shipments/collection.php');
    }
    if ($action === 'update_items' && can('shipments.collection_edit')) {
        $cid = (int) ($_POST['id'] ?? 0);
        $c = get_collection($cid);
        if ($c) {
            $items = [];
            foreach ((array) ($_POST['ci_name'] ?? []) as $i => $nm) {
                $items[] = ['name' => (string) $nm, 'product_code' => $_POST['ci_code'][$i] ?? '', 'barcode' => $_POST['ci_barcode'][$i] ?? '',
                            'qty' => $_POST['ci_qty'][$i] ?? 1, 'taken_qty' => $_POST['ci_taken'][$i] ?? 0,
                            'is_taken' => isset($_POST['ci_istaken'][$i]) ? 1 : 0, 'condition_note' => $_POST['ci_cond'][$i] ?? '', 'note' => ''];
            }
            try { $pdo = db(); $pdo->beginTransaction(); collection_replace_items($pdo, $cid, $items); $pdo->commit(); } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); }
            log_activity('collection_update', 'collection', $cid, null, 'success', 'Toplama ürünleri güncellendi');
            flash('success', 'Toplama ürünleri güncellendi.');
        }
        redirect('modules/shipments/collection.php?id=' . $cid);
    }
    if ($action === 'status' && (can('shipments.collection_edit') || can('shipments.collection_complete'))) {
        $cid = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (set_collection_status($cid, $status, current_user_id())) { log_activity('collection_status', 'collection', $cid, null, 'success', 'Toplama durumu: ' . collection_status_label($status)); flash('success', 'Toplama durumu güncellendi.'); }
        redirect('modules/shipments/collection.php?id=' . $cid);
    }
    redirect('modules/shipments/collection.php');
}

/* ---- Detay ---- */
if ($id > 0) {
    $c = get_collection($id);
    if (!$c) { flash('error', 'Toplama kaydı bulunamadı.'); redirect('modules/shipments/collection.php'); }
    // Sevkiyatçı yalnız kendi sevkiyatına bağlı toplama
    if (!shipment_can_see_all() && (int) ($c['courier_id'] ?? 0) !== shipment_courier_personnel_id()) { flash('error', 'Bu toplamayı görüntüleme yetkiniz yok.'); redirect('modules/shipments/collection.php'); }
    layout_top('Toplama Detayı', 'shipments');
    ?>
    <div class="page-head"><h1 class="page-title">Toplama · <?= e((string) ($c['company_name'] ?? ('#' . $id))) ?></h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/shipments/collection.php')) ?>">← Toplamalar</a></div></div>
    <?= render_flashes() ?>
    <div class="card" style="max-width:900px"><div class="card-header"><h2>Alınacak Ürünler</h2>
        <span class="badge badge-info"><?= e(collection_status_label((string) $c['status'])) ?></span></div>
        <div class="card-body">
        <form method="post" action="<?= e(url('modules/shipments/collection.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="update_items"><input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Alındı</th><th>Ürün</th><th>Kod</th><th>Adet</th><th>Alınan</th><th>Durum notu</th></tr></thead>
                <tbody>
                <?php foreach (($c['items'] ?? []) as $it): ?>
                    <tr>
                        <td><input type="checkbox" name="ci_istaken[]" value="1"<?= (int) $it['is_taken'] === 1 ? ' checked' : '' ?>></td>
                        <td><input type="text" name="ci_name[]" value="<?= e((string) $it['name']) ?>"></td>
                        <td><input type="text" name="ci_code[]" value="<?= e((string) ($it['product_code'] ?? '')) ?>" style="width:100px"><input type="hidden" name="ci_barcode[]" value="<?= e((string) ($it['barcode'] ?? '')) ?>"></td>
                        <td><input type="text" name="ci_qty[]" value="<?= e((string) $it['qty']) ?>" style="width:70px" inputmode="decimal"></td>
                        <td><input type="text" name="ci_taken[]" value="<?= e((string) $it['taken_qty']) ?>" style="width:70px" inputmode="decimal"></td>
                        <td><input type="text" name="ci_cond[]" value="<?= e((string) ($it['condition_note'] ?? '')) ?>"></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($c['items'])): ?><tr><td colspan="6"><div class="empty">Ürün eklenmemiş.</div></td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <?php if (can('shipments.collection_edit')): ?><div class="form-actions"><button type="submit" class="btn btn-primary btn-sm">Ürünleri Kaydet</button></div><?php endif; ?>
        </form>
        </div>
    </div>
    <div class="card" style="max-width:900px"><div class="card-header"><h2>Durum</h2></div><div class="card-body">
        <form method="post" action="<?= e(url('modules/shipments/collection.php')) ?>" style="display:flex;gap:8px;align-items:flex-end">
            <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="form-group" style="margin:0"><label for="status">Toplama durumu</label>
                <select id="status" name="status"><?php foreach (collection_statuses() as $sv => $sl): ?><option value="<?= e($sv) ?>"<?= (string) $c['status'] === $sv ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select></div>
            <button type="submit" class="btn btn-sm">Güncelle</button>
        </form>
    </div></div>
    <?php
    layout_bottom();
    return;
}

/* ---- Liste + yeni ---- */
try {
    $st = db()->query('SELECT c.*, s.shipment_no FROM shipment_collections c LEFT JOIN shipments s ON s.id = c.shipment_id WHERE c.is_deleted = 0 ORDER BY c.id DESC LIMIT 500');
    $collections = $st->fetchAll();
} catch (Throwable $e) { $collections = []; }
$shipments = shipment_can_see_all() ? get_shipments([]) : get_shipments([]);

layout_top('Toplama İşlemleri', 'shipments');
?>
<div class="page-head"><h1 class="page-title">Toplama İşlemleri</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Sevkiyatlar</a></div></div>
<?= render_flashes() ?>

<?php if (can('shipments.collection_create')): ?>
<div class="card" style="max-width:900px"><div class="card-header"><h2>Yeni Toplama</h2></div><div class="card-body">
    <form method="post" action="<?= e(url('modules/shipments/collection.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="form-row">
            <div class="form-group"><label for="company_name">Firma / müşteri</label><input type="text" id="company_name" name="company_name"></div>
            <div class="form-group"><label for="shipment_id">Bağlı sevkiyat (ops.)</label>
                <select id="shipment_id" name="shipment_id"><option value="0">— Yok —</option>
                    <?php foreach ($shipments as $sh): ?><option value="<?= (int) $sh['id'] ?>"><?= e($sh['shipment_no'] . ' · ' . (string) ($sh['customer_name'] ?? '')) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="collection_date">Toplama tarihi</label><input type="date" id="collection_date" name="collection_date" value="<?= e(date('Y-m-d')) ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="package_count">Paket sayısı</label><input type="text" id="package_count" name="package_count" inputmode="numeric"></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="photo_required" value="1"> Fotoğraf zorunlu</label></div>
        </div>
        <div class="form-group"><label>Alınacak ürünler</label>
            <div class="table-wrap"><table class="table"><thead><tr><th>Ürün adı</th><th>Kod</th><th>Barkod</th><th>Adet</th><th>Açıklama</th></tr></thead><tbody>
                <?php for ($i = 0; $i < 3; $i++): ?>
                <tr><td><input type="text" name="item_name[]"></td><td><input type="text" name="item_code[]"></td><td><input type="text" name="item_barcode[]"></td><td><input type="text" name="item_qty[]" value="1" style="width:70px"></td><td><input type="text" name="item_note[]"></td></tr>
                <?php endfor; ?>
            </tbody></table></div>
        </div>
        <div class="form-group"><label for="note">Toplama notu</label><input type="text" id="note" name="note"></div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Toplama Oluştur</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php if (!$collections): ?><div class="card"><div class="card-body"><div class="empty">Toplama kaydı yok.</div></div></div>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Firma</th><th>Sevkiyat</th><th class="nowrap">Tarih</th><th>Paket</th><th>Durum</th><th class="nowrap">İşlem</th></tr></thead>
    <tbody>
    <?php foreach ($collections as $c): ?>
        <tr><td><a href="<?= e(url('modules/shipments/collection.php?id=' . (int) $c['id'])) ?>"><strong><?= e((string) ($c['company_name'] ?? '—')) ?></strong></a></td>
            <td><?= e((string) ($c['shipment_no'] ?? '—')) ?></td>
            <td class="nowrap"><?= e((string) ($c['collection_date'] ?? '—')) ?></td>
            <td><?= e((string) ($c['package_count'] ?? '—')) ?></td>
            <td><span class="badge badge-info"><?= e(collection_status_label((string) $c['status'])) ?></span></td>
            <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('modules/shipments/collection.php?id=' . (int) $c['id'])) ?>"><?= icon('eye', 'icon-xs') ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
<?php layout_bottom();
