<?php
declare(strict_types=1);
/**
 * modules/service/_service-form.php
 * Ortak servis kabul formu (create + edit).
 * Beklenen: $formAction, $submitLabel, $isEdit(bool), $r(array), $brands, $repairers, $personnel.
 */
defined('APP_ROOT') or exit;

$r = $r ?? [];
$v = static function (string $k, $d = '') use ($r) { return e((string) ($r[$k] ?? $d)); };
$isEdit = $isEdit ?? false;
$statuses  = service_statuses();
$devTypes  = service_device_types();
$appMethods = service_approval_methods();
$brands    = $brands ?? [];
$repairers = $repairers ?? [];
$personnel = $personnel ?? [];
$curStatus = (string) ($r['status'] ?? 'new');
$curDevType = (string) ($r['device_type'] ?? '');
$curBrand = (string) ($r['brand_id'] ?? '');
$curRepairer = (string) ($r['external_repairer_id'] ?? '');
$curPersonnel = (string) ($r['received_by_personnel_id'] ?? '');
$curAppMethod = (string) ($r['approval_method'] ?? '');
?>
<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>"><?php endif; ?>

    <div class="card" style="max-width:900px">
        <div class="card-header">
            <h2>Referans Bilgileri</h2>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Referans kodu</label>
                    <input type="text" value="<?= $isEdit ? $v('reference_code') : 'Kaydedince otomatik oluşur' ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="received_at">Teslim/kayıt tarihi</label>
                    <input type="date" id="received_at" name="received_at" value="<?= $v('received_at', date('Y-m-d')) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="status">Servis durumu</label>
                    <select id="status" name="status">
                        <?php foreach ($statuses as $k => $lbl): ?>
                            <option value="<?= e($k) ?>"<?= $curStatus === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="received_by_personnel_id">Kaydı açan / teslim alan personel</label>
                    <select id="received_by_personnel_id" name="received_by_personnel_id">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($personnel as $pid => $pname): ?>
                            <option value="<?= (int) $pid ?>"<?= $curPersonnel === (string) $pid ? ' selected' : '' ?>><?= e($pname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:900px">
        <div class="card-header"><h2>Teslim Eden Bilgileri</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="customer_name">Ad Soyad / Firma Ünvanı</label>
                    <input type="text" id="customer_name" name="customer_name" value="<?= $v('customer_name') ?>" required>
                </div>
                <div class="form-group">
                    <label for="customer_phone">Telefon</label>
                    <input type="text" id="customer_phone" name="customer_phone" value="<?= $v('customer_phone') ?>" inputmode="tel">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="customer_email">E-posta</label>
                    <input type="email" id="customer_email" name="customer_email" value="<?= $v('customer_email') ?>">
                </div>
                <div class="form-group">
                    <label for="customer_tax_no">Vergi No / T.C. No (opsiyonel)</label>
                    <input type="text" id="customer_tax_no" name="customer_tax_no" value="<?= $v('customer_tax_no') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="customer_address">Adres</label>
                <textarea id="customer_address" name="customer_address" rows="2"><?= $v('customer_address') ?></textarea>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:900px">
        <div class="card-header"><h2>Teslim Edilen Cihaz</h2></div>
        <div class="card-body">
            <?php if ($isEdit && !empty($r['received_photo_path'])): ?>
                <div class="form-group">
                    <label>Mevcut fotoğraf</label>
                    <div style="display:flex;align-items:center;gap:12px">
                        <img src="<?= e(url($r['received_photo_path'])) ?>" alt="cihaz" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
                        <label class="small" style="display:flex;align-items:center;gap:6px;font-weight:500">
                            <input type="checkbox" name="remove_photo" value="1" style="width:auto"> Fotoğrafı kaldır
                        </label>
                    </div>
                </div>
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="device_type">Cihaz türü</label>
                    <select id="device_type" name="device_type">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($devTypes as $dt): ?>
                            <option value="<?= e($dt) ?>"<?= $curDevType === $dt ? ' selected' : '' ?>><?= e($dt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="brand_id">Marka</label>
                    <select id="brand_id" name="brand_id">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($brands as $bid => $bname): ?>
                            <option value="<?= (int) $bid ?>" data-name="<?= e($bname) ?>"<?= $curBrand === (string) $bid ? ' selected' : '' ?>><?= e($bname) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="brand_name" id="brand_name" value="<?= $v('brand_name') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="device_model">Model</label>
                    <input type="text" id="device_model" name="device_model" value="<?= $v('device_model') ?>">
                </div>
                <div class="form-group">
                    <label for="quantity">Miktar</label>
                    <input type="number" id="quantity" name="quantity" value="<?= $v('quantity', '1') ?>" min="1" step="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="serial_no">Seri No</label>
                    <input type="text" id="serial_no" name="serial_no" value="<?= $v('serial_no') ?>">
                </div>
                <div class="form-group">
                    <label for="accessories">Aksesuarlar / ekler</label>
                    <input type="text" id="accessories" name="accessories" value="<?= $v('accessories') ?>" placeholder="Güç kablosu, USB, toner, tepsi...">
                </div>
            </div>
            <div class="form-group">
                <label for="problem_description">Servis sebebi / sorun</label>
                <textarea id="problem_description" name="problem_description" rows="2"><?= $v('problem_description') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="physical_condition">Fiziksel durum notu</label>
                    <input type="text" id="physical_condition" name="physical_condition" value="<?= $v('physical_condition') ?>">
                </div>
                <div class="form-group">
                    <label for="photo">Cihaz fotoğrafı (opsiyonel, max 4 MB)</label>
                    <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp">
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:900px">
        <div class="card-header"><h2>Dış Tamirci Bilgileri</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="external_repairer_id">Dış tamirci</label>
                    <select id="external_repairer_id" name="external_repairer_id">
                        <option value="">— Seçiniz —</option>
                        <?php foreach ($repairers as $rid => $rname): ?>
                            <option value="<?= (int) $rid ?>"<?= $curRepairer === (string) $rid ? ' selected' : '' ?>><?= e($rname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sent_to_repairer_at">Gönderim tarihi</label>
                    <input type="date" id="sent_to_repairer_at" name="sent_to_repairer_at" value="<?= $v('sent_to_repairer_at') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="returned_from_repairer_at">Dönüş tarihi</label>
                    <input type="date" id="returned_from_repairer_at" name="returned_from_repairer_at" value="<?= $v('returned_from_repairer_at') ?>">
                </div>
                <div class="form-group">
                    <label for="repairer_cost">Tamircinin bildirdiği masraf (TL)</label>
                    <input type="text" id="repairer_cost" name="repairer_cost" value="<?= $v('repairer_cost') ?>" inputmode="decimal">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="customer_price">Müşteriye bildirilen tutar (TL)</label>
                    <input type="text" id="customer_price" name="customer_price" value="<?= $v('customer_price') ?>" inputmode="decimal">
                </div>
                <div class="form-group">
                    <label>Kâr / hizmet bedeli</label>
                    <input type="text" value="<?= (($r['profit_amount'] ?? null) !== null) ? e((string) $r['profit_amount']) : '' ?>" disabled placeholder="otomatik hesaplanır">
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:900px">
        <div class="card-header"><h2>Müşteri Onayı & Ödeme (özet)</h2></div>
        <div class="card-body">
            <div class="form-group">
                <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:320px">
                    <input type="checkbox" name="approval_required" value="1" <?= (int) ($r['approval_required'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
                    <span>Müşteri onayı gerekiyor</span>
                </label>
                <div class="field-hint">Onay/ödeme adımları kayıt detayından yürütülür. “Tamirde” durumuna geçmek için müşteri onayı şarttır.</div>
            </div>

            <div class="form-group">
                <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:320px">
                    <input type="checkbox" name="is_digital_approved" value="1" <?= (int) ($r['is_digital_approved'] ?? 0) === 1 ? 'checked' : '' ?> style="width:auto">
                    <span>Müşteri dijital onay verdi (servis koşulları)</span>
                </label>
            </div>

            <div class="form-group">
                <label for="final_note">Genel not (panel içi)</label>
                <textarea id="final_note" name="final_note" rows="2"><?= $v('final_note') ?></textarea>
            </div>

            <div class="form-group">
                <label for="internal_note">İç not (müşteriye ASLA gösterilmez)</label>
                <textarea id="internal_note" name="internal_note" rows="2"><?= $v('internal_note') ?></textarea>
            </div>

            <div class="form-group">
                <label for="customer_public_note">Müşteriye gösterilecek not (public takip sayfası)</label>
                <textarea id="customer_public_note" name="customer_public_note" rows="2"><?= $v('customer_public_note') ?></textarea>
            </div>

            <div class="form-group">
                <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:420px">
                    <input type="checkbox" name="show_price_public" value="1" <?= (int) ($r['show_price_public'] ?? 0) === 1 ? 'checked' : '' ?> style="width:auto">
                    <span>Müşteriye bildirilen tutarı public takip sayfasında göster</span>
                </label>
            </div>
        </div>
    </div>

    <div class="form-actions" style="max-width:900px">
        <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
        <a class="btn" href="<?= e(url('modules/service/index.php')) ?>">Vazgeç</a>
    </div>
</form>

<script>
(function(){
    // Marka seçilince gizli brand_name'i doldur (formda serbest metin de korunur)
    var sel = document.getElementById('brand_id');
    var hid = document.getElementById('brand_name');
    if (sel && hid) {
        sel.addEventListener('change', function(){
            var opt = sel.options[sel.selectedIndex];
            hid.value = (sel.value && opt) ? (opt.getAttribute('data-name') || '') : hid.value;
        });
    }
})();
</script>
