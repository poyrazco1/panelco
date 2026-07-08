<?php
declare(strict_types=1);
/** modules/international-customers/message-templates.php — Mesaj şablonları yönetimi. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/message-templates.php';
auth_boot();
require_permission('message_templates.view');

$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $uid = current_user_id();
    if ($action === 'delete') {
        require_permission('message_templates.delete');
        mt_delete((int) ($_POST['id'] ?? 0), $uid);
        flash('success', 'Şablon silindi.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $d = mt_fields_from_input($_POST);
        if ($d['name'] === '' || trim($d['body']) === '') {
            flash('error', 'Ad ve içerik zorunludur.');
        } elseif ($id > 0) {
            require_permission('message_templates.edit');
            mt_update($id, $d, $uid); flash('success', 'Şablon güncellendi.');
        } else {
            require_permission('message_templates.create');
            mt_create($d, $uid); flash('success', 'Şablon eklendi.');
        }
    }
    http_response_code(303);
    redirect('modules/international-customers/message-templates.php');
}

$templates = mt_list();
$edit = $editId ? mt_get($editId) : null;
layout_top('Mesaj Şablonları', 'settings');
?>
<div class="page-head"><h1 class="page-title">Dış Ticaret Mesaj Şablonları</h1><div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>">← Genel Ayarlar</a></div></div>
<?= render_flashes() ?>

<div class="ic-view-grid">
  <div class="ic-view-main">
    <div class="card"><div class="card-header"><strong>Şablonlar</strong></div><div class="card-body">
        <?php if ($templates): ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Ad</th><th>Tür</th><th>Kanal</th><th>Aktif</th><th></th></tr></thead><tbody>
        <?php foreach ($templates as $t): ?>
            <tr><td><strong><?= e($t['name']) ?></strong></td><td><?= e(mt_type_label((string) $t['template_type'])) ?></td>
                <td><span class="badge badge-info"><?= $t['channel'] === 'whatsapp' ? 'WhatsApp' : 'Mail' ?></span></td>
                <td><?= (int) $t['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                <td class="nowrap">
                    <?php if (can('message_templates.edit')): ?><a class="btn btn-xs" href="<?= e(url('modules/international-customers/message-templates.php?edit=' . (int) $t['id'])) ?>"><?= icon('pencil', 'icon-xs') ?></a><?php endif; ?>
                    <?php if (can('message_templates.delete')): ?><form method="post" style="display:inline" onsubmit="return confirm('Silinsin mi?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-xs"><?= icon('trash-2', 'icon-xs') ?></button></form><?php endif; ?>
                </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?><div class="empty empty-compact">Şablon yok.</div><?php endif; ?>
    </div></div>
  </div>
  <div class="ic-view-side">
    <?php if (can('message_templates.create') || can('message_templates.edit')): ?>
    <div class="card"><div class="card-header"><strong><?= $edit ? 'Şablonu Düzenle' : 'Yeni Şablon' ?></strong></div><div class="card-body">
        <form method="post" action="<?= e(url('modules/international-customers/message-templates.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="form-group"><label>Ad *</label><input type="text" name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" required></div>
            <div class="form-group"><label>Tür</label><select name="template_type"><?php foreach (mt_types() as $k => $l): ?><option value="<?= e($k) ?>"<?= ($edit['template_type'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Kanal</label><select name="channel">
                <option value="mail"<?= ($edit['channel'] ?? 'mail') === 'mail' ? ' selected' : '' ?>>Mail</option>
                <option value="whatsapp"<?= ($edit['channel'] ?? '') === 'whatsapp' ? ' selected' : '' ?>>WhatsApp</option>
            </select></div>
            <div class="form-group"><label>Konu (mail)</label><input type="text" name="subject" value="<?= e((string) ($edit['subject'] ?? '')) ?>"></div>
            <div class="form-group"><label>İçerik *</label><textarea name="body" rows="8" required><?= e((string) ($edit['body'] ?? '')) ?></textarea></div>
            <label class="check-inline"><input type="checkbox" name="is_active" value="1"<?= (int) ($edit['is_active'] ?? 1) === 1 ? ' checked' : '' ?>> Aktif</label>
            <div class="form-actions"><button class="btn btn-primary btn-sm"><?= $edit ? 'Güncelle' : 'Ekle' ?></button><?php if ($edit): ?><a class="btn btn-sm" href="<?= e(url('modules/international-customers/message-templates.php')) ?>">İptal</a><?php endif; ?></div>
        </form>
    </div></div>
    <?php endif; ?>
    <div class="card"><div class="card-header"><strong>Değişkenler</strong></div><div class="card-body">
        <ul class="def-list">
        <?php foreach (mt_variables() as $v => $desc): ?><li><code><?= e($v) ?></code> <span class="muted small"><?= e($desc) ?></span></li><?php endforeach; ?>
        </ul>
        <p class="muted small mt">Otomatik toplu gönderim yoktur. Mail kullanıcı onayıyla gönderilir; WhatsApp yalnızca tıklanabilir link üretir.</p>
    </div></div>
  </div>
</div>
<?php layout_bottom();
