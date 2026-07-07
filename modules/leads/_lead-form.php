<?php
/** modules/leads/_lead-form.php — ortak lead form alanları. Beklenen: $l, $people */
$l = isset($l) && is_array($l) ? $l : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($l[$k] ?? $def));
$people = $people ?? [];
$st = (string) ($l['status'] ?? 'new');
?>
<div class="card" style="max-width:900px"><div class="card-header"><h2>Lead Bilgileri</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group"><label for="company_name">Firma adı *</label><input type="text" id="company_name" name="company_name" value="<?= $val('company_name') ?>" required></div>
        <div class="form-group"><label for="contact_name">Yetkili kişi</label><input type="text" id="contact_name" name="contact_name" value="<?= $val('contact_name') ?>"></div>
        <div class="form-group"><label for="status">Durum</label>
            <select id="status" name="status"><?php foreach (lead_statuses() as $k => $lab): ?><option value="<?= e($k) ?>"<?= $st === $k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= $val('phone') ?>" inputmode="tel"></div>
        <div class="form-group"><label for="whatsapp">WhatsApp</label><input type="text" id="whatsapp" name="whatsapp" value="<?= $val('whatsapp') ?>" inputmode="tel"></div>
        <div class="form-group"><label for="email">E-posta</label><input type="email" id="email" name="email" value="<?= $val('email') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="website">Web sitesi</label><input type="text" id="website" name="website" value="<?= $val('website') ?>"></div>
        <div class="form-group"><label for="instagram">Instagram</label><input type="text" id="instagram" name="instagram" value="<?= $val('instagram') ?>"></div>
        <div class="form-group"><label for="maps_url">Google Maps linki</label><input type="text" id="maps_url" name="maps_url" value="<?= $val('maps_url') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="sector">Sektör</label><input type="text" id="sector" name="sector" value="<?= $val('sector') ?>"></div>
        <div class="form-group"><label for="city">İl</label><input type="text" id="city" name="city" value="<?= $val('city') ?>"></div>
        <div class="form-group"><label for="district">İlçe</label><input type="text" id="district" name="district" value="<?= $val('district') ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="source">Kaynak</label><input type="text" id="source" name="source" value="<?= $val('source') ?>" placeholder="Google, Instagram, referans…"></div>
        <div class="form-group"><label for="assigned_personnel_id">Sorumlu personel</label>
            <select id="assigned_personnel_id" name="assigned_personnel_id"><option value="0">— Yok —</option>
                <?php foreach ($people as $pid => $pname): ?><option value="<?= (int) $pid ?>"<?= (int) ($l['assigned_personnel_id'] ?? 0) === (int) $pid ? ' selected' : '' ?>><?= e($pname) ?></option><?php endforeach; ?>
            </select></div>
    </div>
    <div class="form-group"><label for="notes">Not</label><textarea id="notes" name="notes" rows="2"><?= $val('notes') ?></textarea></div>
</div></div>
