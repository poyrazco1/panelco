<?php
declare(strict_types=1);

/**
 * modules/settings/smtp-profiles.php
 * Çoklu SMTP profili yönetimi (Ayarlar → E-Posta Ayarları → SMTP Profilleri).
 * Şifreler vault ile şifreli saklanır; "Bağlantıyı test et" gerçek test maili yollar.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/email_system.php';
require_once __DIR__ . '/../../classes/MailService.php';

auth_boot();
require_permission('settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op  = (string) ($_POST['op'] ?? '');
    $uid = current_user_id();

    if ($op === 'save') {
        $res = smtp_profile_save($_POST, $uid);
        if ($res['ok']) { flash('success', 'SMTP profili kaydedildi.'); }
        else { flash('error', implode(' ', $res['errors'])); }
    } elseif ($op === 'delete') {
        smtp_profile_delete((int) ($_POST['id'] ?? 0));
        flash('success', 'SMTP profili silindi.');
    } elseif ($op === 'test') {
        $p = smtp_profile_get((int) ($_POST['id'] ?? 0));
        $toTest = trim((string) ($_POST['test_email'] ?? ''));
        if ($toTest === '') { $toTest = (string) ($p['test_email'] ?? ''); }
        if (!$p) {
            flash('error', 'Profil bulunamadı.');
        } else {
            $res = MailService::testProfile($p, $toTest);
            flash($res['ok'] ? 'success' : 'error', $res['msg']);
        }
    }
    http_response_code(303);
    redirect('modules/settings/smtp-profiles.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit   = $editId > 0 ? smtp_profile_get($editId) : null;
$profiles = smtp_profiles_all();
$vaultOk  = vault_is_configured();

layout_top('SMTP Profilleri', 'settings');
?>
<div class="page-head">
    <h1 class="page-title">SMTP Profilleri</h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/settings/index.php')) ?>"><?= icon('chevron-left') ?>Genel Ayarlar</a></div>
</div>

<?= render_flashes() ?>

<?php if (!$vaultOk): ?>
    <div class="alert alert-error">Uygulama anahtarı (VAULT_KEY) yapılandırılmamış. SMTP şifreleri güvenli saklanamaz; lütfen config.php içinde VAULT_KEY tanımlayın.</div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h2><?= $edit ? 'Profili Düzenle' : 'Yeni SMTP Profili' ?></h2></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('modules/settings/smtp-profiles.php')) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                <div class="form-group"><label for="p-name">Profil adı</label>
                    <input type="text" id="p-name" name="name" value="<?= e((string) ($edit['name'] ?? '')) ?>" required placeholder="ör. Ana SMTP"></div>

                <div class="form-row">
                    <div class="form-group"><label for="p-host">SMTP host</label>
                        <input type="text" id="p-host" name="host" value="<?= e((string) ($edit['host'] ?? '')) ?>" required placeholder="mail.firma.com"></div>
                    <div class="form-group"><label for="p-port">Port</label>
                        <input type="number" id="p-port" name="port" value="<?= e((string) ($edit['port'] ?? '465')) ?>" min="1" max="65535" required></div>
                </div>

                <div class="form-row">
                    <div class="form-group"><label for="p-enc">Şifreleme</label>
                        <select id="p-enc" name="encryption">
                            <?php foreach (smtp_encryptions() as $k => $lbl): ?>
                                <option value="<?= e($k) ?>"<?= ($edit['encryption'] ?? 'ssl') === $k ? ' selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="p-timeout">Timeout (sn)</label>
                        <input type="number" id="p-timeout" name="timeout" value="<?= e((string) ($edit['timeout'] ?? '20')) ?>" min="1" max="300"></div>
                </div>

                <div class="form-group"><label for="p-user">Kullanıcı adı</label>
                    <input type="text" id="p-user" name="username" value="<?= e((string) ($edit['username'] ?? '')) ?>" autocomplete="off" placeholder="panel@firma.com"></div>

                <div class="form-group"><label for="p-pass">Şifre</label>
                    <input type="password" id="p-pass" name="password" value="" autocomplete="new-password" placeholder="<?= $edit ? '(değiştirmek için yeni şifre girin)' : '' ?>">
                    <div class="field-hint">Şifre uygulama anahtarıyla şifreli saklanır. Düzenlemede boş bırakırsanız mevcut şifre korunur.</div></div>

                <div class="form-row">
                    <div class="form-group"><label for="p-fe">Gönderen e-posta (From)</label>
                        <input type="email" id="p-fe" name="from_email" value="<?= e((string) ($edit['from_email'] ?? '')) ?>" placeholder="panel@firma.com"></div>
                    <div class="form-group"><label for="p-fn">Gönderen adı</label>
                        <input type="text" id="p-fn" name="from_name" value="<?= e((string) ($edit['from_name'] ?? '')) ?>" placeholder="Firma Adı"></div>
                </div>

                <div class="form-group"><label for="p-test">Test e-postası adresi</label>
                    <input type="email" id="p-test" name="test_email" value="<?= e((string) ($edit['test_email'] ?? '')) ?>" placeholder="test@firma.com"></div>

                <div class="form-check"><label><input type="checkbox" name="is_active" <?= !$edit || (int) ($edit['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> Aktif</label></div>
                <div class="form-check"><label><input type="checkbox" name="is_default" <?= $edit && (int) ($edit['is_default'] ?? 0) === 1 ? 'checked' : '' ?>> Varsayılan profil</label></div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?= $edit ? 'Güncelle' : 'Ekle' ?></button>
                    <?php if ($edit): ?><a class="btn" href="<?= e(url('modules/settings/smtp-profiles.php')) ?>">Vazgeç</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2>Tanımlı Profiller</h2></div>
        <div class="card-body">
            <?php if (empty($profiles)): ?>
                <p class="muted">Henüz SMTP profili yok. Belge e-postaları gönderilemez.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Ad</th><th>Host</th><th>Şifreleme</th><th>Durum</th><th style="width:150px">İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($profiles as $p): ?>
                        <tr>
                            <td><?= e((string) $p['name']) ?><?= (int) $p['is_default'] === 1 ? ' <span class="badge badge-info">Varsayılan</span>' : '' ?></td>
                            <td><?= e((string) $p['host']) ?>:<?= (int) $p['port'] ?></td>
                            <td><?= e(smtp_encryptions()[$p['encryption']] ?? (string) $p['encryption']) ?></td>
                            <td><?= (int) $p['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-muted">Pasif</span>' ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn-sm action-icon-btn" href="<?= e(url('modules/settings/smtp-profiles.php?edit=' . (int) $p['id'])) ?>" title="Düzenle"><?= icon('pencil') ?></a>
                                    <form method="post" action="<?= e(url('modules/settings/smtp-profiles.php')) ?>" style="display:inline" title="Test maili gönder">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="test">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm action-icon-btn" title="Bağlantıyı test et"><?= icon('mail') ?></button>
                                    </form>
                                    <form method="post" action="<?= e(url('modules/settings/smtp-profiles.php')) ?>" style="display:inline" data-confirm="Bu SMTP profili silinsin mi?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="op" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm action-icon-btn is-danger" title="Sil"><?= icon('trash-2') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="field-hint" style="margin-top:10px">Test, profilin "Test e-postası adresi" alanına gerçek bir mail gönderir. Sonuç üstte bildirilir (bağlantı, kimlik doğrulama, TLS vb.).</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
layout_bottom();
