<?php
declare(strict_types=1);

/** modules/leads/settings.php — Lead API token + WhatsApp mesaj şablonu. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';

auth_boot();
require_permission('leads.edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'gen_token') {
        leads_generate_api_token();
        log_activity('lead_api_token', 'lead', null, null, 'success', 'Lead API token yenilendi');
        flash('success', 'Yeni API token üretildi.');
    } elseif ($action === 'save_template') {
        app_setting_set('lead_wa_template', trim((string) ($_POST['template'] ?? '')));
        flash('success', 'Şablon kaydedildi.');
    }
    http_response_code(303);
    redirect('modules/leads/settings.php');
}

$token = leads_api_token();
$tpl = lead_wa_template();
$endpoint = url('api/leads.php');

layout_top('Lead API & Şablon', 'leads');
?>
<div class="page-head"><h1 class="page-title">Lead API & Şablon</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/leads/index.php')) ?>">← Lead Yönetimi</a></div></div>
<?= render_flashes() ?>

<div class="card" style="max-width:720px"><div class="card-header"><h2>Chrome Eklentisi API</h2></div><div class="card-body">
    <p class="muted">Chrome eklentisi bu token ile lead kaydedebilir. Token'ı gizli tutun; sızarsa yenileyin. İstekler doğrulanır ve dakikada IP başına sınırlanır (rate limit).</p>
    <div class="form-group"><label>Endpoint (POST)</label><input type="text" value="<?= e($endpoint) ?>" readonly onclick="this.select()"></div>
    <div class="form-group"><label>API Token</label><input type="text" value="<?= e($token !== '' ? $token : '(henüz üretilmedi)') ?>" readonly onclick="this.select()">
        <div class="field-hint">İstek başlığı: <code>Authorization: Bearer &lt;token&gt;</code> veya gövdede <code>token</code> alanı.</div>
    </div>
    <form method="post" action="<?= e(url('modules/leads/settings.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="gen_token">
        <button type="submit" class="btn btn-sm"><?= icon('refresh-cw') ?><?= $token === '' ? 'Token Üret' : 'Token Yenile' ?></button>
    </form>
</div></div>

<div class="card" style="max-width:720px"><div class="card-header"><h2>WhatsApp Mesaj Şablonu</h2></div><div class="card-body">
    <form method="post" action="<?= e(url('modules/leads/settings.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_template">
        <div class="form-group"><label for="template">Şablon</label><textarea id="template" name="template" rows="3"><?= e($tpl) ?></textarea>
            <div class="field-hint">Değişkenler: <code>{firma}</code>, <code>{yetkili}</code>, <code>{sehir}</code>. Toplu gönderim yoktur; her lead için tekil, onaylı link üretilir.</div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
    </form>
</div></div>
<?php layout_bottom();
