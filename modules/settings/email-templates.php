<?php
declare(strict_types=1);

/**
 * modules/settings/email-templates.php
 * Belge e-postaları için düzenlenebilir şablonlar ({{degisken}} destekli).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/email_system.php';

auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'save') {
        $res = email_template_save($_POST, current_user_id());
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Şablon kaydedildi.' : implode(' ', $res['errors']));
    } elseif ($op === 'delete') {
        email_template_delete((int) ($_POST['id'] ?? 0));
        flash('success', 'Şablon silindi.');
    }
    http_response_code(303);
    redirect('modules/settings/email-templates.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit   = $editId > 0 ? email_template_get($editId) : null;
$templates = email_templates_all();
$modules   = all_modules();
$vars      = email_template_variables();

layout_top('E-Posta Şablonları', 'settings');
?>
<div class="page-head">
    <h1 class="page-title">E-Posta Şablonları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2><?= $edit ? 'Şablonu Düzenle' : 'Yeni Şablon' ?></h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('modules/settings/email-templates.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                <div class="form-row">
                    <div class="form-group"><label for="t-name">Şablon adı</label>
                        <input type="text" id="t-name" name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" required placeholder="ör. Mutabakat Bildirimi"></div>
                    <div class="form-group"><label for="t-mod">Modül</label>
                        <select id="t-mod" name="module_key">
                            <option value="">— Genel —</option>
                            <?php foreach ($modules as $mk => $mlabel): ?>
                                <option value="<?= e($mk) ?>"<?= ($edit['module_key'] ?? '') === $mk ? ' selected' : '' ?>><?= e($mlabel) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>

                <div class="form-group"><label for="t-sub">Konu</label>
                    <input type="text" id="t-sub" name="subject" value="<?= e((string) ($edit['subject'] ?? '')) ?>" required placeholder="{{belge_turu}} {{belge_no}} — {{sirket_adi}}"></div>

                <div class="form-group"><label for="t-body">Mesaj</label>
                    <textarea id="t-body" name="body" rows="12" placeholder="Sayın {{yetkili_adi}}, ..."><?= e((string) ($edit['body'] ?? '')) ?></textarea></div>

                <div class="form-check"><label><input type="checkbox" name="is_active" <?= !$edit || (int) ($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Aktif</label></div>
                <div class="form-check"><label><input type="checkbox" name="is_default" <?= $edit && (int) ($edit['is_default'] ?? 0) === 1 ? 'checked' : '' ?>> Bu modül için varsayılan</label></div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Güncelle' : 'Ekle' ?></button>
                    <?php if ($edit): ?><a class="btn" href="<?= e(url('modules/settings/email-templates.php')) ?>">Vazgeç</a><?php endif; ?>
                </div>
            </form>

            <div class="field-hint" style="margin-top:14px">
                <strong>Kullanılabilir değişkenler:</strong><br>
                <?php foreach ($vars as $v): ?><code>{{<?= e($v) ?>}}</code> <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Tanımlı Şablonlar</h2></div>
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <p class="muted">Henüz şablon yok. Gönderim modalında konu/mesaj elle girilir.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Ad</th><th>Modül</th><th>Durum</th><th style="width:90px">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td><?= e((string) $t['name']) ?><?= (int) $t['is_default'] === 1 ? ' <span class="badge badge-info">Varsayılan</span>' : '' ?></td>
                            <td><?= $t['module_key'] !== '' ? e($modules[$t['module_key']] ?? (string) $t['module_key']) : '<span class="muted">Genel</span>' ?></td>
                            <td><?= (int) $t['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/settings/email-templates.php?edit=' . (int) $t['id'])) ?>" title="Düzenle"><?= icon('pencil') ?></a>
                                    <form method="post" action="<?= e(url('modules/settings/email-templates.php')) ?>" style="display:inline" data-confirm="Bu şablon silinsin mi?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm action-icon-btn is-danger" title="Sil"><?= icon('trash-2') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
layout_bottom();
