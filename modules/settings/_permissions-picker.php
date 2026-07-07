<?php
/**
 * modules/settings/_permissions-picker.php
 * Rol yetki seçici — modül başlığı + işlem yetkileri (checkbox matrisi).
 * Beklenen değişkenler:
 *   $selected (array)  : seçili yetki anahtarları (kaba veya ince) + 'all'
 *   $isSystem (bool)   : sistem rolü ise matris kilitli, daima tam yetki
 * includes/permissions.php yüklü olmalıdır (module_action_registry, permission_action_labels).
 */
$selected  = isset($selected) && is_array($selected) ? $selected : [];
$isSystem  = !empty($isSystem);
$allChecked = in_array('all', $selected, true);
$registry  = module_action_registry();
$labels    = permission_action_labels();

// Kaba modül anahtarı seçiliyse o modülün tüm işlemlerini seçili say (geriye uyum).
$isChecked = static function (string $mod, string $action) use ($selected): bool {
    return in_array($mod . '.' . $action, $selected, true) || in_array($mod, $selected, true);
};

// Modülleri gruba göre kümele
$groups = [];
foreach ($registry as $mod => $meta) {
    $groups[$meta[1]][$mod] = $meta;
}
?>
<?php if ($isSystem): ?>
    <div class="alert alert-info">Bu bir sistem rolüdür; tam yetkiye sahiptir ve yetkileri değiştirilemez.</div>
<?php else: ?>
<div class="perm-picker" data-perm-picker>
    <label class="perm-all">
        <input type="checkbox" name="perms[]" value="all" data-perm-all <?= $allChecked ? 'checked' : '' ?>>
        <span><strong>Süper Admin — Tüm modüller ve işlemler</strong> (tam yetki)</span>
    </label>

    <div class="perm-groups"<?= $allChecked ? ' hidden' : '' ?> data-perm-body>
        <?php foreach ($groups as $groupName => $mods): ?>
            <div class="perm-group">
                <h4 class="perm-group-title"><?= e($groupName) ?></h4>
                <?php foreach ($mods as $mod => $meta): ?>
                    <div class="perm-module" data-perm-module>
                        <div class="perm-module-head">
                            <span class="perm-module-name"><?= e($meta[0]) ?></span>
                            <span class="perm-module-tools">
                                <button type="button" class="perm-mini" data-perm-select>Tümünü seç</button>
                                <button type="button" class="perm-mini" data-perm-clear>Kaldır</button>
                            </span>
                        </div>
                        <div class="perm-actions">
                            <?php foreach ($meta[2] as $action): ?>
                                <label class="perm-action">
                                    <input type="checkbox" name="perms[]"
                                           value="<?= e($mod . '.' . $action) ?>"
                                           data-perm-cb
                                           <?= $isChecked($mod, $action) ? 'checked' : '' ?>>
                                    <span><?= e($labels[$action] ?? $action) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="field-hint">“Süper Admin” seçiliyse ayrı işlem seçimleri dikkate alınmaz.</div>
</div>

<script>
(function () {
    var picker = document.currentScript.previousElementSibling;
    while (picker && !picker.matches('[data-perm-picker]')) { picker = picker.previousElementSibling; }
    if (!picker) { return; }
    var allBox = picker.querySelector('[data-perm-all]');
    var body   = picker.querySelector('[data-perm-body]');

    function syncAll() {
        if (!allBox || !body) { return; }
        body.hidden = allBox.checked;
    }
    if (allBox) { allBox.addEventListener('change', syncAll); syncAll(); }

    picker.querySelectorAll('[data-perm-module]').forEach(function (mod) {
        var boxes = mod.querySelectorAll('[data-perm-cb]');
        var sel = mod.querySelector('[data-perm-select]');
        var clr = mod.querySelector('[data-perm-clear]');
        if (sel) { sel.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = true; }); }); }
        if (clr) { clr.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = false; }); }); }
    });
})();
</script>
<?php endif; ?>
