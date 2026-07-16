<?php
declare(strict_types=1);
/**
 * modules/maintenance/_comm-panel.php
 * view.php içine dahil edilir. Beklenen değişkenler: $r, $id, $actUrl, $ret.
 * E-posta önizle/şablon değiştir/düzenleyerek gönder + WhatsApp hazırla/aç/işaretle
 * + gönderim geçmişi (§6, §7).
 */
require_once __DIR__ . '/../../includes/service_maintenance_comm.php';

$emailConsent = (int) ($r['email_consent'] ?? 0) === 1;
$waConsent    = (int) ($r['whatsapp_consent'] ?? 0) === 1;
$toEmail      = trim((string) ($r['email'] ?? ''));

// E-posta şablonları (varsayılan + tanımlılar), değişkenler doldurulmuş.
$emailTpls = [];
$defE = smaint_template_default('email');
$emailTpls[] = ['name' => $defE['name'], 'subject' => smaint_msg_render((string) $defE['subject'], $r), 'body' => smaint_msg_render((string) $defE['body'], $r)];
foreach (smaint_templates('email') as $t) {
    if ((int) $t['is_active'] !== 1) { continue; }
    $emailTpls[] = ['name' => (string) $t['name'], 'subject' => smaint_msg_render((string) $t['subject'], $r), 'body' => smaint_msg_render((string) ($t['body'] ?? ''), $r)];
}
$curSubject = $emailTpls[0]['subject'] ?? '';
$curBody    = $emailTpls[0]['body'] ?? '';

// WhatsApp mesajı
$defW = smaint_template_default('whatsapp');
$waMsg = smaint_msg_render((string) $defW['body'], $r);
$waDigits = preg_replace('/\D+/', '', (string) ($r['whatsapp'] ?: $r['phone'] ?? ''));
if (strlen((string) $waDigits) === 10 && ($waDigits[0] ?? '') === '5') { $waDigits = '90' . $waDigits; }
elseif (strlen((string) $waDigits) === 11 && ($waDigits[0] ?? '') === '0') { $waDigits = '90' . substr($waDigits, 1); }

$comms = smaint_comms_for_reminder($id);
?>
<div class="card">
    <div class="card-header"><h2><?= icon('mail') ?> E-posta Hatırlatma</h2></div>
    <div class="card-body">
        <?php if ($toEmail === ''): ?>
            <div class="empty">Bu kayıtta müşteri e-posta adresi bulunmuyor.</div>
        <?php else: ?>
            <?php if (!$emailConsent): ?>
                <div class="alert alert-error" style="margin-bottom:8px">Bu müşterinin e-posta iletişim izni bulunmuyor. Manuel gönderim audit loga kaydedilir.</div>
            <?php endif; ?>
            <?php if (can_maint_email()): ?>
            <form method="post" action="<?= e($actUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <input type="hidden" name="return" value="<?= e($ret) ?>">
                <input type="hidden" name="action" value="email_send">
                <div class="form-group"><label>Alıcı</label><input type="text" value="<?= e($toEmail) ?>" disabled></div>
                <?php if (count($emailTpls) > 1): ?>
                <div class="form-group"><label for="tplSel">Şablon değiştir</label>
                    <select id="tplSel"><?php foreach ($emailTpls as $i => $t): ?><option value="<?= $i ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select>
                </div>
                <?php endif; ?>
                <div class="form-group"><label for="mailSubject">Konu</label><input type="text" id="mailSubject" name="subject" value="<?= e($curSubject) ?>" required></div>
                <div class="form-group"><label for="mailBody">Mesaj (önizleme / düzenle)</label><textarea id="mailBody" name="body" rows="10" required><?= e($curBody) ?></textarea></div>
                <input type="hidden" name="template_name" value="<?= e($emailTpls[0]['name'] ?? '') ?>">
                <button class="btn btn-sm btn-primary" type="submit"><?= icon('send') ?>Düzenleyerek Gönder</button>
            </form>
            <script>
            (function(){
                var tpls = <?= json_encode($emailTpls, JSON_UNESCAPED_UNICODE) ?>;
                var sel = document.getElementById('tplSel');
                if (sel) sel.addEventListener('change', function(){
                    var t = tpls[parseInt(sel.value,10)] || {};
                    document.getElementById('mailSubject').value = t.subject || '';
                    document.getElementById('mailBody').value = t.body || '';
                    var tn = document.querySelector('input[name="template_name"]'); if (tn) tn.value = t.name || '';
                });
            })();
            </script>
            <?php else: ?>
                <div class="text-muted">E-posta gönderme yetkiniz yok.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= icon('message-circle') ?> WhatsApp Hatırlatma</h2></div>
    <div class="card-body">
        <?php if ((string) $waDigits === ''): ?>
            <div class="empty">Bu kayıtta telefon/WhatsApp numarası bulunmuyor.</div>
        <?php else: ?>
            <?php if (!$waConsent): ?>
                <div class="alert alert-error" style="margin-bottom:8px">Bu müşterinin WhatsApp iletişim izni bulunmuyor. Manuel işlem audit loga kaydedilir.</div>
            <?php endif; ?>
            <div class="form-group"><label for="waBody">WhatsApp mesajı (düzenlenebilir)</label><textarea id="waBody" rows="6"><?= e($waMsg) ?></textarea></div>
            <p class="text-muted" style="margin:4px 0">WhatsApp ekranının açılması "gönderildi" anlamına gelmez. Gönderdikten sonra <strong>Gönderildi Olarak İşaretle</strong> yapın.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <button type="button" class="btn btn-sm btn-wa" id="waOpenBtn"><?= icon('message-circle') ?>WhatsApp'ı Aç</button>
                <?php if (can_maint_whatsapp()): ?>
                <form method="post" action="<?= e($actUrl) ?>" id="waSentForm" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="return" value="<?= e($ret) ?>">
                    <input type="hidden" name="action" value="wa_sent">
                    <input type="hidden" name="body" id="waSentBody" value="<?= e($waMsg) ?>">
                    <button class="btn btn-sm" type="submit"><?= icon('check-circle') ?>Gönderildi Olarak İşaretle</button>
                </form>
                <form method="post" action="<?= e($actUrl) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="return" value="<?= e($ret) ?>">
                    <input type="hidden" name="action" value="customer_replied">
                    <button class="btn btn-sm" type="submit">Müşteri Cevap Verdi</button>
                </form>
                <?php endif; ?>
            </div>
            <script>
            (function(){
                var digits = <?= json_encode((string) $waDigits) ?>;
                var ta = document.getElementById('waBody');
                var open = document.getElementById('waOpenBtn');
                var sentBody = document.getElementById('waSentBody');
                if (open) open.addEventListener('click', function(){
                    var msg = ta ? ta.value : '';
                    if (sentBody) sentBody.value = msg;
                    window.open('https://wa.me/' + digits + '?text=' + encodeURIComponent(msg), '_blank', 'noopener');
                });
                if (ta && sentBody) ta.addEventListener('input', function(){ sentBody.value = ta.value; });
            })();
            </script>
        <?php endif; ?>
    </div>
</div>

<?php if ($comms): ?>
<div class="card">
    <div class="card-header"><h2>Gönderim Geçmişi</h2></div>
    <div class="card-body">
        <table class="table">
            <thead><tr><th>Tarih</th><th>Kanal</th><th>Alıcı</th><th>Konu/Mesaj</th><th>Kullanıcı</th><th>Durum</th></tr></thead>
            <tbody>
            <?php foreach ($comms as $c):
                $st = (string) $c['status'];
                $cls = $st === 'sent' ? 'badge-success' : ($st === 'failed' ? 'badge-danger' : 'badge-muted');
            ?>
                <tr>
                    <td class="nowrap"><?= e(fmt_date((string) $c['created_at'])) ?></td>
                    <td><?= e(smaint_channels()[(string) $c['channel']] ?? (string) $c['channel']) ?></td>
                    <td class="nowrap"><?= e((string) ($c['to_email'] ?: $c['to_phone'] ?: '—')) ?></td>
                    <td class="wrap"><?= e(mb_substr((string) ($c['subject'] ?: $c['body'] ?? ''), 0, 80)) ?><?= (int) (($c['customer_reply'] ?? '') === 'replied') === 1 ? ' <span class="badge badge-info">Yanıtlandı</span>' : '' ?></td>
                    <td><?= e((string) ($c['user_name'] ?? $c['sent_by_name'] ?? '—')) ?: '—' ?></td>
                    <td><span class="badge <?= $cls ?>"><?= e($st) ?></span><?php if ($st === 'failed' && !empty($c['error_message'])): ?><br><span class="text-muted" title="<?= e((string) $c['error_message']) ?>"><?= e(mb_substr((string) $c['error_message'], 0, 40)) ?></span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
