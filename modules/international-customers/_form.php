<?php
/**
 * modules/international-customers/_form.php
 * Ortak yurtdışı müşteri form alanları. Beklenen değişkenler:
 *   $c (mevcut değerler), $statuses, $types, $cats, $users, $selTypes, $selCats
 */
$c = isset($c) && is_array($c) ? $c : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($c[$k] ?? $def));
$statuses = $statuses ?? ic_statuses();
$types    = $types ?? ic_company_types();
$cats     = $cats ?? ic_categories();
$selTypes = $selTypes ?? [];
$selCats  = $selCats ?? [];
$curRole  = (string) ($c['company_role'] ?? 'buyer');
$curStatus = (int) ($c['status_id'] ?? 0);
$users = $users ?? [];
$curUser = (int) ($c['prepared_by_user_id'] ?? 0);
?>
<div class="form-grid-2">
<div class="card">
    <div class="card-header"><h2>Firma Bilgileri</h2></div>
    <div class="card-body">
        <div class="form-group"><label for="company_name">Firma adı *</label><input type="text" id="company_name" name="company_name" value="<?= $val('company_name') ?>" required></div>
        <div class="form-row">
            <div class="form-group"><label for="company_role">Rol</label>
                <select id="company_role" name="company_role">
                    <?php foreach (ic_company_roles() as $k => $l): ?><option value="<?= e($k) ?>"<?= $curRole === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label for="status_id">Durum</label>
                <select id="status_id" name="status_id">
                    <option value="">—</option>
                    <?php foreach ($statuses as $s): ?><option value="<?= (int) $s['id'] ?>"<?= $curStatus === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-group"><label>Şirket Tipleri (çok seçim)</label>
            <div class="check-grid">
                <?php foreach ($types as $t): ?><label class="check-inline"><input type="checkbox" name="company_type_id[]" value="<?= (int) $t['id'] ?>"<?= in_array((int) $t['id'], $selTypes, true) ? ' checked' : '' ?>> <?= e($t['name']) ?></label><?php endforeach; ?>
            </div>
        </div>
        <div class="form-group"><label>Kategoriler (çok seçim)</label>
            <div class="check-grid">
                <?php foreach ($cats as $cat): ?><label class="check-inline"><input type="checkbox" name="category_id_multi[]" value="<?= (int) $cat['id'] ?>"<?= in_array((int) $cat['id'], $selCats, true) ? ' checked' : '' ?>> <?= e(ic_category_label((int) $cat['id'], $cats)) ?></label><?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>İletişim & Adres</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="country">Ülke</label><input type="text" id="country" name="country" value="<?= $val('country') ?>"></div>
            <div class="form-group"><label for="city">Şehir</label><input type="text" id="city" name="city" value="<?= $val('city') ?>"></div>
        </div>
        <div class="form-group"><label for="address">Adres</label><textarea id="address" name="address" rows="2"><?= $val('address') ?></textarea></div>
        <div class="form-row">
            <div class="form-group"><label for="email">E-posta</label><input type="email" id="email" name="email" value="<?= $val('email') ?>"></div>
            <div class="form-group"><label for="website">Web sitesi</label><input type="text" id="website" name="website" value="<?= $val('website') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="phone">Telefon</label><input type="text" id="phone" name="phone" value="<?= $val('phone') ?>" inputmode="tel"></div>
            <div class="form-group"><label for="whatsapp">WhatsApp</label><input type="text" id="whatsapp" name="whatsapp" value="<?= $val('whatsapp') ?>" inputmode="tel"></div>
        </div>
        <label class="check-inline"><input type="checkbox" name="contact_permission" value="1"<?= (int) ($c['contact_permission'] ?? 1) === 1 ? ' checked' : '' ?>> İletişim izni var</label>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Ticari & Kaynak</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group"><label for="tax_no">Vergi / Kayıt No</label><input type="text" id="tax_no" name="tax_no" value="<?= $val('tax_no') ?>"></div>
            <div class="form-group"><label for="currency">Para Birimi</label><input type="text" id="currency" name="currency" value="<?= $val('currency') ?>" placeholder="USD, EUR…"></div>
            <div class="form-group"><label for="language">Dil</label><input type="text" id="language" name="language" value="<?= $val('language') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="data_source">Veri Kaynağı</label><input type="text" id="data_source" name="data_source" value="<?= $val('data_source') ?>" placeholder="Fuar, web, referans…"></div>
            <div class="form-group"><label for="event_name">Fuar / Etkinlik Adı</label><input type="text" id="event_name" name="event_name" value="<?= $val('event_name') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="relationship_status">İlişki Durumu</label><input type="text" id="relationship_status" name="relationship_status" value="<?= $val('relationship_status') ?>" placeholder="Yeni, aktif, eski…"></div>
            <div class="form-group"><label for="communication_status">İletişim Durumu</label><input type="text" id="communication_status" name="communication_status" value="<?= $val('communication_status') ?>" placeholder="Görüşüldü, bekliyor…"></div>
        </div>
        <?php if ($users): ?>
        <div class="form-group"><label for="prepared_by_user_id">Kaydı Hazırlayan</label>
            <select id="prepared_by_user_id" name="prepared_by_user_id">
                <option value="">—</option>
                <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $curUser === (int) $u['id'] ? ' selected' : '' ?>><?= e((string) ($u['name'] ?? $u['username'] ?? ('#' . $u['id']))) ?></option><?php endforeach; ?>
            </select></div>
        <?php endif; ?>
        <div class="form-group"><label for="notes">Notlar / Açıklama</label><textarea id="notes" name="notes" rows="4"><?= $val('notes') ?></textarea><div class="field-hint">Açıklama tam-metin aranabilir.</div></div>
    </div>
</div>
</div>
