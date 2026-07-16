<?php
declare(strict_types=1);

/**
 * modules/settings/maintenance-settings.php
 * Ayarlar → Teknik Servis → Bakım Hatırlatmaları (§13).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service_maintenance.php';
require_once __DIR__ . '/../../includes/service_maintenance_comm.php';

auth_boot();
if (!can_maint_settings()) { require_permission('maintenance.settings'); }

$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (smaint_settings_save($_POST, $uid)) {
        log_activity('maintenance_settings_save', 'maintenance', null, null, 'success', 'Bakım hatırlatma ayarları güncellendi.');
        flash('success', 'Bakım hatırlatma ayarları kaydedildi.');
    } else {
        flash('error', 'Ayarlar kaydedilemedi.');
    }
    http_response_code(303);
    redirect('modules/settings/maintenance-settings.php');
}

$s        = smaint_settings();
$users    = smaint_assignable_users();
$periods  = smaint_period_options();
$stages   = smaint_stages();
$emailTpls = smaint_templates('email');
$waTpls    = smaint_templates('whatsapp');
$chk = static fn(string $k): string => !empty($s[$k]) ? ' checked' : '';

layout_top('Bakım Hatırlatma Ayarları', 'settings');
?>
<div class="page-head">
    <h1 class="page-title"><?= icon('wrench') ?> Bakım Hatırlatma Ayarları</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/maintenance-templates.php')) ?>"><?= icon('mail') ?>Mesaj Şablonları</a>
        <a class="btn btn-sm" href="<?= e(url('modules/maintenance/index.php')) ?>"><?= icon('clipboard-list') ?>Bakım Takipleri</a>
    </div>
</div>
<?php render_flashes(); ?>

<form method="post" action="<?= e(url('modules/settings/maintenance-settings.php')) ?>">
    <?= csrf_field() ?>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Genel</h2></div>
        <div class="card-body">
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="is_active" value="1"<?= $chk('is_active') ?>> Bakım takip sistemi aktif</label></div>
            <div class="form-row">
                <div class="form-group"><label for="default_period_key">Varsayılan bakım süresi</label>
                    <select id="default_period_key" name="default_period_key">
                        <?php foreach ($periods as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) $s['default_period_key'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label for="default_period_days">Özel gün sayısı (özel seçilirse)</label>
                    <input type="number" id="default_period_days" name="default_period_days" min="1" value="<?= (int) $s['default_period_days'] ?>">
                </div>
            </div>
            <div class="form-group"><label for="default_assigned_user_id">Varsayılan sorumlu personel</label>
                <select id="default_assigned_user_id" name="default_assigned_user_id">
                    <option value="0">— (servisi alan kullanıcı)</option>
                    <?php foreach ($users as $uidk => $un): ?><option value="<?= (int) $uidk ?>"<?= (int) ($s['default_assigned_user_id'] ?? 0) === $uidk ? ' selected' : '' ?>><?= e($un) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Bildirim &amp; Kanallar</h2></div>
        <div class="card-body">
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="dashboard_enabled" value="1"<?= $chk('dashboard_enabled') ?>> Dashboard bildirimi aktif</label></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="email_enabled" value="1"<?= $chk('email_enabled') ?>> E-posta hatırlatması aktif</label></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="whatsapp_enabled" value="1"<?= $chk('whatsapp_enabled') ?>> WhatsApp hatırlatması aktif</label></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="auto_email_enabled" value="1"<?= $chk('auto_email_enabled') ?>> Otomatik e-posta gönderimi aktif (cron)</label></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="manual_email_approval" value="1"<?= $chk('manual_email_approval') ?>> Manuel e-posta onayı gerekli</label></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="sound_enabled" value="1"<?= $chk('sound_enabled') ?>> Bildirim sesi aktif</label></div>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="consent_required" value="1"<?= $chk('consent_required') ?>> İletişim izni zorunlu (otomatik gönderimlerde)</label></div>
            <hr>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="whatsapp_api_enabled" value="1"<?= $chk('whatsapp_api_enabled') ?>> WhatsApp Business API entegrasyonu tanımlı</label></div>
            <p class="text-muted">Otomatik WhatsApp gönderimi yalnızca resmi WhatsApp Business API tanımlıysa etkin olabilir. Standart <code>wa.me</code> bağlantısı kullanılan sistemde WhatsApp mesajları otomatik gönderilmez; yalnızca kullanıcı kontrollü bağlantı açılır.</p>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Hatırlatma Aşamaları</h2></div>
        <div class="card-body">
            <p class="text-muted" style="margin-top:0">Bakım tarihine göre hangi aşamalarda bildirim üretilsin?</p>
            <?php foreach ($stages as $key => [$label, $offset, $flag]): ?>
                <div class="form-group"><label class="check-inline"><input type="checkbox" name="<?= e($flag) ?>" value="1"<?= $chk($flag) ?>> <?= e($label) ?></label></div>
            <?php endforeach; ?>
            <hr>
            <div class="form-group"><label class="check-inline"><input type="checkbox" name="overdue_reremind" value="1"<?= $chk('overdue_reremind') ?>> Geciken müşteriye tekrar hatırlatma yapılsın</label></div>
            <div class="form-group"><label for="max_reminders">Maksimum hatırlatma sayısı</label><input type="number" id="max_reminders" name="max_reminders" min="0" value="<?= (int) $s['max_reminders'] ?>"></div>
        </div>
    </div>

    <div class="card" style="max-width:820px">
        <div class="card-header"><h2>Varsayılan Şablonlar</h2></div>
        <div class="card-body">
            <div class="form-group"><label for="email_template_id">E-posta şablonu</label>
                <select id="email_template_id" name="email_template_id">
                    <option value="0">Varsayılan (gömülü)</option>
                    <?php foreach ($emailTpls as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) ($s['email_template_id'] ?? 0) === (int) $t['id'] ? ' selected' : '' ?>><?= e((string) $t['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="whatsapp_template_id">WhatsApp şablonu</label>
                <select id="whatsapp_template_id" name="whatsapp_template_id">
                    <option value="0">Varsayılan (gömülü)</option>
                    <?php foreach ($waTpls as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) ($s['whatsapp_template_id'] ?? 0) === (int) $t['id'] ? ' selected' : '' ?>><?= e((string) $t['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <p class="text-muted"><a href="<?= e(url('modules/settings/maintenance-templates.php')) ?>">Şablonları düzenle →</a></p>
        </div>
    </div>

    <div style="max-width:820px"><button type="submit" class="btn btn-primary"><?= icon('check-circle') ?>Kaydet</button></div>
</form>

<?php layout_bottom(); ?>
