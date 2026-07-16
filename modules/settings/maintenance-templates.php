<?php
declare(strict_types=1);

/**
 * modules/settings/maintenance-templates.php
 * Bakım e-posta / WhatsApp mesaj şablonları yönetimi (§6, §7).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service_maintenance.php';
require_once __DIR__ . '/../../includes/service_maintenance_comm.php';

auth_boot();
if (!can_maint_templates()) { require_permission('maintenance.templates'); }

$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = (string) ($_POST['op'] ?? 'save');
    if ($op === 'delete') {
        smaint_template_delete((int) ($_POST['id'] ?? 0), $uid)
            ? flash('success', 'Şablon silindi.')
            : flash('error', 'Şablon silinemedi.');
    } else {
        $res = smaint_template_save($_POST, $uid);
        $res['ok'] ? flash('success', 'Şablon kaydedildi.') : flash('error', $res['error']);
    }
    http_response_code(303);
    redirect('modules/settings/maintenance-templates.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit   = $editId > 0 ? smaint_template_get($editId) : null;
$all    = smaint_templates();
$defE   = smaint_default_email_template();
$defW   = smaint_default_whatsapp_template();

layout_top('Bakım Mesaj Şablonları', 'settings');
?>
<div class="page-head">
    <h1 class="page-title"><?= icon('mail') ?> Bakım Mesaj Şablonları</h1>
    <div class="page-actions">
        <a class="btn btn-sm" href="<?= e(url('modules/settings/maintenance-settings.php')) ?>"><?= icon('wrench') ?>Bakım Ayarları</a>
    </div>
</div>
<?php render_flashes(); ?>

<div class="grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">
  <div class="card">
    <div class="card-header"><h2><?= $edit ? 'Şablonu Düzenle' : 'Yeni Şablon' ?></h2></div>
    <div class="card-body">
        <form method="post" action="<?= e(url('modules/settings/maintenance-templates.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-row">
                <div class="form-group"><label for="channel">Kanal</label>
                    <select id="channel" name="channel" onchange="document.getElementById('subjWrap').style.display=(this.value==='email')?'':'none'">
                        <option value="email"<?= ($edit['channel'] ?? 'email') === 'email' ? ' selected' : '' ?>>E-posta</option>
                        <option value="whatsapp"<?= ($edit['channel'] ?? '') === 'whatsapp' ? ' selected' : '' ?>>WhatsApp</option>
                    </select>
                </div>
                <div class="form-group"><label for="name">Şablon adı</label><input type="text" id="name" name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" required></div>
            </div>
            <div class="form-group" id="subjWrap" style="<?= ($edit['channel'] ?? 'email') === 'email' ? '' : 'display:none' ?>">
                <label for="subject">Konu (e-posta)</label>
                <input type="text" id="subject" name="subject" value="<?= e((string) ($edit['subject'] ?? $defE['subject'])) ?>">
            </div>
            <div class="form-group"><label for="body">Mesaj içeriği</label>
                <textarea id="body" name="body" rows="10" required><?= e((string) ($edit['body'] ?? '')) ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="check-inline"><input type="checkbox" name="is_default" value="1"<?= !empty($edit['is_default']) ? ' checked' : '' ?>> Kanalın varsayılanı</label></div>
                <div class="form-group"><label class="check-inline"><input type="checkbox" name="is_active" value="1"<?= (!isset($edit['is_active']) || (int) $edit['is_active'] === 1) ? ' checked' : '' ?>> Aktif</label></div>
            </div>
            <button type="submit" class="btn btn-primary"><?= icon('check-circle') ?>Kaydet</button>
            <?php if ($edit): ?><a class="btn btn-sm" href="<?= e(url('modules/settings/maintenance-templates.php')) ?>">Vazgeç</a><?php endif; ?>
        </form>
        <div class="card" style="margin-top:12px;background:#fafafa">
            <div class="card-body">
                <strong>Kullanılabilir değişkenler</strong>
                <p class="text-muted" style="margin:6px 0 2px">E-posta:</p>
                <div><?php foreach (smaint_template_variables('email') as $v): ?><code style="margin:2px;display:inline-block"><?= e($v) ?></code> <?php endforeach; ?></div>
                <p class="text-muted" style="margin:8px 0 2px">WhatsApp:</p>
                <div><?php foreach (smaint_template_variables('whatsapp') as $v): ?><code style="margin:2px;display:inline-block"><?= e($v) ?></code> <?php endforeach; ?></div>
            </div>
        </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Tanımlı Şablonlar</h2></div>
    <div class="card-body">
        <?php if (!$all): ?>
            <div class="empty">Henüz özel şablon yok. Gömülü varsayılanlar kullanılıyor.</div>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Kanal</th><th>Ad</th><th>Varsayılan</th><th>Aktif</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($all as $t): ?>
                <tr>
                    <td><?= e(smaint_channels()[(string) $t['channel']] ?? (string) $t['channel']) ?></td>
                    <td class="wrap"><?= e((string) $t['name']) ?></td>
                    <td><?= (int) $t['is_default'] === 1 ? '<span class="badge badge-success">Evet</span>' : '—' ?></td>
                    <td><?= (int) $t['is_active'] === 1 ? 'Evet' : 'Hayır' ?></td>
                    <td class="nowrap">
                        <a class="btn btn-sm" href="<?= e(url('modules/settings/maintenance-templates.php?edit=' . (int) $t['id'])) ?>">Düzenle</a>
                        <form method="post" action="<?= e(url('modules/settings/maintenance-templates.php')) ?>" style="display:inline" onsubmit="return confirm('Şablon silinsin mi?')">
                            <?= csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit"><?= icon('x') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <div class="card" style="margin-top:12px;background:#fafafa"><div class="card-body">
            <strong>Gömülü Varsayılanlar</strong>
            <p class="text-muted" style="margin:6px 0 2px"><b>E-posta konusu:</b> <?= e($defE['subject']) ?></p>
            <details><summary>E-posta gövdesi</summary><pre style="white-space:pre-wrap"><?= e($defE['body']) ?></pre></details>
            <details><summary>WhatsApp mesajı</summary><pre style="white-space:pre-wrap"><?= e($defW) ?></pre></details>
        </div></div>
    </div>
  </div>
</div>

<?php layout_bottom(); ?>
