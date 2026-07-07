<?php
/** modules/help/_help-form.php — ortak yardım konusu form alanları. Beklenen: $a */
$a = isset($a) && is_array($a) ? $a : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($a[$k] ?? $def));
$cat = (string) ($a['category'] ?? 'dashboard');
$rel = (string) ($a['related_module'] ?? '');
?>
<div class="card" style="max-width:820px"><div class="card-header"><h2>Konu Bilgileri</h2></div><div class="card-body">
    <div class="form-row">
        <div class="form-group"><label for="title">Başlık *</label><input type="text" id="title" name="title" value="<?= $val('title') ?>" required></div>
        <div class="form-group"><label for="category">Kategori</label>
            <select id="category" name="category"><?php foreach (help_categories() as $k => $l): ?><option value="<?= e($k) ?>"<?= $cat === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-group"><label for="short_desc">Kısa açıklama</label><input type="text" id="short_desc" name="short_desc" value="<?= $val('short_desc') ?>"></div>
    <div class="form-group"><label for="content">Detaylı anlatım</label><textarea id="content" name="content" rows="4"><?= $val('content') ?></textarea></div>
    <div class="form-group"><label for="steps">Adım adım kullanım (her satır bir adım)</label><textarea id="steps" name="steps" rows="6"><?= $val('steps') ?></textarea></div>
    <div class="form-row">
        <div class="form-group"><label for="screen_path">Ekran yolu</label><input type="text" id="screen_path" name="screen_path" value="<?= $val('screen_path') ?>" placeholder="Ayarlar → Şirket Bilgileri"></div>
        <div class="form-group"><label for="related_module">İlgili modül</label>
            <select id="related_module" name="related_module"><option value="">— Yok —</option>
                <?php foreach (help_categories() as $k => $l): if (!help_module_url($k)) continue; ?><option value="<?= e($k) ?>"<?= $rel === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="search_keywords">Arama kelimeleri</label><input type="text" id="search_keywords" name="search_keywords" value="<?= $val('search_keywords') ?>" placeholder="teklif olustur fiyat"></div>
        <div class="form-group"><label for="tags">Etiketler (virgülle)</label><input type="text" id="tags" name="tags" value="<?= $val('tags') ?>" placeholder="teklif,pdf"></div>
        <div class="form-group"><label for="sort">Sıralama</label><input type="text" id="sort" name="sort" value="<?= $val('sort', '0') ?>" inputmode="numeric"></div>
    </div>
    <div class="form-check"><label><input type="checkbox" name="is_active" value="1"<?= (int) ($a['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif (kullanıcılara görünür)</label></div>
</div></div>
