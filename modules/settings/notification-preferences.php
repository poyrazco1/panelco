<?php
declare(strict_types=1);

/**
 * modules/settings/notification-preferences.php — Kişisel bildirim tercihleri (§21).
 * Her kullanıcı KENDİ lead takip bildirim tercihlerini yönetir (kişiseldir).
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead_reminders.php';
require_once __DIR__ . '/../../includes/lead_notifications.php';

auth_boot();
require_login();
// Bildirim tercihleri kişiseldir; giriş yapan herkes kendi tercihini yönetebilir.
// 'leads.notif_settings' yetkisi rol matrisinde ayrıca atanabilir (§21).
if (!can_followup_notif_settings()) { require_permission('leads.notif_settings'); }

$uid = (int) (current_user_id() ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    lead_notif_prefs_save($uid, [
        'lead_followup_enabled' => $_POST['lead_followup_enabled'] ?? 0,
        'sound_enabled'         => $_POST['sound_enabled'] ?? 0,
        'dashboard_enabled'     => $_POST['dashboard_enabled'] ?? 0,
        'upcoming_enabled'      => $_POST['upcoming_enabled'] ?? 0,
        'overdue_enabled'       => $_POST['overdue_enabled'] ?? 0,
        'remind_before_minutes' => $_POST['remind_before_minutes'] ?? 15,
        'daily_digest'          => $_POST['daily_digest'] ?? 0,
        'show_pending_on_login' => $_POST['show_pending_on_login'] ?? 0,
    ]);
    flash('success', 'Bildirim tercihleriniz kaydedildi.');
    http_response_code(303);
    redirect('modules/settings/notification-preferences.php');
}

$p = lead_notif_prefs($uid);
$chk = static fn(string $k): string => !empty($p[$k]) ? ' checked' : '';

layout_top('Bildirim Tercihleri', 'settings');
?>
<div class="page-head">
    <h1 class="page-title">Bildirim Tercihleri</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div>
</div>
<?= render_flashes() ?>
<p class="muted" style="max-width:720px">Bu ayarlar yalnızca sizi ilgilendirir (kişiseldir). Lead takip bildirimlerinin nasıl geleceğini buradan yönetirsiniz.</p>

<form method="post" action="<?= e(url('modules/settings/notification-preferences.php')) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:720px"><div class="card-header"><h2>Lead Takip Bildirimleri</h2></div><div class="card-body">
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="lead_followup_enabled" value="1"<?= $chk('lead_followup_enabled') ?>> Lead takip bildirimleri aktif</label>
            <div class="field-hint">Kapatırsanız yaklaşan/geciken takip bildirimi almazsınız.</div></div>
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="upcoming_enabled" value="1"<?= $chk('upcoming_enabled') ?>> Yaklaşan görev bildirimi</label></div>
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="overdue_enabled" value="1"<?= $chk('overdue_enabled') ?>> Geciken görev bildirimi</label></div>
        <div class="form-group"><label for="remind_before_minutes">Bildirim kaç dakika önce gelsin (varsayılan)</label>
            <select id="remind_before_minutes" name="remind_before_minutes" style="max-width:220px">
                <?php foreach (lead_remind_before_options() as $mk => $ml): ?><option value="<?= (int) $mk ?>"<?= (int) $p['remind_before_minutes'] === (int) $mk ? ' selected' : '' ?>><?= e($ml) ?></option><?php endforeach; ?>
            </select>
            <div class="field-hint">Takip oluştururken ayrıca kayıt bazlı bildirim zamanı seçebilirsiniz.</div></div>
    </div></div>

    <div class="card" style="max-width:720px"><div class="card-header"><h2>Gösterim & Ses</h2></div><div class="card-body">
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="dashboard_enabled" value="1"<?= $chk('dashboard_enabled') ?>> Dashboard'da lead takip kartını göster</label></div>
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="sound_enabled" value="1"<?= $chk('sound_enabled') ?>> Yeni bildirimde ses çal</label></div>
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="show_pending_on_login" value="1"<?= $chk('show_pending_on_login') ?>> Girişte tamamlanmamış takipleri özet olarak göster</label></div>
        <div class="form-group"><label class="checkbox"><input type="checkbox" name="daily_digest" value="1"<?= $chk('daily_digest') ?>> Günlük takip özeti göster</label></div>
    </div></div>

    <div class="form-actions" style="max-width:720px"><button type="submit" class="btn btn-primary">Kaydet</button></div>
</form>
<?php layout_bottom();
