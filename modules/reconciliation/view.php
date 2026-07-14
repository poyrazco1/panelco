<?php
declare(strict_types=1);

/** modules/reconciliation/view.php — Mutabakat detayı + çıktı/e-posta aksiyonları. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/customers.php';
require_once __DIR__ . '/../../includes/forms.php';
require_once __DIR__ . '/../../includes/email_system.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../classes/DocumentTokenService.php';

auth_boot();
require_permission('reconciliation.view');

$id = (int) ($_GET['id'] ?? 0);
$r  = get_reconciliation($id);
if (!$r) { flash('error', 'Mutabakat bulunamadı.'); redirect('modules/reconciliation/index.php'); }

// Onay iptali / güvenli bağlantı iptali (yetkiye bağlı).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'cancel_approval' && can('reconciliation.approval_cancel')) {
        try { db()->prepare("UPDATE reconciliations SET agreement = 'pending' WHERE id = ?")->execute([$id]); }
        catch (Throwable $e) { log_error('recon cancel: ' . $e->getMessage()); }
        log_activity('reconciliation_approval_cancel', 'reconciliation', $id, (string) $r['recon_no'], 'success', 'Onay iptal edildi');
        flash('success', 'Mutabakat onayı iptal edildi (durum: beklemede).');
    } elseif ($op === 'revoke_links' && can('reconciliation.token_renew')) {
        DocumentTokenService::revokeAll('reconciliation', $id);
        log_activity('reconciliation_token_revoke', 'reconciliation', $id, (string) $r['recon_no'], 'success', 'Güvenli bağlantılar iptal edildi');
        flash('success', 'Mevcut güvenli onay bağlantıları iptal edildi. Yeni gönderimde yeni bağlantı üretilir.');
    }
    http_response_code(303);
    redirect('modules/reconciliation/view.php?id=' . $id);
}

$sym = quote_currency_symbol((string) $r['currency']);
$cid = (int) ($r['customer_id'] ?? 0);
$cust = $cid > 0 ? get_customer_by_id($cid) : null;

// Gönderim modalı için ön dolgu (departman hesabı + şablon).
$actor = mail_session_user();
$dept  = dept_account_for_module('reconciliation');
$toDefault  = trim((string) ($cust['email'] ?? ''));
$ccDefault  = (string) ($dept['default_cc'] ?? '');
$bccDefault = (string) ($dept['default_bcc'] ?? '');

$vars = [
    'firma_unvani'   => (string) ($cust['company_name'] ?? $r['customer_name']),
    'musteri_adi'    => (string) $r['customer_name'],
    'yetkili_adi'    => (string) ($r['authorized_name'] ?? ($cust['contact_name'] ?? '')),
    'belge_turu'     => 'Mutabakat',
    'belge_no'       => (string) $r['recon_no'],
    'belge_tarihi'   => (string) ($r['recon_date'] ?? ''),
    'donem_baslangic'=> (string) ($r['period_start'] ?? ($r['period'] ?? '')),
    'donem_bitis'    => (string) ($r['period_end'] ?? ''),
    'borc'           => fmt_money((float) $r['debit']),
    'alacak'         => fmt_money((float) $r['credit']),
    'bakiye'         => fmt_money((float) $r['balance']),
    'para_birimi'    => $sym,
    'personel_adi'   => $actor['full_name'] ?: $actor['username'],
    'personel_email' => $actor['email'],
    'departman'      => (string) ($dept['department_name'] ?? ''),
    // belge_linki / onay_linki BİLEREK verilmez: şablonda {{onay_linki}} olarak kalır,
    // gönderim anında (send-mail.php) güvenli token URL'i ile değiştirilir.
    'sirket_adi'     => function_exists('pub_brand_name') ? pub_brand_name() : (string) $r['customer_name'],
    'sirket_telefon' => (string) pub_setting('company_phone', ''),
    'sirket_email'   => (string) pub_setting('company_email', ''),
];

$tpl = email_template_for_module('reconciliation');
if ($tpl) {
    $subjectDefault = email_template_render((string) $tpl['subject'], $vars);
    $bodyDefault    = email_template_render((string) ($tpl['body'] ?? ''), $vars);
} else {
    $subjectDefault = (string) $r['recon_no'] . ' — Cari Mutabakat';
    $bodyDefault    = recon_text_summary($r);
}

$logs = document_email_logs_for('reconciliation', $id);

layout_top('Mutabakat ' . $r['recon_no'], 'reconciliation');
$row = static fn(string $l, ?string $v): string => trim((string) $v) !== '' ? '<div class="dl-row"><span class="dl-k">' . e($l) . '</span><span class="dl-v">' . e((string) $v) . '</span></div>' : '';
?>
<div class="page-head"><h1 class="page-title">Mutabakat · <?= e((string) $r['recon_no']) ?></h1><div class="page-actions">
    <a class="btn btn-sm" href="<?= e(url('modules/reconciliation/index.php')) ?>"><?= icon('chevron-left') ?>Mutabakat</a>
    <?php if (can('reconciliation.print')): ?><a class="btn btn-sm" href="<?= e(url('modules/reconciliation/print.php?id=' . $id)) ?>" target="_blank"><?= icon('printer') ?>Yazdır</a><?php endif; ?>
    <?php if (can('reconciliation.pdf')): ?><a class="btn btn-sm" href="<?= e(url('modules/reconciliation/pdf.php?id=' . $id . '&inline=1')) ?>" target="_blank"><?= icon('eye') ?>Önizle</a><a class="btn btn-sm" href="<?= e(url('modules/reconciliation/pdf.php?id=' . $id)) ?>"><?= icon('file-down') ?>PDF indir</a><?php endif; ?>
    <?php if (can('reconciliation.mail')): ?><button type="button" class="btn btn-primary btn-sm" data-open-send><?= icon('mail') ?>E-posta gönder</button><?php endif; ?>
    <?php if (can('reconciliation.edit')): ?><a class="btn btn-sm" href="<?= e(url('modules/reconciliation/edit.php?id=' . $id)) ?>"><?= icon('pencil') ?>Düzenle</a><?php endif; ?>
    <?php if (can('reconciliation.approval_cancel') && (string) $r['agreement'] !== 'pending'): ?>
        <form method="post" style="display:inline" data-confirm="Müşteri onayı iptal edilip durum 'beklemede'ye alınsın mı?">
            <?= csrf_field() ?><input type="hidden" name="op" value="cancel_approval">
            <button type="submit" class="btn btn-sm">Onayı iptal et</button>
        </form>
    <?php endif; ?>
    <?php if (can('reconciliation.token_renew')): ?>
        <form method="post" style="display:inline" data-confirm="Mevcut güvenli onay bağlantıları iptal edilsin mi? (Eski linkler çalışmaz.)">
            <?= csrf_field() ?><input type="hidden" name="op" value="revoke_links">
            <button type="submit" class="btn btn-sm">Bağlantıları iptal et</button>
        </form>
    <?php endif; ?>
</div></div>
<?= render_flashes() ?>

<div class="card" style="max-width:820px"><div class="card-header"><h2><?= e((string) $r['customer_name']) ?></h2>
    <span class="badge <?= e(recon_agreement_class((string) $r['agreement'])) ?>"><?= e(recon_agreement_label((string) $r['agreement'])) ?></span></div>
    <div class="card-body"><div class="dl">
        <?= $row('Cari kod', $r['cari_code']) ?>
        <?= $row('Tür', recon_type_label((string) ($r['recon_type'] ?? 'cari'))) ?>
        <?= $row('Dönem', $r['period']) ?>
        <?= $row('Dönem başlangıç', $r['period_start'] ?? '') ?>
        <?= $row('Dönem bitiş', $r['period_end'] ?? '') ?>
        <?= $row('Tarih', $r['recon_date']) ?>
        <?= $row('Borç', fmt_money((float) $r['debit']) . ' ' . $sym) ?>
        <?= $row('Alacak', fmt_money((float) $r['credit']) . ' ' . $sym) ?>
        <?= $row('Bakiye', fmt_money((float) $r['balance']) . ' ' . $sym) ?>
        <?= $row('Yetkili', $r['authorized_name']) ?>
        <?= $row('Açıklama', $r['description']) ?>
        <?= $row('Ek not', $r['extra_note'] ?? '') ?>
    </div></div>
</div>

<?php if (can('reconciliation.mail')): ?>
<div class="card" style="max-width:820px"><div class="card-header"><h2>Gönderim Geçmişi</h2></div><div class="card-body">
    <?php if (empty($logs)): ?>
        <p class="muted">Bu mutabakat için henüz e-posta gönderilmemiş.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tarih</th><th>Alıcı</th><th>Gönderen</th><th>PDF</th><th>Durum</th><th style="width:70px">İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= e(date('d.m.Y H:i', strtotime((string) $l['created_at']))) ?></td>
                    <td><?= e((string) $l['to_email']) ?></td>
                    <td><?= e((string) ($l['sent_by_name'] ?: '—')) ?></td>
                    <td><?= (int) $l['has_pdf'] === 1 ? '<a class="badge badge-info" href="' . e(url('modules/documents/file.php?log=' . (int) $l['id'])) . '" title="PDF indir">Ek ⬇</a>' : '<span class="muted">—</span>' ?></td>
                    <td><?= $l['status'] === 'sent'
                            ? '<span class="badge badge-success">Gönderildi</span>'
                            : '<span class="badge badge-danger" title="' . e((string) ($l['error_message'] ?? '')) . '">Başarısız</span>' ?></td>
                    <td>
                        <form method="post" action="<?= e(url('modules/reconciliation/send-mail.php')) ?>" style="display:inline" data-confirm="Aynı alıcıya yeniden gönderilsin mi?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="resend">
                            <input type="hidden" name="id" value="<?= (int) $id ?>">
                            <input type="hidden" name="log_id" value="<?= (int) $l['id'] ?>">
                            <button type="submit" class="btn btn-sm action-icon-btn" title="Yeniden gönder"><?= icon('refresh-cw') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div></div>

<dialog id="sendDialog" class="send-dialog">
    <form method="post" action="<?= e(url('modules/reconciliation/send-mail.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="send">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="send-dialog-head">
            <h2>E-posta Gönder — <?= e((string) $r['recon_no']) ?></h2>
            <button type="button" class="btn btn-sm action-icon-btn" data-close-send title="Kapat"><?= icon('x') ?></button>
        </div>
        <div class="send-dialog-body">
            <?php if ($toDefault === ''): ?><div class="alert alert-info">Müşteri kartında e-posta yok; alıcıyı elle girin.</div><?php endif; ?>
            <div class="form-group"><label for="s-to">Alıcı</label>
                <input type="email" id="s-to" name="to" value="<?= e($toDefault) ?>" required></div>
            <div class="form-row">
                <div class="form-group"><label for="s-cc">CC</label>
                    <input type="text" id="s-cc" name="cc" value="<?= e($ccDefault) ?>" placeholder="virgülle ayırın"></div>
                <div class="form-group"><label for="s-bcc">BCC</label>
                    <input type="text" id="s-bcc" name="bcc" value="<?= e($bccDefault) ?>" placeholder="virgülle ayırın"></div>
            </div>
            <div class="form-group"><label for="s-sub">Konu</label>
                <input type="text" id="s-sub" name="subject" value="<?= e($subjectDefault) ?>" required></div>
            <div class="form-group"><label for="s-msg">Mesaj</label>
                <textarea id="s-msg" name="message" rows="9"><?= e($bodyDefault) ?></textarea></div>
            <div class="form-check"><label><input type="checkbox" name="attach_pdf" checked> PDF ekle</label></div>
            <div class="form-check"><label><input type="checkbox" name="add_link" checked> Güvenli onay bağlantısı ekle</label></div>
            <div class="form-check"><label><input type="checkbox" name="copy_self"> Bana (kullanıcıya) kopya gönder</label></div>
            <div class="form-check"><label><input type="checkbox" name="copy_dept"> Departmana kopya gönder</label></div>
            <?php $fromDisp = trim((string) ($dept['from_email'] ?? '')) ?: 'Varsayılan gönderen'; $replyDisp = trim((string) ($actor['email'] ?? '')) ?: $fromDisp; ?>
            <p class="field-hint">
                <strong>Gönderen (From):</strong> <?= e($fromDisp) ?> · <strong>Reply-To:</strong> <?= e($replyDisp) ?>
                <?php if (!empty($dept['department_name'])): ?> · <strong>Departman:</strong> <?= e((string) $dept['department_name']) ?><?php endif; ?><br>
                Mesajdaki <code>{{onay_linki}}</code> güvenli müşteri onay bağlantısıyla değiştirilir; yoksa mesaj sonuna eklenir. Gönderim geçmişe kaydedilir.</p>
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
    document.querySelectorAll('[data-open-send]').forEach(function (b) {
        b.addEventListener('click', function () { if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open',''); });
    });
    document.querySelectorAll('[data-close-send]').forEach(function (b) {
        b.addEventListener('click', function () { if (typeof dlg.close === 'function') dlg.close(); else dlg.removeAttribute('open'); });
    });
})();
</script>
<?php endif; ?>

<?php layout_bottom();
