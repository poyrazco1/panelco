<?php
declare(strict_types=1);

/**
 * modules/shipments/route-create.php — 3 Adımlı Rota Oluştur / Düzenle.
 *
 *  Adım 1: Rota bilgileri (ad, tarih, sürücü, başlangıç/bitiş, not)
 *  Adım 2: Durak seçimi (adres defterinden çoklu seçim + işlem tipi/ref/not)
 *  Adım 3: Sıralama ve onay (yukarı/aşağı ile sırala, kaydet)
 *
 * Tek form + JS adım geçişi. Sunucu tarafında doğrulanır ve rota+duraklar
 * transaction ile yazılır. Google Maps API kullanılmaz.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/shipments.php';

auth_boot();
require_permission('shipments.create');

$editId = (int) ($_GET['id'] ?? 0);
$editing = $editId > 0;
$route = null;
if ($editing) {
    $route = get_route($editId);
    if (!$route) { flash('error', 'Rota bulunamadı.'); redirect('modules/shipments/index.php'); }
    if (!can('shipments.edit')) { require_permission('shipments.edit'); }
}

$errors = [];
$set = ship_route_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $header = ship_route_header_fields($_POST);
    if ($header['route_name'] === '') { $errors[] = 'Rota adı zorunludur.'; }

    // Seçili durakları sıraya göre topla
    $sel = (array) ($_POST['sel'] ?? []);
    $ord = (array) ($_POST['ord'] ?? []);
    $stops = [];
    foreach ($sel as $addrId => $on) {
        $addrId = (int) $addrId;
        if ($addrId <= 0) { continue; }
        $stops[] = [
            'address_id'     => $addrId,
            'operation_type' => (string) ($_POST['op'][$addrId] ?? 'delivery'),
            'reference_no'   => (string) ($_POST['ref'][$addrId] ?? ''),
            'stop_note'      => (string) ($_POST['snote'][$addrId] ?? ''),
            '_order'         => (int) ($ord[$addrId] ?? 9999),
        ];
    }
    usort($stops, static fn($a, $b) => $a['_order'] <=> $b['_order']);
    if (!$stops) { $errors[] = 'En az bir durak (adres) seçmelisiniz.'; }

    if (!$errors) {
        $uid = current_user_id();
        if ($editing) {
            update_route($editId, $header, $uid);
            if (!empty($header['driver_user_id'])) { assign_route_driver($editId, (int) $header['driver_user_id'], $uid); }
            route_replace_stops($editId, $stops, $uid);
            log_activity('shipment_route_edit', 'shipment_route', $editId, null, 'success', 'Rota güncellendi: ' . $header['route_name']);
            flash('success', 'Rota güncellendi.');
            redirect('modules/shipments/route-view.php?id=' . $editId);
        } else {
            $rid = create_route($header, $stops, $uid);
            if ($rid > 0) {
                log_activity('shipment_route_create', 'shipment_route', $rid, null, 'success', 'Rota oluşturuldu: ' . $header['route_name']);
                flash('success', 'Rota oluşturuldu.');
                redirect('modules/shipments/route-view.php?id=' . $rid);
            }
            $errors[] = 'Rota kaydedilemedi. Lütfen tekrar deneyin.';
        }
    }
}

$drivers = ship_driver_options();
$addresses = get_shipment_addresses(['active' => 1]);

// Düzenleme için mevcut durakları hazırla (adres id → durak verisi)
$existing = [];
if ($editing) {
    foreach (get_route_stops($editId) as $i => $s) {
        $existing[(int) $s['address_id']] = [
            'op' => (string) $s['operation_type'], 'ref' => (string) ($s['reference_no'] ?? ''),
            'note' => (string) ($s['stop_note'] ?? ''), 'order' => (int) $s['stop_order'],
        ];
    }
}

// Form değerleri (POST geri dönüşü veya edit)
$val = static function (string $k, string $def = '') use ($route): string {
    if (isset($_POST[$k])) { return (string) $_POST[$k]; }
    if ($route && isset($route[$k])) { return (string) $route[$k]; }
    return $def;
};
$curDriver = (int) ($_POST['driver_user_id'] ?? ($route['driver_user_id'] ?? 0));

layout_top($editing ? 'Rota Düzenle' : 'Rota Oluştur', 'shipments');
?>
<div class="page-head">
    <h1 class="page-title"><?= $editing ? 'Rota Düzenle' : 'Rota Oluştur' ?></h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/shipments/index.php')) ?>">← Sevkiyat Takibi</a>
    </div>
</div>
<?= render_flashes() ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<?php if (!$addresses): ?>
<div class="card"><div class="card-body">
    <div class="empty">Henüz sevkiyat adresi yok. Rota oluşturmak için önce adres ekleyin.</div>
    <?php if (can('shipment_addresses.create')): ?>
    <div style="margin-top:12px"><a class="btn btn-primary btn-sm" href="<?= e(url('modules/shipments/address-create.php')) ?>"><?= icon('plus') ?>Yeni Adres</a></div>
    <?php endif; ?>
</div></div>
<?php else: ?>

<!-- Adım göstergesi -->
<ol class="wiz-steps" id="wizSteps">
    <li class="wiz-step is-active" data-step="1"><span class="wiz-num">1</span> Rota Bilgileri</li>
    <li class="wiz-step" data-step="2"><span class="wiz-num">2</span> Durak Seçimi</li>
    <li class="wiz-step" data-step="3"><span class="wiz-num">3</span> Sıralama & Onay</li>
</ol>

<form method="post" action="" id="routeForm" class="wiz-form">
    <?= csrf_field() ?>

    <!-- ADIM 1 -->
    <section class="wiz-panel is-active" data-panel="1">
        <div class="card"><div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label for="route_name">Rota Adı *</label>
                    <input type="text" id="route_name" name="route_name" value="<?= e($val('route_name')) ?>" placeholder="Örn. Kadıköy Bölgesi - Sabah" required>
                </div>
                <div class="form-group">
                    <label for="route_date">Tarih</label>
                    <input type="date" id="route_date" name="route_date" value="<?= e($val('route_date', date('Y-m-d'))) ?>">
                </div>
                <div class="form-group">
                    <label for="driver_user_id">Sevkiyatçı</label>
                    <select id="driver_user_id" name="driver_user_id">
                        <option value="0">— Sonra ata —</option>
                        <?php foreach ($drivers as $uid => $dn): ?>
                            <option value="<?= (int) $uid ?>"<?= $curDriver === (int) $uid ? ' selected' : '' ?>><?= e($dn) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$drivers): ?><small class="muted">Sevkiyatçı için personelin panel kullanıcısına bağlı olması gerekir.</small><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="start_point">Başlangıç Noktası</label>
                    <input type="text" id="start_point" name="start_point" value="<?= e($val('start_point', $set['default_start'])) ?>" placeholder="Depo / merkez adresi">
                </div>
                <div class="form-group">
                    <label for="end_point">Bitiş Noktası</label>
                    <input type="text" id="end_point" name="end_point" value="<?= e($val('end_point', $set['default_end'])) ?>" placeholder="Bitiş adresi (boşsa son durak)">
                </div>
                <div class="form-group form-col-full">
                    <label for="note">Rota Notu</label>
                    <textarea id="note" name="note" rows="2"><?= e($val('note')) ?></textarea>
                </div>
            </div>
        </div></div>
        <div class="wiz-nav">
            <span></span>
            <button type="button" class="btn btn-primary" data-next="2">Devam: Durak Seçimi →</button>
        </div>
    </section>

    <!-- ADIM 2 -->
    <section class="wiz-panel" data-panel="2">
        <div class="card">
            <div class="card-header">
                <strong>Durak Seçimi</strong>
                <input type="search" id="addrSearch" class="input-inline" placeholder="Adres ara: firma, il, ilçe…" autocomplete="off">
            </div>
            <div class="card-body">
                <p class="muted small">Rotaya eklemek istediğiniz adresleri işaretleyin ve işlem tipini seçin.</p>
                <div class="stop-pick-list" id="stopPickList">
                    <?php foreach ($addresses as $a):
                        $aid = (int) $a['id'];
                        $ex = $existing[$aid] ?? null;
                        $checked = isset($_POST['sel'][$aid]) || $ex !== null;
                        $loc = trim((string) ($a['city'] ?? '') . ' / ' . (string) ($a['district'] ?? ''), ' /');
                        $searchStr = mb_strtolower(trim((string) $a['company_name'] . ' ' . $loc . ' ' . (string) ($a['contact_name'] ?? '')), 'UTF-8');
                    ?>
                    <div class="stop-pick<?= $checked ? ' is-picked' : '' ?>" data-addr="<?= $aid ?>" data-search="<?= e($searchStr) ?>">
                        <label class="stop-pick-check">
                            <input type="checkbox" name="sel[<?= $aid ?>]" value="1"<?= $checked ? ' checked' : '' ?>>
                            <span class="stop-pick-main">
                                <span class="stop-pick-name"><?= e((string) $a['company_name']) ?></span>
                                <span class="stop-pick-meta"><?= e($loc ?: '—') ?><?= $a['contact_name'] ? ' · ' . e((string) $a['contact_name']) : '' ?></span>
                            </span>
                        </label>
                        <div class="stop-pick-fields">
                            <select name="op[<?= $aid ?>]" aria-label="İşlem tipi">
                                <?php foreach (ship_stop_operations() as $k => $l): $selOp = $ex['op'] ?? ((string) ($a['address_type'] ?? 'delivery')); ?>
                                    <option value="<?= e($k) ?>"<?= (string)($_POST['op'][$aid] ?? $selOp) === $k ? ' selected' : '' ?>><?= e($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="ref[<?= $aid ?>]" value="<?= e((string) ($_POST['ref'][$aid] ?? ($ex['ref'] ?? ''))) ?>" placeholder="Ref / Sipariş no">
                            <input type="text" name="snote[<?= $aid ?>]" value="<?= e((string) ($_POST['snote'][$aid] ?? ($ex['note'] ?? ''))) ?>" placeholder="Durak notu">
                            <input type="hidden" name="ord[<?= $aid ?>]" value="<?= (int) ($ex['order'] ?? 0) ?>">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="stop-pick-empty" id="stopPickEmpty" hidden>Aramanıza uygun adres yok.</div>
            </div>
        </div>
        <div class="wiz-nav">
            <button type="button" class="btn" data-prev="1">← Geri</button>
            <div><span class="muted small" id="pickCount"></span>
            <button type="button" class="btn btn-primary" data-next="3">Devam: Sıralama →</button></div>
        </div>
    </section>

    <!-- ADIM 3 -->
    <section class="wiz-panel" data-panel="3">
        <div class="card">
            <div class="card-header"><strong>Durak Sırası</strong><span class="muted small">Yukarı/aşağı ile sıralayın</span></div>
            <div class="card-body">
                <ol class="stop-order-list" id="stopOrderList"></ol>
                <div class="stop-order-empty" id="stopOrderEmpty">Henüz durak seçilmedi. 2. adımdan adres seçin.</div>
            </div>
        </div>
        <div class="wiz-nav">
            <button type="button" class="btn" data-prev="2">← Geri</button>
            <button type="submit" class="btn btn-primary" id="routeSaveBtn"><?= icon('check-circle') ?><?= $editing ? 'Değişiklikleri Kaydet' : 'Rotayı Kaydet' ?></button>
        </div>
    </section>
</form>

<script>
(function(){
    var form = document.getElementById('routeForm');
    var steps = document.querySelectorAll('#wizSteps .wiz-step');
    var panels = document.querySelectorAll('.wiz-panel');
    function go(step){
        panels.forEach(function(p){ p.classList.toggle('is-active', p.getAttribute('data-panel') === String(step)); });
        steps.forEach(function(s){
            var n = Number(s.getAttribute('data-step'));
            s.classList.toggle('is-active', n === Number(step));
            s.classList.toggle('is-done', n < Number(step));
        });
        if (String(step) === '3') { buildOrder(); }
        window.scrollTo({top:0, behavior:'smooth'});
    }
    form.querySelectorAll('[data-next]').forEach(function(b){ b.addEventListener('click', function(){ go(b.getAttribute('data-next')); }); });
    form.querySelectorAll('[data-prev]').forEach(function(b){ b.addEventListener('click', function(){ go(b.getAttribute('data-prev')); }); });

    // Adım 2: seçim işaretleme + arama
    var list = document.getElementById('stopPickList');
    list.addEventListener('change', function(e){
        if (e.target.type === 'checkbox') {
            e.target.closest('.stop-pick').classList.toggle('is-picked', e.target.checked);
            updateCount();
        }
    });
    function updateCount(){
        var n = list.querySelectorAll('input[type=checkbox]:checked').length;
        var c = document.getElementById('pickCount');
        if (c) c.textContent = n + ' durak seçildi';
    }
    var search = document.getElementById('addrSearch');
    search.addEventListener('input', function(){
        var q = search.value.toLowerCase().trim();
        var any = false;
        list.querySelectorAll('.stop-pick').forEach(function(row){
            var hit = q === '' || (row.getAttribute('data-search')||'').indexOf(q) !== -1;
            row.style.display = hit ? '' : 'none';
            if (hit) any = true;
        });
        document.getElementById('stopPickEmpty').hidden = any;
    });
    updateCount();

    // Adım 3: sıralama listesi
    var orderList = document.getElementById('stopOrderList');
    function buildOrder(){
        var picked = Array.prototype.slice.call(list.querySelectorAll('.stop-pick.is-picked'));
        // mevcut ord değerine göre sırala
        picked.sort(function(a,b){
            var oa = Number(a.querySelector('input[name^="ord"]').value)||0;
            var ob = Number(b.querySelector('input[name^="ord"]').value)||0;
            if (oa === ob) return 0; if (!oa) return 1; if (!ob) return -1; return oa-ob;
        });
        orderList.innerHTML = '';
        document.getElementById('stopOrderEmpty').style.display = picked.length ? 'none' : '';
        picked.forEach(function(row){
            var addr = row.getAttribute('data-addr');
            var name = row.querySelector('.stop-pick-name').textContent;
            var meta = row.querySelector('.stop-pick-meta').textContent;
            var op = row.querySelector('select[name^="op"]');
            var opLabel = op.options[op.selectedIndex].text;
            var li = document.createElement('li');
            li.className = 'stop-order-row'; li.setAttribute('data-addr', addr);
            li.innerHTML = '<span class="so-handle">≡</span>'
                + '<span class="so-body"><span class="so-name"></span><span class="so-meta"></span></span>'
                + '<span class="so-op badge badge-muted"></span>'
                + '<span class="so-actions"><button type="button" class="btn btn-xs" data-dir="up">↑</button>'
                + '<button type="button" class="btn btn-xs" data-dir="down">↓</button></span>';
            li.querySelector('.so-name').textContent = name;
            li.querySelector('.so-meta').textContent = meta;
            li.querySelector('.so-op').textContent = opLabel;
            orderList.appendChild(li);
        });
        syncOrder();
    }
    orderList.addEventListener('click', function(e){
        var btn = e.target.closest('[data-dir]'); if (!btn) return;
        var row = btn.closest('.stop-order-row');
        if (btn.getAttribute('data-dir') === 'up' && row.previousElementSibling) {
            orderList.insertBefore(row, row.previousElementSibling);
        } else if (btn.getAttribute('data-dir') === 'down' && row.nextElementSibling) {
            orderList.insertBefore(row.nextElementSibling, row);
        }
        syncOrder();
    });
    function syncOrder(){
        var i = 1;
        orderList.querySelectorAll('.stop-order-row').forEach(function(row){
            var addr = row.getAttribute('data-addr');
            var hid = list.querySelector('input[name="ord['+addr+']"]');
            if (hid) hid.value = i;
            i++;
        });
    }
    // submit öncesi sırayı garanti et
    form.addEventListener('submit', function(){ if (document.querySelector('.wiz-panel[data-panel="3"]').classList.contains('is-active')) syncOrder(); });
})();
</script>
<?php endif; ?>
<?php layout_bottom();
