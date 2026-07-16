<?php
/**
 * modules/customers/_customer-form.php
 * Ortak müşteri form alanları. Beklenen: $c (mevcut değerler dizisi), $types.
 */
$c = isset($c) && is_array($c) ? $c : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($c[$k] ?? $def));
$types = customer_types();
$curType = (string) ($c['customer_type'] ?? 'tr');
?>
<div class="card" style="max-width:900px">
    <div class="card-header"><h2>Firma Bilgileri</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="company_name">Firma adı *</label><input type="text" id="company_name" name="company_name" value="<?= $val('company_name') ?>" required></div>
            <div class="form-group"><label for="code">Cari kod</label><input type="text" id="code" name="code" value="<?= $val('code') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="customer_type">Müşteri tipi</label>
                <select id="customer_type" name="customer_type">
                    <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"<?= $curType === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="source">Kaynak</label><input type="text" id="source" name="source" value="<?= $val('source') ?>" placeholder="Web, fuar, referans…"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="contact_name">Yetkili kişi</label><input type="text" id="contact_name" name="contact_name" value="<?= $val('contact_name') ?>"></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="is_active" value="1"<?= (int) ($c['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif müşteri</label></div>
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
            <div class="form-group"><label for="district">İlçe</label><input type="text" id="district" name="district" value="<?= $val('district') ?>"></div>
        </div>
        <div class="form-group"><label for="address">Adres</label><textarea id="address" name="address" rows="2"><?= $val('address') ?></textarea></div>
    </div>
</div>

<div class="card" style="max-width:900px">
    <div class="card-header"><h2>Bakım Hatırlatma İzinleri</h2></div>
    <div class="card-body">
        <p class="text-muted" style="margin-top:0">Müşterinin periyodik bakım hatırlatmalarını hangi kanallardan almayı kabul ettiği (KVKK). İlgili kanal için izin yoksa otomatik gönderim yapılmaz.</p>
        <div class="form-group"><label class="check-inline"><input type="checkbox" name="maintenance_opt_in" value="1"<?= (int) ($c['maintenance_opt_in'] ?? 0) === 1 ? ' checked' : '' ?>> Bakım hatırlatması almak istiyor</label></div>
        <div class="form-row">
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="maint_email_consent" value="1"<?= (int) ($c['maint_email_consent'] ?? 0) === 1 ? ' checked' : '' ?>> E-posta iletişim izni</label></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="maint_whatsapp_consent" value="1"<?= (int) ($c['maint_whatsapp_consent'] ?? 0) === 1 ? ' checked' : '' ?>> WhatsApp iletişim izni</label></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="maint_phone_consent" value="1"<?= (int) ($c['maint_phone_consent'] ?? 0) === 1 ? ' checked' : '' ?>> Telefonla aranma izni</label></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="maint_consent_source">İzin kaynağı</label><input type="text" id="maint_consent_source" name="maint_consent_source" value="<?= $val('maint_consent_source') ?>" placeholder="Servis formu, web, telefon…"></div>
            <?php if (!empty($c['maint_consent_at'])): ?>
            <div class="form-group"><label>İzin tarihi</label><input type="text" value="<?= e(fmt_date((string) $c['maint_consent_at'])) ?>" disabled></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($c['maint_consent_revoked_at'])): ?>
        <p class="text-muted">İzin iptal tarihi: <?= e(fmt_date((string) $c['maint_consent_revoked_at'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="max-width:900px">
    <div class="card-header"><h2>Vergi & Notlar</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="tax_office">Vergi dairesi</label><input type="text" id="tax_office" name="tax_office" value="<?= $val('tax_office') ?>"></div>
            <div class="form-group"><label for="tax_no">Vergi / TC no</label><input type="text" id="tax_no" name="tax_no" value="<?= $val('tax_no') ?>"></div>
        </div>
        <div class="form-group"><label for="notes">Notlar</label><textarea id="notes" name="notes" rows="3"><?= $val('notes') ?></textarea></div>
    </div>
</div>
