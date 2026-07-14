<?php
declare(strict_types=1);

/**
 * modules/documents/send.php — Herhangi bir belge türünü e-posta ile gönderir
 * (departman hesabı + SMTP profili + PDF ek). Onay/token YOKTUR (mutabakata özgü).
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/document_registry.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../classes/MailService.php';
require_once __DIR__ . '/../../classes/DocumentService.php';

auth_boot();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('dashboard.php'); }
csrf_check();

$type = (string) ($_POST['type'] ?? '');
$id   = (int) ($_POST['id'] ?? 0);
$m    = document_type_meta($type);
if (!$m) { flash('error', 'Bilinmeyen belge türü.'); redirect('dashboard.php'); }

require_permission($m['module'] . '.mail');

$row = document_type_load($type, $id);
if (!$row) { flash('error', 'Belge bulunamadı.'); redirect($m['index']); }

// Güvenli geri dönüş yolu (yalnızca modules/ altındaki göreli yollara izin ver).
$back = (string) ($_POST['back'] ?? '');
if ($back === '' || !preg_match('#^modules/[A-Za-z0-9_./?=&-]+$#', $back)) { $back = $m['index']; }

$op = (string) ($_POST['op'] ?? 'send');
$resendOf = null;
if ($op === 'resend') {
    $log = document_email_log_get((int) ($_POST['log_id'] ?? 0));
    if (!$log || (string) $log['document_type'] !== $type || (int) $log['document_id'] !== $id) {
        flash('error', 'Yeniden gönderilecek kayıt bulunamadı.');
        redirect($back);
    }
    $to = (string) $log['to_email']; $cc = (string) $log['cc']; $bcc = (string) $log['bcc'];
    $subject = (string) $log['subject']; $message = (string) $log['body']; $attachPdf = (int) $log['has_pdf'] === 1;
    $resendOf = (int) $log['id'];
} else {
    $to = trim((string) ($_POST['to'] ?? '')); $cc = trim((string) ($_POST['cc'] ?? '')); $bcc = trim((string) ($_POST['bcc'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? '')); $message = (string) ($_POST['message'] ?? '');
    $attachPdf = !empty($_POST['attach_pdf']);
}

if (!is_valid_email($to)) { flash('error', 'Geçerli bir alıcı e-posta adresi girin.'); redirect($back); }

// Ek kopya seçenekleri (yalnızca yeni gönderimde).
if ($op !== 'resend') {
    $actorEmail = mail_session_user()['email'];
    if (!empty($_POST['copy_self']) && $actorEmail !== '') { $cc = trim($cc . ',' . $actorEmail, ', '); }
    if (!empty($_POST['copy_dept'])) {
        $deptAcc  = dept_account_for_module((string) $m['module']);
        $depEmail = trim((string) ($deptAcc['from_email'] ?? ''));
        if ($depEmail !== '') { $cc = trim($cc . ',' . $depEmail, ', '); }
    }
}

$no = (string) ($m['no'])($row);

$attachments = []; $pdfRel = '';
if ($attachPdf) {
    try {
        $stored = DocumentService::pdfToStorage(document_type_inner($type, $row), [
            'type' => (string) $m['label'], 'number' => $no, 'party' => (string) ($m['party'])($row),
            'title' => (string) $m['label'] . ' ' . $no, 'orientation' => (string) ($m['orientation'] ?? 'portrait'),
        ]);
        $attachments[] = ['path' => $stored['abs'], 'name' => $stored['friendly']];
        $pdfRel = $stored['rel'];
    } catch (Throwable $e) {
        log_error('documents pdf attach [' . $type . ']: ' . $e->getMessage());
        flash('error', 'PDF eki oluşturulamadı; e-posta gönderilmedi.');
        redirect($back);
    }
}

// Kullanılmayan {{degisken}} yer tutucularını temizle.
$message = (string) preg_replace('/\{\{[a-zA-Z_]+\}\}/', '', $message);
$htmlBody = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;line-height:1.5">'
          . nl2br(e($message)) . '</div>';

$res = MailService::sendDocument([
    'module_key'    => (string) $m['module'],
    'document_type' => $type,
    'document_id'   => $id,
    'document_no'   => $no,
    'to'            => $to,
    'cc'            => $cc,
    'bcc'           => $bcc,
    'subject'       => $subject !== '' ? $subject : ((string) $m['label'] . ' ' . $no),
    'body'          => $htmlBody,
    'html'          => true,
    'attachments'   => $attachments,
    'pdf_rel'       => $pdfRel,
    'sender_user'   => mail_session_user(),
    'resend_of_id'  => $resendOf,
]);

if ($pdfRel !== '' && isset($stored)) {
    document_file_record($type, $id, $stored['rel'], $stored['friendly'], 'application/pdf',
        (int) @filesize($stored['abs']), current_user_id(), $res['log_id'] ?? null);
}

log_activity($m['module'] . '_mail', $m['module'], $id, $no,
    $res['ok'] ? 'success' : 'failed', $res['ok'] ? 'Belge maili gönderildi' : ($res['error'] ?? 'Mail hatası'));

if ($res['ok']) {
    flash('success', 'E-posta gönderildi. Alıcı: ' . ($res['to'] ?? $to)
        . ' · Gönderen: ' . ($res['from'] ?? '') . ' · Reply-To: ' . ($res['reply_to'] ?? ''));
} else {
    flash('error', $res['msg']);
}
redirect($back);
