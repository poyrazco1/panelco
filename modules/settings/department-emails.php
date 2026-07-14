<?php
declare(strict_types=1);

/**
 * modules/settings/department-emails.php
 * Departman / modül e-posta hesapları (From, Reply-To kuralı, CC/BCC, SMTP, şablon).
 * From = departman hesabı; Reply-To = kullanıcı / departman / sabit.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/email_system.php';

auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'save') {
        $res = dept_account_save($_POST, current_user_id());
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Departman hesabı kaydedildi.' : implode(' ', $res['errors']));
    } elseif ($op === 'delete') {
        dept_account_delete((int) ($_POST['id'] ?? 0));
        flash('success', 'Departman hesabı silindi.');
    }
    http_response_code(303);
    redirect('modules/settings/department-emails.php');
}

$editId  = (int) ($_GET['edit'] ?? 0);
$edit    = $editId > 0 ? dept_account_get($editId) : null;
$accounts = dept_accounts_all();
$profiles = smtp_profiles_all();
$templates = email_templates_all();
$modules  = all_modules();
$replyModes = ['user' => 'İşlemi yapan kullanıcı', 'department' => 'Departman e-postası', 'fixed' => 'Sabit adres'];

layout_top('Departman E-Postaları', 'settings');
?>
<div class="page-head">
    <h1 class="page-title">Departman E-Postaları</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2><?= $edit ? 'Hesabı Düzenle' : 'Yeni Departman Hesabı' ?></h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('modules/settings/department-emails.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                <div class="form-row">
                    <div class="form-group"><label for="d-dep">Departman adı</label>
                        <input type="text" id="d-dep" name="department_name" value="<?= e((string) ($edit['department_name'] ?? '')) ?>" required placeholder="ör. Muhasebe"></div>
                    <div class="form-group"><label for="d-mod">Modül</label>
                        <select id="d-mod" name="module_key">
                            <option value="">— Genel (modüle bağlı değil) —</option>
                            <?php foreach ($modules as $mk => $mlabel): ?>
                                <option value="<?= e($mk) ?>"<?= ($edit['module_key'] ?? '') === $mk ? ' selected' : '' ?>><?= e($mlabel) ?></option>
                            <?php endforeach; ?>
                        </select><div class="field-hint">Bu modülden gönderilen belgeler bu hesabı kullanır.</div></div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label for="d-fe">Gönderen e-posta (From)</label>
                        <input type="email" id="d-fe" name="from_email" value="<?= e((string) ($edit['from_email'] ?? '')) ?>" required placeholder="mutabakat@firma.com"></div>
                    <div class="form-group"><label for="d-fn">Görünen gönderen adı</label>
                        <input type="text" id="d-fn" name="from_name" value="<?= e((string) ($edit['from_name'] ?? '')) ?>" placeholder="Mutabakat Birimi"></div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label for="d-rm">Reply-To yöntemi</label>
                        <select id="d-rm" name="reply_to_mode">
                            <?php foreach ($replyModes as $k => $lbl): ?>
                                <option value="<?= e($k) ?>"<?= ($edit['reply_to_mode'] ?? 'user') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="d-rf">Sabit Reply-To (yöntem "Sabit" ise)</label>
                        <input type="email" id="d-rf" name="reply_to_fixed" value="<?= e((string) ($edit['reply_to_fixed'] ?? '')) ?>" placeholder="birim@firma.com"></div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label for="d-cc">Varsayılan CC</label>
                        <input type="text" id="d-cc" name="default_cc" value="<?= e((string) ($edit['default_cc'] ?? '')) ?>" placeholder="virgülle ayırın"></div>
                    <div class="form-group"><label for="d-bcc">Varsayılan BCC</label>
                        <input type="text" id="d-bcc" name="default_bcc" value="<?= e((string) ($edit['default_bcc'] ?? '')) ?>" placeholder="virgülle ayırın"></div>
                </div>

                <div class="form-group"><label for="d-fwd">Cevap yönlendirme adresleri (bilgi amaçlı)</label>
                    <input type="text" id="d-fwd" name="forward_to" value="<?= e((string) ($edit['forward_to'] ?? '')) ?>" placeholder="muhasebe@firma.com, yonetici@firma.com"></div>

                <div class="form-row">
                    <div class="form-group"><label for="d-smtp">SMTP profili</label>
                        <select id="d-smtp" name="smtp_profile_id">
                            <option value="">— Varsayılan profil —</option>
                            <?php foreach ($profiles as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"<?= (int) ($edit['smtp_profile_id'] ?? 0) === (int) $p['id'] ? ' selected' : '' ?>><?= e((string) $p['name']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="d-tpl">Varsayılan şablon</label>
                        <select id="d-tpl" name="template_id">
                            <option value="">— Yok —</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"<?= (int) ($edit['template_id'] ?? 0) === (int) $t['id'] ? ' selected' : '' ?>><?= e((string) $t['name']) ?><?= $t['module_key'] !== '' ? ' (' . e((string) $t['module_key']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>

                <div class="form-check"><label><input type="checkbox" name="is_active" <?= !$edit || (int) ($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Aktif</label></div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Güncelle' : 'Ekle' ?></button>
                    <?php if ($edit): ?><a class="btn" href="<?= e(url('modules/settings/department-emails.php')) ?>">Vazgeç</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Tanımlı Hesaplar</h2></div>
        <div class="card-body">
            <?php if (empty($accounts)): ?>
                <p class="muted">Henüz departman hesabı yok. Gönderimlerde varsayılan From/SMTP kullanılır.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Departman</th><th>Modül</th><th>From</th><th>Reply-To</th><th>Durum</th><th style="width:90px">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($accounts as $a): ?>
                        <tr>
                            <td><?= e((string) $a['department_name']) ?></td>
                            <td><?= $a['module_key'] !== '' ? e($modules[$a['module_key']] ?? (string) $a['module_key']) : '<span class="muted">Genel</span>' ?></td>
                            <td><?= e((string) $a['from_email']) ?></td>
                            <td><?= e($replyModes[$a['reply_to_mode']] ?? (string) $a['reply_to_mode']) ?></td>
                            <td><?= (int) $a['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/settings/department-emails.php?edit=' . (int) $a['id'])) ?>" title="Düzenle"><?= icon('pencil') ?></a>
                                    <form method="post" action="<?= e(url('modules/settings/department-emails.php')) ?>" style="display:inline" data-confirm="Bu hesap silinsin mi?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
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
