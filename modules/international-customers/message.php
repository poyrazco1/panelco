<?php
declare(strict_types=1);
/**
 * modules/international-customers/message.php
 * Seçili/filtreli yurtdışı müşterilere ONAYLI mesaj hazırlama.
 *  - Kara liste engellenir, izinsiz müşteri UYARILIR, tekrar gönderim uyarısı.
 *  - Mail: önizleme + test mail + günlük limitli gönderim + loglama.
 *  - WhatsApp: kişi başına tıklanabilir wa.me linki + "gönderildi" işaretleme.
 *  - OTOMATİK ARKA PLAN GÖNDERİMİ YOKTUR.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/international-customers.php';
require_once __DIR__ . '/../../includes/message-templates.php';
require_once __DIR__ . '/../../includes/product-lists.php';
require_once __DIR__ . '/../../includes/mail.php';

auth_boot();
require_permission('international_customers.view');

$canMail = can('international_customers.message_mail');
$canWa   = can('international_customers.message_whatsapp');
if (!$canMail && !$canWa) { require_permission('international_customers.message_mail'); }

$uid = current_user_id();

/* ---- Alıcıları topla (ids[] veya product_list_id bağlamı) ---- */
$ids = array_values(array_unique(array_map('intval', (array) ($_GET['ids'] ?? $_POST['ids'] ?? []))));
$ids = array_filter($ids, static fn($i) => $i > 0);
$productListId = (int) ($_GET['product_list_id'] ?? $_POST['product_list_id'] ?? 0);

if (!$ids) {
    // ids yoksa ve ürün listesi bağlamı da yoksa listeye dön
    if (!$productListId) { flash('error', 'Mesaj için müşteri seçin.'); redirect('modules/international-customers/index.php'); }
}

$recipients = [];
foreach ($ids as $id) { if ($c = ic_get($id)) { $recipients[] = $c; } }

$channel = (string) ($_POST['channel'] ?? $_GET['channel'] ?? ($canMail ? 'mail' : 'whatsapp'));
if ($channel === 'mail' && !$canMail) { $channel = 'whatsapp'; }
if ($channel === 'whatsapp' && !$canWa) { $channel = 'mail'; }

$templateId = (int) ($_POST['template_id'] ?? 0);
$template = $templateId ? mt_get($templateId) : null;
$plId = (int) ($_POST['product_list_id'] ?? $productListId);
$productList = $plId ? pl_get($plId) : null;
$plItems = $productList ? pl_items($plId) : [];
$plText = $productList ? pl_to_text($productList, $plItems) : '';
$pdfLink = $productList ? url('modules/product-lists/print.php?id=' . $plId) : '';

$action = (string) ($_POST['action'] ?? '');
$results = [];
$showPreview = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $renderFor = static function (array $cust) use ($template, $productList, $plText, $pdfLink) {
        $contact = ic_primary_contact((int) $cust['id']);
        $vars = mt_build_vars($cust, $contact, $productList, $plText, $pdfLink);
        $subject = $template ? mt_render((string) ($template['subject'] ?? ''), $vars) : '';
        $body = $template ? mt_render((string) $template['body'], $vars) : $plText;
        return [$contact, $subject, $body];
    };

    if ($action === 'test_mail' && $canMail) {
        $me = mail_session_user();
        $to = (string) ($me['email'] ?? '');
        if ($to === '' || !$recipients) { flash('error', 'Test için geçerli alıcı/örnek müşteri yok (kullanıcı e-postanız tanımlı olmalı).'); }
        else {
            [$contact, $subject, $body] = $renderFor($recipients[0]);
            $res = mail_send($to, (string) ($me['full_name'] ?? 'Test'), '[TEST] ' . $subject, nl2br(e($body)), ['type' => 'intl_test', 'sender_user' => $me]);
            msg_log(['customer_id' => (int) $recipients[0]['id'], 'channel' => 'mail', 'template_id' => $templateId, 'product_list_id' => $plId, 'to_email' => $to, 'subject' => $subject, 'body' => $body, 'status' => 'test', 'note' => 'Test maili'], $uid);
            flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Test maili gönderildi: ' . $to : 'Test maili gönderilemedi: ' . ($res['error'] ?? ''));
        }
        $showPreview = true;
    } elseif ($action === 'send_mail' && $canMail) {
        require_permission('international_customers.message_mail');
        $remaining = msg_mail_remaining();
        $sent = 0; $blocked = 0; $skipped = 0; $failed = 0; $limitHit = false;
        foreach ($recipients as $cust) {
            if (ic_is_blacklisted($cust)) { msg_log(['customer_id' => (int) $cust['id'], 'channel' => 'mail', 'template_id' => $templateId, 'product_list_id' => $plId, 'status' => 'blocked', 'note' => 'Kara liste'], $uid); $blocked++; continue; }
            $to = trim((string) ($cust['email'] ?? ''));
            if ($to === '') { $skipped++; continue; }
            if ($sent >= $remaining) { $limitHit = true; break; }
            [$contact, $subject, $body] = $renderFor($cust);
            $res = mail_send($to, (string) ($cust['company_name'] ?? ''), $subject, nl2br(e($body)), ['type' => 'intl_offer', 'sender_user' => mail_session_user()]);
            msg_log(['customer_id' => (int) $cust['id'], 'channel' => 'mail', 'template_id' => $templateId, 'product_list_id' => $plId, 'to_email' => $to, 'subject' => $subject, 'body' => $body, 'status' => $res['ok'] ? 'sent' : 'failed', 'note' => (int) $cust['contact_permission'] === 0 ? 'İzinsiz müşteri' : null], $uid);
            if ($res['ok']) { $sent++; } else { $failed++; }
            if ($plId) { pl_add_recipient($plId, (int) $cust['id'], 'mail', $res['ok'] ? 'sent' : 'test', $uid); }
        }
        log_activity('intl_message_mail', 'international_customer', null, null, 'success', "Mail gönderimi: $sent gönderildi, $blocked engellendi, $failed hata");
        $msg = "$sent mail gönderildi.";
        if ($blocked) { $msg .= " $blocked kara liste engellendi."; }
        if ($skipped) { $msg .= " $skipped e-postasız atlandı."; }
        if ($failed) { $msg .= " $failed hata."; }
        if ($limitHit) { $msg .= ' Günlük limit doldu, kalanlar gönderilmedi.'; }
        flash($sent > 0 ? 'success' : 'error', $msg);
        redirect('modules/international-customers/message-report.php');
    } elseif ($action === 'mark_whatsapp' && $canWa) {
        $marked = array_values(array_unique(array_map('intval', (array) ($_POST['mark'] ?? []))));
        $n = 0;
        foreach ($marked as $mid) {
            $cust = ic_get($mid);
            if (!$cust || ic_is_blacklisted($cust)) { continue; }
            msg_log(['customer_id' => $mid, 'channel' => 'whatsapp', 'template_id' => $templateId, 'product_list_id' => $plId, 'to_phone' => (string) ($cust['whatsapp'] ?? $cust['phone'] ?? ''), 'status' => 'link', 'note' => 'wa.me link'], $uid);
            if ($plId) { pl_add_recipient($plId, $mid, 'whatsapp', 'link', $uid); }
            $n++;
        }
        flash('success', "$n müşteri WhatsApp gönderimi işaretlendi.");
        $showPreview = true;
    } else {
        $showPreview = true; // preview action
    }
}

$mailTemplates = mt_active_by_channel('mail');
$waTemplates   = mt_active_by_channel('whatsapp');
$productLists  = pl_for_select();

layout_top('Mesaj Hazırla', 'international_customers');
?>
<div class="page-head"><h1 class="page-title">Mesaj Hazırla <span class="muted small">(<?= count($recipients) ?> müşteri)</span></h1>
    <div class="page-actions"><a class="btn btn-sm" href="<?= e(url('modules/international-customers/index.php')) ?>">← Liste</a>
        <a class="btn btn-sm" href="<?= e(url('modules/international-customers/message-report.php')) ?>"><?= icon('file-text') ?>Gönderim Raporu</a></div></div>
<?= render_flashes() ?>

<?php
$blCount = 0; $noPermCount = 0; $dupCount = 0;
foreach ($recipients as $r) {
    if (ic_is_blacklisted($r)) { $blCount++; }
    if ((int) $r['contact_permission'] === 0) { $noPermCount++; }
    if (msg_recently_sent((int) $r['id'], $channel, 24)) { $dupCount++; }
}
?>
<?php if ($blCount): ?><div class="alert alert-error"><?= icon('x-circle') ?><?= (int) $blCount ?> müşteri kara listede — bunlara gönderim yapılmayacak.</div><?php endif; ?>
<?php if ($noPermCount): ?><div class="alert alert-warning"><?= (int) $noPermCount ?> müşterinin iletişim izni yok. Yine de gönderebilirsiniz; log'a "izinsiz" olarak kaydedilir.</div><?php endif; ?>
<?php if ($dupCount): ?><div class="alert alert-warning"><?= (int) $dupCount ?> müşteriye son 24 saatte aynı kanaldan gönderim yapılmış (tekrar gönderim).</div><?php endif; ?>

<form method="post" action="<?= e(url('modules/international-customers/message.php')) ?>">
    <?= csrf_field() ?>
    <?php foreach ($recipients as $r): ?><input type="hidden" name="ids[]" value="<?= (int) $r['id'] ?>"><?php endforeach; ?>
    <div class="card"><div class="card-header"><strong>Mesaj Ayarları</strong>
        <span class="muted small">Günlük mail hakkı: <?= msg_mail_remaining() ?> / <?= msg_daily_mail_limit() ?></span></div><div class="card-body">
        <div class="form-row">
            <div class="form-group"><label>Kanal</label>
                <select name="channel" onchange="this.form.querySelector('[name=action]').value='preview';this.form.submit()">
                    <?php if ($canMail): ?><option value="mail"<?= $channel === 'mail' ? ' selected' : '' ?>>Mail</option><?php endif; ?>
                    <?php if ($canWa): ?><option value="whatsapp"<?= $channel === 'whatsapp' ? ' selected' : '' ?>>WhatsApp (link)</option><?php endif; ?>
                </select></div>
            <div class="form-group"><label>Şablon</label>
                <select name="template_id">
                    <option value="">— Şablon seç —</option>
                    <?php foreach (($channel === 'whatsapp' ? $waTemplates : $mailTemplates) as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $templateId === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Ürün Listesi (opsiyonel)</label>
                <select name="product_list_id">
                    <option value="">— Yok —</option>
                    <?php foreach ($productLists as $pl): ?><option value="<?= (int) $pl['id'] ?>"<?= $plId === (int) $pl['id'] ? ' selected' : '' ?>><?= e($pl['list_no'] . ' · ' . $pl['title']) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <input type="hidden" name="action" value="preview">
        <div class="form-actions">
            <button type="submit" class="btn btn-sm" onclick="this.form.action.value='preview'"><?= icon('eye') ?>Önizle</button>
        </div>
    </div></div>

    <?php if ($showPreview && $recipients):
        $sample = $recipients[0];
        $contact = ic_primary_contact((int) $sample['id']);
        $vars = mt_build_vars($sample, $contact, $productList, $plText, $pdfLink);
        $prevSubject = $template ? mt_render((string) ($template['subject'] ?? ''), $vars) : '';
        $prevBody = $template ? mt_render((string) $template['body'], $vars) : $plText;
    ?>
    <div class="card"><div class="card-header"><strong>Önizleme</strong> <span class="muted small">(<?= e($sample['company_name']) ?>)</span></div><div class="card-body">
        <?php if ($channel === 'mail'): ?><div class="form-group"><label>Konu</label><input type="text" value="<?= e($prevSubject) ?>" readonly></div><?php endif; ?>
        <div class="form-group"><label>İçerik</label><textarea rows="10" readonly style="font-family:monospace;font-size:12px"><?= e($prevBody) ?></textarea></div>
    </div></div>
    <?php endif; ?>

    <?php if ($showPreview && $channel === 'mail' && $canMail): ?>
    <div class="form-actions">
        <button type="submit" class="btn" name="action" value="test_mail"><?= icon('mail') ?>Kendime Test Gönder</button>
        <button type="submit" class="btn btn-primary" name="action" value="send_mail" onclick="return confirm('<?= count($recipients) ?> müşteriye mail gönderilsin mi? Kara listedekiler atlanır.')"><?= icon('send') ?>Mail Gönder</button>
    </div>
    <?php endif; ?>
</form>

<?php if ($showPreview && $channel === 'whatsapp' && $canWa && $recipients): ?>
<form method="post" action="<?= e(url('modules/international-customers/message.php')) ?>">
    <?= csrf_field() ?>
    <?php foreach ($recipients as $r): ?><input type="hidden" name="ids[]" value="<?= (int) $r['id'] ?>"><?php endforeach; ?>
    <input type="hidden" name="channel" value="whatsapp"><input type="hidden" name="template_id" value="<?= $templateId ?>"><input type="hidden" name="product_list_id" value="<?= $plId ?>">
    <div class="card"><div class="card-header"><strong>WhatsApp Linkleri</strong> <span class="muted small">Her müşteri için tıklanabilir wa.me linki. Otomatik gönderim yoktur.</span></div><div class="card-body">
        <div class="table-wrap"><table class="table"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.wachk').forEach(c=>c.checked=this.checked)"></th><th>Firma</th><th>Telefon</th><th>Durum</th><th>Link</th></tr></thead><tbody>
        <?php foreach ($recipients as $cust):
            $bl = ic_is_blacklisted($cust);
            $contact = ic_primary_contact((int) $cust['id']);
            $vars = mt_build_vars($cust, $contact, $productList, $plText, $pdfLink);
            $body = $template ? mt_render((string) $template['body'], $vars) : $plText;
            $phone = trim((string) ($cust['whatsapp'] ?? '')) ?: trim((string) ($cust['phone'] ?? ''));
            $link = (!$bl && $phone !== '') ? build_whatsapp_message_link($phone, $body) : null;
        ?>
            <tr<?= $bl ? ' class="row-muted"' : '' ?>>
                <td><?php if ($link): ?><input type="checkbox" class="wachk" name="mark[]" value="<?= (int) $cust['id'] ?>"><?php endif; ?></td>
                <td><?= e($cust['company_name']) ?><?php if ($bl): ?> <span class="badge badge-danger">Kara Liste</span><?php elseif ((int) $cust['contact_permission'] === 0): ?> <span class="badge badge-muted">İzinsiz</span><?php endif; ?></td>
                <td class="small"><?= e($phone) ?: '—' ?></td>
                <td><?= msg_recently_sent((int) $cust['id'], 'whatsapp', 24) ? '<span class="badge badge-muted">Yakında gönderildi</span>' : '—' ?></td>
                <td><?php if ($link): ?><a class="btn btn-xs btn-primary" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= icon('message-circle', 'icon-xs') ?>WhatsApp Aç</a><?php else: ?><span class="muted small"><?= $bl ? 'Engelli' : 'Telefon yok' ?></span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <div class="form-actions"><button type="submit" class="btn btn-sm" name="action" value="mark_whatsapp"><?= icon('check-circle') ?>Seçilenleri "Gönderildi" İşaretle</button></div>
    </div></div>
</form>
<?php endif; ?>
<?php layout_bottom();
