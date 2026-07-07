<?php
declare(strict_types=1);
/**
 * modules/rma/_rma-form.php
 * Ortak iade-değişim formu (create + edit) — çoklu ürün satırlı.
 * Beklenen: $formAction, $submitLabel, $isEdit, $r, $platforms, $types, $reasons,
 *           $statuses, $cargoOptions, $brands, $receivedItems, $sentItems.
 */
defined('APP_ROOT') or exit;

$r = $r ?? [];
$v = static function (string $k, $d = '') use ($r) { return e((string) ($r[$k] ?? $d)); };
$isEdit = $isEdit ?? false;
$platforms = $platforms ?? rma_platforms();
$types = $types ?? rma_process_types();
$reasons = $reasons ?? rma_reason_types();
$statuses = $statuses ?? rma_statuses();
$cargoOptions = $cargoOptions ?? [];
$brands = $brands ?? [];
$receivedItems = $receivedItems ?? [];
$sentItems = $sentItems ?? [];
$curPlatform = (string) ($r['platform'] ?? '');
$curType = (string) ($r['process_type'] ?? 'iade');
$curReason = (string) ($r['reason_type'] ?? '');
$curStatus = (string) ($r['status'] ?? 'requested');

// Marka <option> üretici (seçili idyi işaretler)
$brandOptions = static function (string $selId) use ($brands): string {
    $h = '<option value="">— Marka —</option>';
    foreach ($brands as $bid => $bname) {
        $sel = ((string) $bid === $selId) ? ' selected' : '';
        $h .= '<option value="' . (int) $bid . '" data-name="' . e($bname) . '"' . $sel . '>' . e($bname) . '</option>';
    }
    return $h;
};

// Tek ürün satırı HTML'i (hem prefill hem template için)
$itemRow = static function (string $which, string $idx, array $it) use ($brandOptions): string {
    $name  = e((string) ($it['product_name'] ?? ''));
    $model = e((string) ($it['product_model'] ?? ''));
    $bId   = (string) ($it['brand_id'] ?? '');
    $bName = e((string) ($it['brand_name'] ?? ''));
    $qty   = e((string) ($it['quantity'] ?? '1'));
    $serial= e((string) ($it['serial_no'] ?? ''));
    $carrier = e((string) ($it['cargo_company'] ?? ''));
    $trackNo = e((string) ($it['cargo_tracking_no'] ?? ''));
    $trackUrl= e((string) ($it['cargo_tracking_url'] ?? ''));
    $note  = e((string) ($it['note'] ?? ''));
    $n = 'ritem';
    $extra = '';
    if ($which === 'sent') {
        $n = 'sitem';
        $sentAt = e((string) ($it['sent_at'] ?? ''));
        $delivAt = e((string) ($it['delivered_at'] ?? ''));
        $sentStatus = e((string) ($it['sent_status'] ?? ''));
        $extra = '
            <div class="form-group"><label>Gönderim tarihi</label><input type="date" name="' . $n . '[' . $idx . '][sent_at]" value="' . $sentAt . '"></div>
            <div class="form-group"><label>Teslim tarihi</label><input type="date" name="' . $n . '[' . $idx . '][delivered_at]" value="' . $delivAt . '"></div>
            <div class="form-group"><label>Gönderi durumu</label><input type="text" name="' . $n . '[' . $idx . '][sent_status]" value="' . $sentStatus . '"></div>';
    } else {
        $rStatus = e((string) ($it['received_status'] ?? ''));
        $cond = e((string) ($it['condition_note'] ?? ''));
        $recvAt = e((string) ($it['received_at'] ?? ''));
        $extra = '
            <div class="form-group"><label>Ürün durumu</label><input type="text" name="' . $n . '[' . $idx . '][received_status]" value="' . $rStatus . '" placeholder="Sağlam / hasarlı..."></div>
            <div class="form-group"><label>Kontrol notu</label><input type="text" name="' . $n . '[' . $idx . '][condition_note]" value="' . $cond . '"></div>
            <div class="form-group"><label>Teslim alınma</label><input type="date" name="' . $n . '[' . $idx . '][received_at]" value="' . $recvAt . '"></div>';
    }

    return '
    <div class="item-row">
        <button type="button" class="item-remove" title="Satırı sil" aria-label="Satırı sil">' . icon('x') . '</button>
        <div class="item-grid">
            <div class="form-group"><label>Ürün adı</label><input type="text" name="' . $n . '[' . $idx . '][product_name]" value="' . $name . '"></div>
            <div class="form-group"><label>Ürün modeli</label><input type="text" name="' . $n . '[' . $idx . '][product_model]" value="' . $model . '"></div>
            <div class="form-group"><label>Marka</label>
                <select name="' . $n . '[' . $idx . '][brand_id]" class="brand-select">' . $brandOptions($bId) . '</select>
                <input type="hidden" class="brand-name" name="' . $n . '[' . $idx . '][brand_name]" value="' . $bName . '">
            </div>
            <div class="form-group"><label>Adet</label><input type="number" min="1" step="1" name="' . $n . '[' . $idx . '][quantity]" value="' . $qty . '"></div>
            <div class="form-group"><label>Seri no</label><input type="text" name="' . $n . '[' . $idx . '][serial_no]" value="' . $serial . '"></div>
            <div class="form-group"><label>Kargo firması</label><input type="text" list="cargo_list" name="' . $n . '[' . $idx . '][cargo_company]" value="' . $carrier . '"></div>
            <div class="form-group"><label>Kargo takip no</label><input type="text" name="' . $n . '[' . $idx . '][cargo_tracking_no]" value="' . $trackNo . '"></div>
            <div class="form-group"><label>Takip linki (ops.)</label><input type="text" name="' . $n . '[' . $idx . '][cargo_tracking_url]" value="' . $trackUrl . '"></div>
            ' . $extra . '
            <div class="form-group item-note"><label>Not</label><input type="text" name="' . $n . '[' . $idx . '][note]" value="' . $note . '"></div>
        </div>
    </div>';
};
?>
<form method="post" action="<?= e($formAction) ?>" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>"><?php endif; ?>

    <datalist id="cargo_list">
        <?php foreach ($cargoOptions as $co): ?><option value="<?= e($co) ?>"></option><?php endforeach; ?>
    </datalist>

    <div class="card" style="max-width:1000px">
        <div class="card-header"><h2>Temel Bilgiler</h2></div>
        <div class="card-body">
            <?php if ($isEdit && !empty($r['reference_code'])): ?>
                <div class="form-group"><label>Referans kodu</label><input type="text" value="<?= $v('reference_code') ?>" disabled></div>
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group"><label for="process_date">İşlem tarihi</label><input type="date" id="process_date" name="process_date" value="<?= $v('process_date', date('Y-m-d')) ?>"></div>
                <div class="form-group"><label for="invoice_date">Fatura tarihi</label><input type="date" id="invoice_date" name="invoice_date" value="<?= $v('invoice_date') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="customer_name">Firma / Ad Soyad</label><input type="text" id="customer_name" name="customer_name" value="<?= $v('customer_name') ?>" required></div>
                <div class="form-group"><label for="customer_phone">Telefon numarası</label><input type="text" id="customer_phone" name="customer_phone" value="<?= $v('customer_phone') ?>" inputmode="tel"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="customer_email">E-posta</label><input type="email" id="customer_email" name="customer_email" value="<?= $v('customer_email') ?>"></div>
                <div class="form-group">
                    <label for="platform">Platform</label>
                    <select id="platform" name="platform">
                        <option value="">— Seçiniz —</option>
                        <?php
                        $inList = false;
                        foreach ($platforms as $pl) {
                            $sel = $curPlatform === $pl ? ' selected' : '';
                            if ($curPlatform === $pl) { $inList = true; }
                            echo '<option value="' . e($pl) . '"' . $sel . '>' . e($pl) . '</option>';
                        }
                        if ($curPlatform !== '' && !$inList) { echo '<option value="' . e($curPlatform) . '" selected>' . e($curPlatform) . '</option>'; }
                        ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="process_type">İşlem tipi</label>
                    <select id="process_type" name="process_type">
                        <?php foreach ($types as $k => $lbl): ?><option value="<?= e($k) ?>"<?= $curType === $k ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reason_type">Süreç sebebi</label>
                    <select id="reason_type" name="reason_type">
                        <option value="">— Seçiniz —</option>
                        <?php
                        $rInList = false;
                        foreach ($reasons as $rs) {
                            $sel = $curReason === $rs ? ' selected' : '';
                            if ($curReason === $rs) { $rInList = true; }
                            echo '<option value="' . e($rs) . '"' . $sel . '>' . e($rs) . '</option>';
                        }
                        if ($curReason !== '' && !$rInList) { echo '<option value="' . e($curReason) . '" selected>' . e($curReason) . '</option>'; }
                        ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="supplier">Tedarikçi</label><input type="text" id="supplier" name="supplier" value="<?= $v('supplier') ?>"></div>
                <div class="form-group"><label for="loss_amount">Zarar (TL)</label><input type="text" id="loss_amount" name="loss_amount" value="<?= $v('loss_amount', '0.00') ?>" inputmode="decimal"><div class="field-hint">Boş = 0. Negatif kabul edilmez.</div></div>
            </div>
            <div class="form-group"><label for="description">Açıklama</label><textarea id="description" name="description" rows="2"><?= $v('description') ?></textarea></div>
        </div>
    </div>

    <div class="card" style="max-width:1000px">
        <div class="card-header"><h2>Müşteriden Alınan Ürünler</h2></div>
        <div class="card-body">
            <div id="received_items" class="item-list">
                <?php
                if ($receivedItems) {
                    foreach ($receivedItems as $i => $it) { echo $itemRow('received', (string) $i, $it); }
                }
                ?>
            </div>
            <button type="button" class="btn btn-sm" id="add_received">+ Müşteriden Alınan Ürün Ekle</button>
        </div>
    </div>

    <div class="card" style="max-width:1000px">
        <div class="card-header"><h2>Müşteriye Gönderilen Ürünler</h2></div>
        <div class="card-body">
            <div id="sent_items" class="item-list">
                <?php
                if ($sentItems) {
                    foreach ($sentItems as $i => $it) { echo $itemRow('sent', (string) $i, $it); }
                }
                ?>
            </div>
            <button type="button" class="btn btn-sm" id="add_sent">+ Müşteriye Gönderilen Ürün Ekle</button>
        </div>
    </div>

    <div class="card" style="max-width:1000px">
        <div class="card-header"><h2>Notlar & Public Takip</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label for="internal_note">İç not (müşteriye ASLA gösterilmez)</label>
                <textarea id="internal_note" name="internal_note" rows="2"><?= $v('internal_note') ?></textarea>
            </div>
            <div class="form-group">
                <label for="customer_public_note">Müşteriye gösterilecek not (public takip sayfası)</label>
                <textarea id="customer_public_note" name="customer_public_note" rows="2"><?= $v('customer_public_note') ?></textarea>
            </div>
            <div class="form-group">
                <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:360px">
                    <input type="checkbox" name="public_tracking_enabled" value="1" <?= (int) ($r['public_tracking_enabled'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
                    <span>Public takip linki aktif</span>
                </label>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:1000px">
        <div class="card-header"><h2>Süreç Durumu</h2></div>
        <div class="card-body">
            <div class="form-group" style="max-width:360px">
                <label for="status">Durum</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $k => $lbl): ?><option value="<?= e($k) ?>"<?= $curStatus === $k ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="form-actions" style="max-width:1000px">
        <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
        <a class="btn" href="<?= e(url('modules/rma/index.php')) ?>">Vazgeç</a>
    </div>
</form>

<template id="tpl_received"><?= $itemRow('received', '__IDX__', []) ?></template>
<template id="tpl_sent"><?= $itemRow('sent', '__IDX__', []) ?></template>

<script>
(function(){
    var idx = <?php
        $keys = array_merge(array_keys((array) $receivedItems), array_keys((array) $sentItems));
        $nums = array_filter(array_map('intval', $keys), static fn ($x) => $x >= 0);
        echo max(100000, ($nums ? max($nums) + 1 : 0));
    ?>; // prefill indexleriyle çakışmayan başlangıç
    function addRow(listId, tplId){
        var list = document.getElementById(listId);
        var tpl = document.getElementById(tplId);
        if (!list || !tpl) return;
        var html = tpl.innerHTML.replace(/__IDX__/g, String(idx++));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var node = wrap.firstChild;
        list.appendChild(node);
    }
    var ar = document.getElementById('add_received');
    var as = document.getElementById('add_sent');
    if (ar) ar.addEventListener('click', function(){ addRow('received_items','tpl_received'); });
    if (as) as.addEventListener('click', function(){ addRow('sent_items','tpl_sent'); });

    // Satır sil + marka adı senkronu (delege)
    document.addEventListener('click', function(ev){
        var btn = ev.target.closest ? ev.target.closest('.item-remove') : null;
        if (btn){ var row = btn.closest('.item-row'); if (row) row.parentNode.removeChild(row); }
    });
    document.addEventListener('change', function(ev){
        var sel = ev.target;
        if (sel && sel.classList && sel.classList.contains('brand-select')){
            var row = sel.closest('.item-row');
            if (row){
                var hid = row.querySelector('.brand-name');
                var opt = sel.options[sel.selectedIndex];
                if (hid) hid.value = (sel.value && opt) ? (opt.getAttribute('data-name') || '') : '';
            }
        }
    });
})();
</script>
