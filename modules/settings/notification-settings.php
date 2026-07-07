<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/notifications.php';

auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    notif_settings_save([
        'birthday_enabled'        => isset($_POST['birthday_enabled']),
        'birthday_days_before'    => (int) ($_POST['birthday_days_before'] ?? 7),
        'anniversary_enabled'     => isset($_POST['anniversary_enabled']),
        'anniversary_days_before' => (int) ($_POST['anniversary_days_before'] ?? 7),
        'leave_enabled'           => isset($_POST['leave_enabled']),
        'leave_days_before'       => (int) ($_POST['leave_days_before'] ?? 3),
        'mail_auto'               => isset($_POST['mail_auto']),
        'whatsapp_auto'           => isset($_POST['whatsapp_auto']),
        'show_age'                => isset($_POST['show_age']),
    ]);
    flash('success', 'Bildirim ayarları kaydedildi.');
    http_response_code(303);
    redirect('modules/settings/notification-settings.php');
}

$s = notif_settings();

// Cron token (yoksa üret) — günlük otomatik bildirim endpoint'i için
$cronToken = (string) notif_setting_get('cron_token', '');
if ($cronToken === '') { $cronToken = bin2hex(random_bytes(16)); notif_setting_set('cron_token', $cronToken); }
$cronUrl = url('cron/daily-notifications.php?token=' . $cronToken);

layout_top('İK Bildirim Ayarları', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">İK Bildirim Ayarları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<form method="post" action="<?= e(url('modules/settings/notification-settings.php')) ?>">
    <?= csrf_field() ?>
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header"><h2>Doğum Günü</h2></div>
            <div class="card-body">
                <div class="form-check"><label><input type="checkbox" name="birthday_enabled" <?= $s['birthday_enabled'] ? 'checked' : '' ?>> Doğum günü bildirimi aktif</label></div>
                <div class="form-group"><label for="bd">Kaç gün önceden bildirilsin?</label><input type="number" id="bd" name="birthday_days_before" min="0" max="90" value="<?= (int) $s['birthday_days_before'] ?>"></div>
                <div class="form-check"><label><input type="checkbox" name="show_age" <?= $s['show_age'] ? 'checked' : '' ?>> Yaş bilgisini göster (varsayılan kapalı)</label></div>
                <p class="field-hint">Doğum yılı hiçbir zaman herkese açık gösterilmez; yalnızca gün/ay.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Çalışma Yıl Dönümü</h2></div>
            <div class="card-body">
                <div class="form-check"><label><input type="checkbox" name="anniversary_enabled" <?= $s['anniversary_enabled'] ? 'checked' : '' ?>> Yıl dönümü bildirimi aktif</label></div>
                <div class="form-group"><label for="ad">Kaç gün önceden bildirilsin?</label><input type="number" id="ad" name="anniversary_days_before" min="0" max="90" value="<?= (int) $s['anniversary_days_before'] ?>"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>İzin</h2></div>
            <div class="card-body">
                <div class="form-check"><label><input type="checkbox" name="leave_enabled" <?= $s['leave_enabled'] ? 'checked' : '' ?>> İzin bildirimi aktif</label></div>
                <div class="form-group"><label for="ld">Yaklaşan izin kaç gün önceden bildirilsin?</label><input type="number" id="ld" name="leave_days_before" min="0" max="60" value="<?= (int) $s['leave_days_before'] ?>"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Gönderim</h2></div>
            <div class="card-body">
                <div class="form-check"><label><input type="checkbox" name="mail_auto" <?= $s['mail_auto'] ? 'checked' : '' ?>> Mail otomatik gönderilsin (sunucu SMTP gerektirir)</label></div>
                <div class="form-check"><label><input type="checkbox" name="whatsapp_auto" <?= $s['whatsapp_auto'] ? 'checked' : '' ?>> WhatsApp otomatik gönderim (Business API gerektirir)</label></div>
                <p class="field-hint">Varsayılan: mail ve WhatsApp manuel. WhatsApp resmi API yoksa "WhatsApp ile Kutla" butonu wa.me linki açar.</p>
            </div>
        </div>
    </div>
    <div class="form-actions" style="margin-top:16px"><button type="submit" class="btn btn-primary">Kaydet</button></div>
</form>

<div class="card">
    <div class="card-header"><h2>Otomatik Günlük Bildirim (Cron)</h2></div>
    <div class="card-body">
        <p class="field-hint">Bildirimler kullanıcı panele girdiğinde otomatik üretilir; cron zorunlu değildir. Yine de günlük çalışması için Plesk Zamanlanmış Görev ekleyebilirsiniz:</p>
        <div class="form-group">
            <label>Cron URL (token dahil)</label>
            <input type="text" value="<?= e($cronUrl) ?>" readonly onclick="this.select()">
        </div>
        <p class="field-hint">Plesk (PHP): <code>php <?= e($_SERVER['DOCUMENT_ROOT'] ?? '/httpdocs') ?>/cron/daily-notifications.php <?= e($cronToken) ?></code></p>
        <p class="field-hint">Token gizlidir; paylaşmayın. Sıfırlamak için sunucudan <code>notification_settings.cron_token</code> kaydını silin.</p>
    </div>
</div>

<?php
layout_bottom();
