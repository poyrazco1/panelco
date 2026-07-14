<?php
declare(strict_types=1);

/**
 * includes/_document_send_ui.php
 * Ortak belge çıktı/gönderim arayüzü (PDF indir + E-posta gönder modalı + gönderim
 * geçmişi). Bir formun detay sayfasından şu değişkenler set edilerek include edilir:
 *   $docType     — belge türü (document_registry anahtarı, ör. 'quote')
 *   $docId       — belge id
 *   $docRow      — belge satırı (yüklenmiş)
 *   $docBackUrl  — (ops.) gönderim sonrası dönülecek göreli yol
 */

require_once __DIR__ . '/document_registry.php';
require_once __DIR__ . '/mail.php';

$__m = document_type_meta($docType);
if ($__m && !empty($docRow) && (can($__m['module'] . '.mail') || can($__m['module'] . '.pdf'))):

    $__no    = (string) ($__m['no'])($docRow);
    $__back  = isset($docBackUrl) && $docBackUrl !== '' ? $docBackUrl : ($__m['index']);
    $__actor = mail_session_user();
    $__dept  = dept_account_for_module((string) $__m['module']);
    $__to    = (string) ($__m['to_email'])($docRow);
    $__cc    = (string) ($__dept['default_cc'] ?? '');
    $__bcc   = (string) ($__dept['default_bcc'] ?? '');
    $__vars  = document_type_vars($docType, $docRow, $__actor, $__dept);
    $__tpl   = email_template_for_module((string) $__m['module']);
    if ($__tpl) {
        $__subject = email_template_render((string) $__tpl['subject'], $__vars);
        $__body    = email_template_render((string) ($__tpl['body'] ?? ''), $__vars);
    } else {
        $__subject = (string) $__m['label'] . ' ' . $__no;
        $__body    = "Sayın " . ($__vars['yetkili_adi'] ?: $__vars['musteri_adi']) . ",\n\n"
                   . $__no . " numaralı " . mb_strtolower((string) $__m['label'], 'UTF-8') . " belgemiz ektedir.\n\n"
                   . "Saygılarımızla,\n" . $__vars['sirket_adi'];
    }
    $__logs = document_email_logs_for($docType, $docId);
?>
<div class="card" style="max-width:820px"><div class="card-header"><h2>Belge Çıktısı & Gönderim</h2></div><div class="card-body">
    <div class="row-actions" style="margin-bottom:6px">
        <?php if (can($__m['module'] . '.pdf')): ?>
            <a class="btn btn-sm" href="<?= e(url('modules/documents/pdf.php?type=' . $docType . '&id=' . $docId)) ?>"><?= icon('file-down') ?>PDF indir</a>
        <?php endif; ?>
        <?php if (can($__m['module'] . '.mail')): ?>
            <button type="button" class="btn btn-primary btn-sm" data-open-send><?= icon('mail') ?>E-posta gönder</button>
        <?php endif; ?>
    </div>

    <?php if (can($__m['module'] . '.mail')): ?>
        <?php if (empty($__logs)): ?>
            <p class="muted" style="margin-top:12px">Bu belge için henüz e-posta gönderilmemiş.</p>
        <?php else: ?>
        <div class="table-wrap" style="margin-top:12px">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Alıcı</th><th>Gönderen</th><th>PDF</th><th>Durum</th><th style="width:70px">İşlem</th></tr></thead>
                <tbody>
                <?php foreach ($__logs as $l): ?>
                    <tr>
                        <td><?= e(date('d.m.Y H:i', strtotime((string) $l['created_at']))) ?></td>
                        <td><?= e((string) $l['to_email']) ?></td>
                        <td><?= e((string) ($l['sent_by_name'] ?: '—')) ?></td>
                        <td><?= (int) $l['has_pdf'] === 1 ? '<span class="badge badge-info">Ek</span>' : '<span class="muted">—</span>' ?></td>
                        <td><?= $l['status'] === 'sent' ? '<span class="badge badge-success">Gönderildi</span>'
                                : '<span class="badge badge-danger" title="' . e((string) ($l['error_message'] ?? '')) . '">Başarısız</span>' ?></td>
                        <td>
                            <form method="post" action="<?= e(url('modules/documents/send.php')) ?>" style="display:inline" data-confirm="Aynı alıcıya yeniden gönderilsin mi?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="op" value="resend">
                                <input type="hidden" name="type" value="<?= e($docType) ?>">
                                <input type="hidden" name="id" value="<?= (int) $docId ?>">
                                <input type="hidden" name="log_id" value="<?= (int) $l['id'] ?>">
                                <input type="hidden" name="back" value="<?= e($__back) ?>">
                                <button type="submit" class="btn btn-sm action-icon-btn" title="Yeniden gönder"><?= icon('refresh-cw') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div></div>

<?php if (can($__m['module'] . '.mail')): ?>
<dialog id="sendDialog" class="send-dialog">
    <form method="post" action="<?= e(url('modules/documents/send.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="send">
        <input type="hidden" name="type" value="<?= e($docType) ?>">
        <input type="hidden" name="id" value="<?= (int) $docId ?>">
        <input type="hidden" name="back" value="<?= e($__back) ?>">
        <div class="send-dialog-head">
            <h2>E-posta Gönder — <?= e($__no) ?></h2>
            <button type="button" class="btn btn-sm action-icon-btn" data-close-send title="Kapat"><?= icon('x') ?></button>
        </div>
        <div class="send-dialog-body">
            <?php if ($__to === ''): ?><div class="alert alert-info">Belgede alıcı e-postası yok; elle girin.</div><?php endif; ?>
            <div class="form-group"><label for="s-to">Alıcı</label>
                <input type="email" id="s-to" name="to" value="<?= e($__to) ?>" required></div>
            <div class="form-row">
                <div class="form-group"><label for="s-cc">CC</label><input type="text" id="s-cc" name="cc" value="<?= e($__cc) ?>" placeholder="virgülle ayırın"></div>
                <div class="form-group"><label for="s-bcc">BCC</label><input type="text" id="s-bcc" name="bcc" value="<?= e($__bcc) ?>" placeholder="virgülle ayırın"></div>
            </div>
            <div class="form-group"><label for="s-sub">Konu</label><input type="text" id="s-sub" name="subject" value="<?= e($__subject) ?>" required></div>
            <div class="form-group"><label for="s-msg">Mesaj</label><textarea id="s-msg" name="message" rows="9"><?= e($__body) ?></textarea></div>
            <div class="form-check"><label><input type="checkbox" name="attach_pdf" checked> PDF ekle</label></div>
            <p class="field-hint">Gönderen departman hesabı ve Reply-To (işlemi yapan kullanıcı) ayarlardan belirlenir. Gönderim, geçmişe kaydedilir.</p>
        </div>
        <div class="send-dialog-foot">
            <button type="submit" class="btn btn-primary"><?= icon('mail') ?>Gönder</button>
            <button type="button" class="btn" data-close-send>Vazgeç</button>
        </div>
    </form>
</dialog>
<style>
.send-dialog { width: min(620px, 94vw); border: 1px solid var(--border, #e2e5ea); border-radius: 12px; padding: 0; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
.send-dialog::backdrop { background: rgba(17,24,39,.45); }
.send-dialog-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--border, #e2e5ea); }
.send-dialog-head h2 { margin: 0; font-size: 16px; }
.send-dialog-body { padding: 16px 18px; max-height: 70vh; overflow-y: auto; }
.send-dialog-foot { display: flex; gap: 8px; padding: 14px 18px; border-top: 1px solid var(--border, #e2e5ea); }
</style>
<script>
(function () {
    var dlg = document.getElementById('sendDialog');
    if (!dlg) return;
    document.querySelectorAll('[data-open-send]').forEach(function (b) { b.addEventListener('click', function () { if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open',''); }); });
    document.querySelectorAll('[data-close-send]').forEach(function (b) { b.addEventListener('click', function () { if (dlg.close) dlg.close(); else dlg.removeAttribute('open'); }); });
})();
</script>
<?php endif; ?>
<?php endif; /* meta && row && perms */ ?>
