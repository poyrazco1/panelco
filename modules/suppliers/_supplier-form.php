<?php
/**
 * modules/suppliers/_supplier-form.php — ortak tedarikçi form alanları. Beklenen: $s
 */
$s = isset($s) && is_array($s) ? $s : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($s[$k] ?? $def));
$cur = (string) ($s['currency'] ?? 'TRY');
?>
<div class="card" style="max-width:900px">
    <div class="card-header"><h2>Firma Bilgileri</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="company_name">Firma adı *</label><input type="text" id="company_name" name="company_name" value="<?= $val('company_name') ?>" required></div>
            <div class="form-group"><label for="code">Kod</label><input type="text" id="code" name="code" value="<?= $val('code') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="contact_name">Yetkili kişi</label><input type="text" id="contact_name" name="contact_name" value="<?= $val('contact_name') ?>"></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="is_active" value="1"<?= (int) ($s['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif tedarikçi</label></div>
        </div>
    </div>
</div>

<div class="card" style="max-width:900px">
    <div class="card-header"><h2>İletişim & Adres</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= $val('phone') ?>" inputmode="tel"></div>
            <div class="form-group"><label for="whatsapp">WhatsApp</label><input type="text" id="whatsapp" name="whatsapp" value="<?= $val('whatsapp') ?>" inputmode="tel"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="email">E-posta</label><input type="email" id="email" name="email" value="<?= $val('email') ?>"></div>
            <div class="form-group"><label for="website">Web sitesi</label><input type="text" id="website" name="website" value="<?= $val('website') ?>" placeholder="https://..."></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="country">Ülke</label><input type="text" id="country" name="country" value="<?= $val('country') ?>"></div>
            <div class="form-group"><label for="city">Şehir</label><input type="text" id="city" name="city" value="<?= $val('city') ?>"></div>
        </div>
        <div class="form-group"><label for="address">Adres</label><textarea id="address" name="address" rows="2"><?= $val('address') ?></textarea></div>
    </div>
</div>

<div class="card" style="max-width:900px">
    <div class="card-header"><h2>Ticari Bilgiler</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="product_groups">Ürün grupları</label><input type="text" id="product_groups" name="product_groups" value="<?= $val('product_groups') ?>" placeholder="Toner, kartuş, sarf…"></div>
            <div class="form-group"><label for="currency">Para birimi</label>
                <select id="currency" name="currency">
                    <?php foreach (['TRY' => 'TL (₺)', 'USD' => 'USD ($)', 'EUR' => 'EUR (€)'] as $cv => $cl): ?><option value="<?= e($cv) ?>"<?= $cur === $cv ? ' selected' : '' ?>><?= e($cl) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="payment_terms">Ödeme şartları</label><input type="text" id="payment_terms" name="payment_terms" value="<?= $val('payment_terms') ?>" placeholder="30 gün vade…"></div>
            <div class="form-group"><label for="delivery_time">Teslimat süresi</label><input type="text" id="delivery_time" name="delivery_time" value="<?= $val('delivery_time') ?>" placeholder="3-5 iş günü…"></div>
        </div>
        <div class="form-group"><label for="notes">Notlar</label><textarea id="notes" name="notes" rows="3"><?= $val('notes') ?></textarea></div>
    </div>
</div>
