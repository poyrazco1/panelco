<?php
declare(strict_types=1);

/** modules/forms/external-link-create.php — Dış form token linki oluştur. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/form-center.php';

auth_boot();
require_permission('forms.external.manage');

$templateId = (int) ($_GET['template_id'] ?? ($_POST['template_id'] ?? 0));
$created = null;

// Yalnızca dış'a açık (external/both) şablonlar
$externalTemplates = form_center_templates(['external_only' => true, 'only_active' => false]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tid = (int) ($_POST['template_id'] ?? 0);
    $tpl = form_center_template_find($tid);
    if (!$tpl || !in_array((string) $tpl['form_type'], ['external', 'both'], true)) {
        flash('error', 'Seçilen form dış kullanıma uygun değil.');
        redirect('modules/forms/external-link-create.php');
    }
    $res = form_center_create_external_token($tid, [
        'title'      => $_POST['title'] ?? '',
        'expires_at' => trim((string) ($_POST['expires_at'] ?? '')),
        'max_uses'   => (int) ($_POST['max_uses'] ?? 0),
    ], current_user_id());
    if (!empty($res['token'])) {
        log_activity('form_external_token', 'form_template', $tid, null, 'success', 'Dış form linki oluşturuldu');
        $created = $res['token'];
    } else {
        flash('error', 'Link oluşturulamadı.');
    }
}

$publicBase = rtrim((defined('BASE_URL') && BASE_URL !== '') ? BASE_URL : '', '/');
$publicUrl = $created ? (url('public-form.php') . '?token=' . $created) : '';

layout_top('Dış Form Linki Oluştur', 'settings');
?>
<div class="page-head"><h1 class="page-title">Dış Form Linki Oluştur</h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/forms/external-links.php')) ?>"><?= icon('link') ?>Dış Linkler</a>
</div></div>
<?= render_flashes() ?>

<?php if ($created): ?>
<div class="card" style="max-width:720px"><div class="card-header"><strong>Link Hazır</strong></div><div class="card-body">
    <p class="muted">Aşağıdaki linki müşteriye gönderin. Login gerektirmez; token ile korunur.</p>
    <div class="vault-secret"><code id="pubLink"><?= e($publicUrl) ?></code> <button type="button" class="btn btn-xs" id="copyLink">Kopyala</button></div>
    <p class="field-hint" style="margin-top:10px"><a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Linki yeni sekmede aç →</a></p>
</div></div>
<script>document.getElementById('copyLink').addEventListener('click',function(){var t=document.getElementById('pubLink').textContent;navigator.clipboard&&navigator.clipboard.writeText(t);this.textContent='Kopyalandı';});</script>
<?php endif; ?>

<?php if (!$externalTemplates): ?>
<div class="alert alert-info">Dış kullanıma açık (Dış / İç+Dış) form şablonu yok. Önce bir şablonu dış tip yapın.</div>
<?php else: ?>
<form method="post" action="<?= e(url('modules/forms/external-link-create.php')) ?>">
    <?= csrf_field() ?>
    <div class="card" style="max-width:720px"><div class="card-body">
        <div class="form-group"><label for="template_id">Form Şablonu *</label>
            <select id="template_id" name="template_id" required>
                <option value="">— Seçin —</option>
                <?php foreach ($externalTemplates as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $templateId === (int) $t['id'] ? ' selected' : '' ?>><?= e((string) $t['form_name']) ?> (<?= e(form_type_label((string) $t['form_type'])) ?>)</option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label for="title">Link Başlığı (opsiyonel)</label><input type="text" id="title" name="title" placeholder="Örn. Trendyol iade formu"></div>
        <div class="form-row">
            <div class="form-group"><label for="expires_at">Son Kullanım (opsiyonel)</label><input type="datetime-local" id="expires_at" name="expires_at"></div>
            <div class="form-group"><label for="max_uses">Maks. Kullanım (0 = sınırsız)</label><input type="number" id="max_uses" name="max_uses" min="0" value="0"></div>
        </div>
    </div></div>
    <div class="form-actions" style="max-width:720px"><button type="submit" class="btn btn-primary"><?= icon('link') ?>Link Oluştur</button></div>
</form>
<?php endif; ?>
<?php layout_bottom();
