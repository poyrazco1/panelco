<?php
declare(strict_types=1);

/** modules/leads/settings.php — Lead API token + WhatsApp mesaj şablonları. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads_crm.php';

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
        // İlk şablon aynı zamanda varsayılan tekil şablon (geriye uyum).
        $names = (array) ($_POST['tpl_name'] ?? []);
        $bodies = (array) ($_POST['tpl_body'] ?? []);
        lead_wa_templates_save($names, $bodies);
        $firstBody = '';
        foreach ($bodies as $i => $b) { if (trim((string) ($names[$i] ?? '')) !== '' && trim((string) $b) !== '') { $firstBody = trim((string) $b); break; } }
        if ($firstBody !== '') { app_setting_set('lead_wa_template', $firstBody); }
        flash('success', 'Şablonlar kaydedildi.');
    }
    http_response_code(303);
    redirect('modules/leads/settings.php');
}

$token = leads_api_token();
$templates = lead_wa_templates();
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

<div class="card" style="max-width:720px"><div class="card-header"><h2>WhatsApp Mesaj Şablonları</h2></div><div class="card-body">
    <form method="post" action="<?= e(url('modules/leads/settings.php')) ?>" id="tplForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_template">
        <div id="tplRows">
            <?php foreach ($templates as $t): ?>
            <div class="form-row tpl-row" style="align-items:flex-start">
                <div class="form-group" style="flex:0 0 200px"><label>Şablon adı</label><input type="text" name="tpl_name[]" value="<?= e((string) $t['name']) ?>"></div>
                <div class="form-group" style="flex:1"><label>Metin</label><textarea name="tpl_body[]" rows="2"><?= e((string) $t['body']) ?></textarea></div>
                <button type="button" class="btn btn-xs" style="margin-top:26px" onclick="this.closest('.tpl-row').remove()"><?= icon('x', 'icon-xs') ?></button>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="field-hint" style="margin:6px 0">Değişkenler: <code>{firma}</code> <code>{yetkili}</code> <code>{sehir}</code> <code>{ilce}</code> <code>{telefon}</code> <code>{web}</code>. İlk şablon varsayılandır. Toplu gönderim yoktur; her lead için tekil, onaylı link üretilir.</div>
        <div style="display:flex;gap:8px">
            <button type="button" class="btn btn-sm" onclick="addTpl()"><?= icon('plus') ?>Şablon Ekle</button>
            <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
        </div>
    </form>
    <script>
    function addTpl(){
        var wrap=document.getElementById('tplRows');
        var d=document.createElement('div'); d.className='form-row tpl-row'; d.style.alignItems='flex-start';
        d.innerHTML='<div class="form-group" style="flex:0 0 200px"><label>Şablon adı</label><input type="text" name="tpl_name[]"></div>'
            +'<div class="form-group" style="flex:1"><label>Metin</label><textarea name="tpl_body[]" rows="2"></textarea></div>'
            +'<button type="button" class="btn btn-xs" style="margin-top:26px" onclick="this.closest(\'.tpl-row\').remove()">×</button>';
        wrap.appendChild(d);
    }
    </script>
</div></div>
<?php layout_bottom();
