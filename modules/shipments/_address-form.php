<?php
/** modules/shipments/_address-form.php — sevkiyat adresi form alanları. Beklenen: $a */
$a = isset($a) && is_array($a) ? $a : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($a[$k] ?? $def));
$type = (string) ($a['address_type'] ?? 'delivery');
?>
<div class="card" style="max-width:900px"><div class="card-header"><h2>Firma & İletişim</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group"><label for="company_name">Firma / müşteri adı *</label><input type="text" id="company_name" name="company_name" value="<?= $val('company_name') ?>" required></div>
        <div class="form-group"><label for="contact_name">Yetkili kişi</label><input type="text" id="contact_name" name="contact_name" value="<?= $val('contact_name') ?>"></div>
        <div class="form-group"><label for="address_type">Adres tipi</label>
            <select id="address_type" name="address_type"><?php foreach (shipment_address_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= $val('phone') ?>" inputmode="tel"></div>
        <div class="form-group"><label for="whatsapp">WhatsApp</label><input type="text" id="whatsapp" name="whatsapp" value="<?= $val('whatsapp') ?>" inputmode="tel"></div>
        <div class="form-group"><label for="email">E-posta</label><input type="email" id="email" name="email" value="<?= $val('email') ?>"></div>
    </div>
</div></div>
<div class="card" style="max-width:900px"><div class="card-header"><h2>Adres & Konum</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group"><label for="country">Ülke</label><input type="text" id="country" name="country" value="<?= $val('country') ?>"></div>
        <div class="form-group"><label for="city">İl</label><input type="text" id="city" name="city" value="<?= $val('city') ?>"></div>
        <div class="form-group"><label for="district">İlçe</label><input type="text" id="district" name="district" value="<?= $val('district') ?>"></div>
        <div class="form-group"><label for="neighborhood">Mahalle</label><input type="text" id="neighborhood" name="neighborhood" value="<?= $val('neighborhood') ?>"></div>
    </div>
    <div class="form-group"><label for="address">Açık adres</label><textarea id="address" name="address" rows="2"><?= $val('address') ?></textarea></div>
    <div class="form-row">
        <div class="form-group"><label for="location_url">Konum linki</label><input type="text" id="location_url" name="location_url" value="<?= $val('location_url') ?>" placeholder="Google Maps linki"></div>
        <div class="form-group"><label for="lat">Enlem</label><input type="text" id="lat" name="lat" value="<?= $val('lat') ?>"></div>
        <div class="form-group"><label for="lng">Boylam</label><input type="text" id="lng" name="lng" value="<?= $val('lng') ?>"></div>
    </div>
</div></div>
<div class="card" style="max-width:900px"><div class="card-header"><h2>Erişim & Notlar</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group"><label for="working_hours">Çalışma saatleri</label><input type="text" id="working_hours" name="working_hours" value="<?= $val('working_hours') ?>" placeholder="09:00 - 18:00"></div>
        <div class="form-group"><label for="vehicle_access">Araç giriş uygunluğu</label><input type="text" id="vehicle_access" name="vehicle_access" value="<?= $val('vehicle_access') ?>"></div>
        <div class="form-group"><label for="floor_building">Kat / bina bilgisi</label><input type="text" id="floor_building" name="floor_building" value="<?= $val('floor_building') ?>"></div>
    </div>
    <div class="form-check"><label><input type="checkbox" name="has_elevator" value="1"<?= (int) ($a['has_elevator'] ?? 0) === 1 ? ' checked' : '' ?>> Asansör var</label></div>
    <div class="form-check"><label><input type="checkbox" name="is_active" value="1"<?= (int) ($a['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif adres</label></div>
    <div class="form-group"><label for="default_note">Varsayılan teslimat notu</label><input type="text" id="default_note" name="default_note" value="<?= $val('default_note') ?>"></div>
    <div class="form-group"><label for="notes">Özel notlar</label><textarea id="notes" name="notes" rows="2"><?= $val('notes') ?></textarea></div>
</div></div>
