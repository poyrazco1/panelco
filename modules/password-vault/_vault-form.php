<?php
/**
 * modules/password-vault/_vault-form.php — ortak kasa form alanları. Beklenen: $v, $users, $isEdit
 */
$v = isset($v) && is_array($v) ? $v : [];
$val = static fn(string $k, string $def = ''): string => e((string) ($v[$k] ?? $def));
$users = $users ?? [];
$cat = (string) ($v['category'] ?? 'other');
$isEdit = !empty($isEdit);
$allowed = json_decode((string) ($v['allowed_user_ids'] ?? ''), true);
$allowed = is_array($allowed) ? array_map('intval', $allowed) : [];
?>
<div class="card" style="max-width:820px"><div class="card-header"><h2>Kayıt Bilgileri</h2></div><div class="card-body">
    <?php if (!vault_is_configured()): ?>
        <div class="alert alert-error">Şifre kasası yapılandırılmamış. Yönetici <code>config.php</code> içinde <code>VAULT_KEY</code> tanımlamalı; aksi halde şifreler kaydedilemez.</div>
    <?php endif; ?>
    <div class="form-row">
        <div class="form-group"><label for="title">Başlık *</label><input type="text" id="title" name="title" value="<?= $val('title') ?>" required></div>
        <div class="form-group"><label for="category">Kategori</label>
            <select id="category" name="category"><?php foreach (vault_categories() as $k => $l): ?><option value="<?= e($k) ?>"<?= $cat === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="username">Kullanıcı adı</label><input type="text" id="username" name="username" value="<?= $val('username') ?>" autocomplete="off"></div>
        <div class="form-group"><label for="secret">Şifre <?= $isEdit ? '(değiştirmek için doldurun)' : '' ?></label>
            <input type="password" id="secret" name="secret" value="" autocomplete="new-password" placeholder="<?= $isEdit ? '•••••••• (boş bırakırsanız değişmez)' : '' ?>">
            <div class="field-hint">Şifre AES-256-GCM ile şifrelenerek saklanır; düz metin tutulmaz.</div>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="url">URL</label><input type="text" id="url" name="url" value="<?= $val('url') ?>" placeholder="https://..."></div>
        <div class="form-group"><label for="responsible_person">Sorumlu kişi</label><input type="text" id="responsible_person" name="responsible_person" value="<?= $val('responsible_person') ?>"></div>
        <div class="form-group"><label for="change_period_days">Değiştirme periyodu (gün)</label><input type="text" id="change_period_days" name="change_period_days" value="<?= $val('change_period_days') ?>" inputmode="numeric" placeholder="90"></div>
    </div>
    <div class="form-group"><label for="description">Açıklama</label><textarea id="description" name="description" rows="2"><?= $val('description') ?></textarea></div>
    <div class="form-group">
        <label for="allowed_user_ids">Yetkili kullanıcılar (boş = tüm kasa yetkilileri)</label>
        <select id="allowed_user_ids" name="allowed_user_ids[]" multiple size="5">
            <?php foreach ($users as $uid => $uname): ?><option value="<?= (int) $uid ?>"<?= in_array((int) $uid, $allowed, true) ? ' selected' : '' ?>><?= e($uname) ?></option><?php endforeach; ?>
        </select>
        <div class="field-hint">Ctrl/Cmd ile çoklu seçim. Seçim yapılırsa yalnızca bu kullanıcılar (ve kaydı oluşturan + süper admin) görür.</div>
    </div>
</div></div>
