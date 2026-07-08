<?php
declare(strict_types=1);
/**
 * modules/forms/_template-form.php — Şablon oluştur/düzenle ortak formu.
 * Beklenen: $tpl (mevcut/boş), $fields (alan dizisi), $categories.
 */
if (!defined('APP_ROOT')) { exit; }
$val = static fn(string $k, $def = '') => e((string) ($tpl[$k] ?? $def));
?>
<div class="card" style="max-width:860px"><div class="card-header"><strong>Form Bilgileri</strong></div><div class="card-body">
    <div class="form-grid">
        <div class="form-group form-col-full"><label for="form_name">Form Adı *</label>
            <input type="text" id="form_name" name="form_name" value="<?= $val('form_name') ?>" required></div>
        <div class="form-group"><label for="category_id">Kategori</label>
            <select id="category_id" name="category_id"><option value="0">— Kategorisiz —</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) ($tpl['category_id'] ?? 0) === (int) $c['id'] ? ' selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="form_type">Form Tipi</label>
            <select id="form_type" name="form_type"><?php foreach (form_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) ($tpl['form_type'] ?? 'internal') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="form-group form-col-full"><label for="description">Açıklama</label>
            <input type="text" id="description" name="description" value="<?= $val('description') ?>"></div>
        <div class="form-group"><label class="check-inline"><input type="checkbox" name="requires_approval" value="1"<?= !empty($tpl['requires_approval']) ? ' checked' : '' ?>> Onay gerekli</label></div>
        <div class="form-group"><label class="check-inline"><input type="checkbox" name="is_active" value="1"<?= (!isset($tpl['is_active']) || (int) $tpl['is_active'] === 1) ? ' checked' : '' ?>> Aktif</label></div>
    </div>
</div></div>

<div class="card" style="max-width:860px"><div class="card-header"><strong>Form Alanları</strong><button type="button" class="btn btn-sm" id="addField"><?= icon('plus') ?>Alan Ekle</button></div>
<div class="card-body">
    <div class="field-editor" id="fieldEditor">
        <?php
        $renderRow = static function (array $fld = []): void {
            $types = form_field_types();
            $type = (string) ($fld['type'] ?? 'text');
            ?>
            <div class="field-row">
                <span class="field-drag" aria-hidden="true">≡</span>
                <div class="field-row-grid">
                    <input type="text" name="f_label[]" value="<?= e((string) ($fld['label'] ?? '')) ?>" placeholder="Etiket">
                    <input type="text" name="f_name[]" value="<?= e((string) ($fld['name'] ?? '')) ?>" placeholder="anahtar (boşsa otomatik)">
                    <select name="f_type[]">
                        <?php foreach ($types as $tk => $tl): ?><option value="<?= e($tk) ?>"<?= $type === $tk ? ' selected' : '' ?>><?= e($tl) ?></option><?php endforeach; ?>
                    </select>
                    <input type="text" name="f_options[]" value="<?= e(implode(', ', (array) ($fld['options'] ?? []))) ?>" placeholder="Seçim: a, b, c">
                    <input type="text" name="f_placeholder[]" value="<?= e((string) ($fld['placeholder'] ?? '')) ?>" placeholder="Placeholder">
                    <input type="text" name="f_help[]" value="<?= e((string) ($fld['help_text'] ?? '')) ?>" placeholder="Yardım metni">
                    <label class="check-inline"><input type="checkbox" name="f_required[]" value="1"<?= !empty($fld['required']) ? ' checked' : '' ?>> Zorunlu</label>
                </div>
                <button type="button" class="btn btn-xs field-remove" title="Kaldır"><?= icon('trash-2', 'icon-xs') ?></button>
            </div>
            <?php
        };
        if ($fields) { foreach ($fields as $fld) { $renderRow($fld); } }
        else { $renderRow(); }
        ?>
    </div>
    <p class="field-hint">Alan tipleri: metin, uzun metin, telefon, e-posta, sayı, tarih, seçim (virgülle), onay kutusu, dosya, gizli. "anahtar" boş bırakılırsa etiketten otomatik üretilir.</p>
</div></div>

<template id="fieldRowTemplate">
    <div class="field-row">
        <span class="field-drag" aria-hidden="true">≡</span>
        <div class="field-row-grid">
            <input type="text" name="f_label[]" placeholder="Etiket">
            <input type="text" name="f_name[]" placeholder="anahtar (boşsa otomatik)">
            <select name="f_type[]"><?php foreach (form_field_types() as $tk => $tl): ?><option value="<?= e($tk) ?>"><?= e($tl) ?></option><?php endforeach; ?></select>
            <input type="text" name="f_options[]" placeholder="Seçim: a, b, c">
            <input type="text" name="f_placeholder[]" placeholder="Placeholder">
            <input type="text" name="f_help[]" placeholder="Yardım metni">
            <label class="check-inline"><input type="checkbox" name="f_required[]" value="1"> Zorunlu</label>
        </div>
        <button type="button" class="btn btn-xs field-remove" title="Kaldır"><?= icon('trash-2', 'icon-xs') ?></button>
    </div>
</template>

<script>
(function(){
    var editor = document.getElementById('fieldEditor');
    var tpl = document.getElementById('fieldRowTemplate');
    document.getElementById('addField').addEventListener('click', function(){
        editor.appendChild(tpl.content.cloneNode(true));
    });
    editor.addEventListener('click', function(e){
        var btn = e.target.closest('.field-remove'); if (!btn) return;
        if (editor.querySelectorAll('.field-row').length > 1) { btn.closest('.field-row').remove(); }
    });
})();
</script>
