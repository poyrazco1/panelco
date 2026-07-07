<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/notifications.php';

auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        save_notification_template($id, (string) ($_POST['subject'] ?? ''), (string) ($_POST['body'] ?? ''), isset($_POST['is_active']));
        flash('success', 'Şablon kaydedildi.');
    }
    http_response_code(303);
    redirect('modules/settings/notification-templates.php');
}

$templates = get_notification_templates();
$channelLabels = ['whatsapp' => 'WhatsApp', 'email' => 'E-posta', 'panel' => 'Panel'];
$keyLabels = ['birthday' => 'Doğum Günü', 'anniversary' => 'Çalışma Yıl Dönümü'];

layout_top('Kutlama Şablonları', 'settings');
?>

<div class="page-head">
    <h1 class="page-title">Kutlama Şablonları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<div class="card">
    <div class="card-body">
        <p class="field-hint">Kullanılabilir yer tutucular: <code>{personnel_name}</code>, <code>{year}</code> (yıl dönümü), <code>{company_name}</code>, <code>{date}</code>.</p>
    </div>
</div>

<?php if (empty($templates)): ?>
    <div class="card"><div class="card-body"><p class="muted">Şablon bulunamadı. install.sql içe aktarılmış olmalı.</p></div></div>
<?php else: ?>
    <?php foreach ($templates as $t): ?>
        <div class="card">
            <div class="card-header">
                <h2><?= e($keyLabels[$t['template_key']] ?? (string) $t['template_key']) ?> — <?= e($channelLabels[$t['channel']] ?? (string) $t['channel']) ?></h2>
                <?= (int) $t['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(url('modules/settings/notification-templates.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <?php if ($t['channel'] === 'email'): ?>
                        <div class="form-group"><label>Konu</label><input type="text" name="subject" value="<?= e((string) ($t['subject'] ?? '')) ?>"></div>
                    <?php endif; ?>
                    <div class="form-group"><label>Mesaj</label><textarea name="body" rows="4"><?= e((string) $t['body']) ?></textarea></div>
                    <div class="form-check"><label><input type="checkbox" name="is_active" <?= (int) $t['is_active'] === 1 ? 'checked' : '' ?>> Aktif</label></div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary btn-sm">Kaydet</button></div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php
layout_bottom();
