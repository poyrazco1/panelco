<?php
declare(strict_types=1);
/**
 * modules/settings/_personnel-form.php
 * Ortak personel formu (create + edit birlikte kullanır).
 * Beklenen: $formAction, $submitLabel, $isEdit(bool), $p(array), $departments, $statuses, $users.
 */
defined('APP_ROOT') or exit;

$p = $p ?? [];
$val = static function (string $k, $d = '') use ($p) { return e((string) ($p[$k] ?? $d)); };
$isEdit = $isEdit ?? false;
$statuses = $statuses ?? personnel_status_labels();
$departments = $departments ?? personnel_departments();
$users = $users ?? [];
$curStatus = (string) ($p['employment_status'] ?? 'active');
$curUserId = (string) ($p['user_id'] ?? '');
$curDep = (string) ($p['department'] ?? '');
?>
<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" data-lock-on-submit novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>"><?php endif; ?>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Kimlik & Fotoğraf</h2></div>
        <div class="card-body">
            <?php if ($isEdit): ?>
                <div class="form-group">
                    <label>Mevcut fotoğraf</label>
                    <div style="display:flex;align-items:center;gap:12px">
                        <?= personnel_photo_html($p['photo_path'] ?? null, (string) ($p['full_name'] ?? '')) ?>
                        <?php if (!empty($p['photo_path'])): ?>
                            <label class="small" style="display:flex;align-items:center;gap:6px;font-weight:500">
                                <input type="checkbox" name="remove_photo" value="1" style="width:auto"> Fotoğrafı kaldır
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">Ad</label>
                    <input type="text" id="first_name" name="first_name" value="<?= $val('first_name') ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Soyad</label>
                    <input type="text" id="last_name" name="last_name" value="<?= $val('last_name') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="personnel_code">Personel kodu</label>
                    <input type="text" id="personnel_code" name="personnel_code" value="<?= $val('personnel_code') ?>" placeholder="opsiyonel, benzersiz">
                </div>
                <div class="form-group">
                    <label for="photo">Fotoğraf (PNG, JPG, JPEG, WEBP — en fazla 2 MB)</label>
                    <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp">
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Görev & İletişim</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="department">Departman</label>
                    <select id="department" name="department">
                        <option value="">— Seçiniz —</option>
                        <?php
                        $depInList = false;
                        foreach ($departments as $d) {
                            $sel = ($curDep === $d) ? ' selected' : '';
                            if ($curDep === $d) { $depInList = true; }
                            echo '<option value="' . e($d) . '"' . $sel . '>' . e($d) . '</option>';
                        }
                        // Listede olmayan mevcut departman değeri varsa koru
                        if ($curDep !== '' && !$depInList) {
                            echo '<option value="' . e($curDep) . '" selected>' . e($curDep) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="position">Görev / Unvan</label>
                    <input type="text" id="position" name="position" value="<?= $val('position') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Telefon</label>
                    <input type="text" id="phone" name="phone" value="<?= $val('phone') ?>" inputmode="tel">
                </div>
                <div class="form-group">
                    <label for="internal_phone">Dahili</label>
                    <input type="text" id="internal_phone" name="internal_phone" value="<?= $val('internal_phone') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">E-posta</label>
                    <input type="email" id="email" name="email" value="<?= $val('email') ?>">
                </div>
                <div class="form-group">
                    <label for="whatsapp">WhatsApp</label>
                    <input type="text" id="whatsapp" name="whatsapp" value="<?= $val('whatsapp') ?>" inputmode="tel">
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Çalışma Bilgileri</h2></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="hire_date">İşe giriş tarihi</label>
                    <input type="date" id="hire_date" name="hire_date" value="<?= $val('hire_date') ?>">
                </div>
                <div class="form-group">
                    <label for="termination_date">İşten çıkış tarihi</label>
                    <input type="date" id="termination_date" name="termination_date" value="<?= $val('termination_date') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="birth_date">Doğum tarihi</label>
                    <input type="date" id="birth_date" name="birth_date" value="<?= $val('birth_date') ?>">
                    <p class="field-hint">Bildirimlerde yalnızca gün/ay gösterilir; doğum yılı gizlenir.</p>
                </div>
                <div class="form-group">
                    <label>Doğum günü bildirimi</label>
                    <label class="chk" style="margin-top:8px">
                        <input type="checkbox" name="show_birthday_notifications" value="1" <?= (int) ($p['show_birthday_notifications'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
                        Doğum günü bildirimlerinde gösterilsin
                    </label>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="employment_status">Çalışma durumu</label>
                    <select id="employment_status" name="employment_status">
                        <?php foreach ($statuses as $k => $lbl): ?>
                            <option value="<?= e($k) ?>"<?= $curStatus === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="user_id">Panel kullanıcısı (opsiyonel)</label>
                    <select id="user_id" name="user_id">
                        <option value="">— Bağlı değil —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"<?= $curUserId === (string) $u['id'] ? ' selected' : '' ?>>
                                <?= e($u['username']) ?><?= !empty($u['email']) ? ' (' . e($u['email']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint">Bu personeli bir giriş hesabıyla ilişkilendirir.</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="sort_order">Sıra no</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= $val('sort_order', '0') ?>" step="1">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label class="check-item" style="display:flex;align-items:center;gap:8px;max-width:220px">
                        <input type="checkbox" name="is_active" value="1" <?= (int) ($p['is_active'] ?? 1) === 1 ? 'checked' : '' ?> style="width:auto">
                        <span>Kayıt aktif</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Açıklama / not</label>
                <textarea id="description" name="description" rows="2"><?= $val('description') ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions" style="max-width:820px">
        <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
        <a class="btn" href="<?= e(url('modules/settings/personnel.php')) ?>">Vazgeç</a>
    </div>
</form>
