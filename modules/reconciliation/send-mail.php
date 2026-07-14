<?php
declare(strict_types=1);

/**
 * modules/reconciliation/send-mail.php
 * Mutabakatı e-posta ile gönderir (departman hesabı + SMTP profili + PDF ek).
 * Gönderim modalından POST edilir; her gönderim document_email_logs'a yazılır.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation_document.php';
require_once __DIR__ . '/../../includes/customers.php';
require_once __DIR__ . '/../../includes/email_system.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../classes/MailService.php';
require_once __DIR__ . '/../../classes/DocumentService.php';

auth_boot();
require_permission('reconciliation.mail');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/reconciliation/index.php'); }
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$r  = get_reconciliation($id);
if (!$r) { flash('error', 'Mutabakat bulunamadı.'); redirect('modules/reconciliation/index.php'); }

$back = 'modules/reconciliation/view.php?id=' . $id;
$op   = (string) ($_POST['op'] ?? 'send');

// Yeniden gönderimde önceki logun alanlarını kullan.
$resendOf = null;
if ($op === 'resend') {
    $log = document_email_log_get((int) ($_POST['log_id'] ?? 0));
    if (!$log || (string) $log['document_type'] !== 'reconciliation' || (int) $log['document_id'] !== $id) {
        flash('error', 'Yeniden gönderilecek kayıt bulunamadı.');
        redirect($back);
    }
    $to        = (string) $log['to_email'];
    $cc        = (string) $log['cc'];
    $bcc       = (string) $log['bcc'];
    $subject   = (string) $log['subject'];
    $message   = (string) $log['body'];
    $attachPdf = (int) $log['has_pdf'] === 1;
    $resendOf  = (int) $log['id'];
} else {
    $to        = trim((string) ($_POST['to'] ?? ''));
    $cc        = trim((string) ($_POST['cc'] ?? ''));
    $bcc       = trim((string) ($_POST['bcc'] ?? ''));
    $subject   = trim((string) ($_POST['subject'] ?? ''));
    $message   = (string) ($_POST['message'] ?? '');
    $attachPdf = !empty($_POST['attach_pdf']);
}

if (!is_valid_email($to)) {
    flash('error', 'Geçerli bir alıcı e-posta adresi girin.');
    redirect($back);
}

// PDF ekini üret (güvenli klasöre) — istenmişse.
$attachments = [];
$pdfRel = '';
if ($attachPdf) {
    try {
        $stored = DocumentService::pdfToStorage(recon_document_inner_html($r), [
            'type' => 'mutabakat', 'number' => (string) $r['recon_no'], 'party' => (string) $r['customer_name'],
            'title' => 'Mutabakat ' . (string) $r['recon_no'],
        ]);
        $attachments[] = ['path' => $stored['abs'], 'name' => $stored['friendly']];
        $pdfRel = $stored['rel'];
    } catch (Throwable $e) {
        log_error('reconciliation pdf attach: ' . $e->getMessage());
        flash('error', 'PDF eki oluşturulamadı; e-posta gönderilmedi.');
        redirect($back);
    }
}

// Düz metin mesajı güvenli HTML gövdeye çevir (satır sonları korunur).
$htmlBody = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;line-height:1.5">'
          . nl2br(e($message)) . '</div>';

$res = MailService::sendDocument([
    'module_key'    => 'reconciliation',
    'document_type' => 'reconciliation',
    'document_id'   => $id,
    'document_no'   => (string) $r['recon_no'],
    'to'            => $to,
    'cc'            => $cc,
    'bcc'           => $bcc,
    'subject'       => $subject !== '' ? $subject : ((string) $r['recon_no'] . ' — Cari Mutabakat'),
    'body'          => $htmlBody,
    'html'          => true,
    'attachments'   => $attachments,
    'pdf_rel'       => $pdfRel,
    'sender_user'   => mail_session_user(),
    'resend_of_id'  => $resendOf,
]);

log_activity('reconciliation_mail', 'reconciliation', $id, (string) $r['recon_no'],
    $res['ok'] ? 'success' : 'failed',
    $res['ok'] ? 'Mutabakat maili gönderildi' : ($res['error'] ?? 'Mail hatası'));

flash($res['ok'] ? 'success' : 'error', $res['msg']);
redirect($back);
