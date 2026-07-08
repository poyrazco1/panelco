<?php
declare(strict_types=1);

/** modules/password-vault/view.php — Kasa kaydı detayı + güvenli şifre göster (re-auth). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/vault.php';

auth_boot();
require_permission('password_vault.view');

$id = (int) ($_GET['id'] ?? 0);
$v  = get_vault_item($id);
if (!$v) { flash('error', 'Kayıt bulunamadı veya görüntüleme yetkiniz yok.'); redirect('modules/password-vault/index.php'); }

$accessLog = vault_access_log($id, 10);
$historyCount = vault_history_count($id);

layout_top('Kasa: ' . $v['title'], 'password_vault');
$row = static fn(string $l, ?string $vv): string => trim((string) $vv) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $vv) . '</span></div>' : '';
?>
<div class="page-head"><h1 class="page-title"><?= e($v['title']) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/password-vault/index.php')) ?>">← Kasa</a>
    <?php if (can('password_vault.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(url('modules/password-vault/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
</div></div>
<?= render_flashes() ?>

<div class="card" style="max-width:720px"><div class="card-header"><h2>Kayıt Bilgileri</h2>
    <span class="badge badge-info"><?= e(vault_category_label((string) $v['category'])) ?></span></div>
    <div class="card-body">
        <div class="dl">
            <?= $row('Kullanıcı adı', $v['username']) ?>
            <?= $row('URL', $v['url']) ?>
            <?= $row('Sorumlu kişi', $v['responsible_person']) ?>
            <?= $row('Son değiştirme', $v['last_changed_at']) ?>
            <?= $row('Değiştirme periyodu', $v['change_period_days'] ? ($v['change_period_days'] . ' gün') : '') ?>
            <?= $row('Açıklama', $v['description']) ?>
            <div class="dl-row"><span class="dl-k">Geçmiş şifre sayısı</span><span class="dl-v"><?= (int) $historyCount ?></span></div>
        </div>

        <?php
        // Şifre çözme durumunu belirle (düz şifre GÖSTERİLMEZ/loglanmaz; yalnızca durum).
        $decStatus = !empty($v['secret_enc']) ? vault_decrypt_result((string) $v['secret_enc'])['status'] : 'empty';
        ?>
        <?php if (!vault_is_configured()): ?>
            <div class="alert alert-error" style="margin-top:14px">Şifre Kasası güvenlik anahtarı yapılandırılmamış. Şifreler görüntülenemez.</div>
        <?php elseif (!empty($v['secret_enc']) && $decStatus === 'failed'): ?>
            <div class="alert alert-error" style="margin-top:14px">Bu kayıt mevcut VAULT_KEY ile çözülemedi. Anahtar değişmiş veya kayıt bozulmuş olabilir. (Kayıt korunuyor; silinmedi.)</div>
        <?php elseif (can('password_vault.reveal') && !empty($v['secret_enc']) && $decStatus === 'ok'): ?>
        <div class="vault-reveal" style="margin-top:16px">
            <div class="form-row" style="max-width:520px">
                <div class="form-group"><label for="vaultPanelPass">Şifreyi görmek için panel şifrenizi girin</label>
                    <input type="password" id="vaultPanelPass" autocomplete="current-password" placeholder="Panel şifreniz"></div>
                <div class="form-group" style="align-self:flex-end"><button type="button" class="btn btn-sm" id="vaultRevealBtn"><?= icon('eye') ?>Şifreyi Göster</button></div>
            </div>
            <div id="vaultResult" class="ts-results"></div>
        </div>
        <?php elseif (empty($v['secret_enc'])): ?>
            <p class="birthday-note" style="margin-top:12px">Bu kayıtta saklı şifre yok.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="max-width:720px"><div class="card-header"><h2>Görüntüleme Kaydı (son 10)</h2></div><div class="card-body">
    <?php if (!$accessLog): ?><div class="empty">Henüz görüntüleme yok.</div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Kullanıcı</th><th>İşlem</th><th>IP</th><th class="nowrap">Tarih</th></tr></thead>
        <tbody>
        <?php foreach ($accessLog as $a): ?>
            <tr><td><?= e((string) ($a['full_name'] ?? ('#' . (int) $a['user_id']))) ?></td><td><?= e((string) $a['action']) ?></td><td><?= e((string) ($a['ip'] ?? '')) ?></td><td class="nowrap"><?= e(fmt_date((string) $a['created_at'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div></div>

<script>
(function () {
    var btn = document.getElementById('vaultRevealBtn');
    if (!btn) { return; }
    var pass = document.getElementById('vaultPanelPass');
    var result = document.getElementById('vaultResult');
    var csrf = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
    var url = <?= json_encode(url('modules/password-vault/reveal.php'), JSON_UNESCAPED_UNICODE) ?>;
    var id = <?= (int) $id ?>;
    function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
    btn.addEventListener('click', function () {
        if (!pass.value) { result.innerHTML = '<div class="ts-note ts-error">Panel şifrenizi girin.</div>'; return; }
        btn.disabled = true; result.innerHTML = '<div class="ts-note">Doğrulanıyor…</div>';
        var body = 'id=' + id + '&password=' + encodeURIComponent(pass.value) + '&_csrf=' + encodeURIComponent(csrf);
        fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                btn.disabled = false; pass.value = '';
                if (!d.ok) { result.innerHTML = '<div class="ts-note ts-error">' + esc(d.error || 'Görüntülenemedi.') + '</div>'; return; }
                result.innerHTML = '<div class="vault-secret"><code id="vaultSecret">' + esc(d.secret) + '</code> <button type="button" class="btn btn-xs" id="vaultCopy">Kopyala</button> <span class="birthday-note">Bu görüntüleme loglandı.</span></div>';
                var cp = document.getElementById('vaultCopy');
                if (cp) { cp.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(d.secret); cp.textContent = 'Kopyalandı'; }); }
            })
            .catch(function () { btn.disabled = false; result.innerHTML = '<div class="ts-note ts-error">Bağlantı hatası.</div>'; });
    });
})();
</script>
<?php layout_bottom();
