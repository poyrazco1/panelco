<?php
declare(strict_types=1);

/** modules/finance/statement-send.php — Cari ekstreyi e-posta ile gönderir (PDF ek). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/finance.php';
require_once __DIR__ . '/../../includes/customers.php';
require_once __DIR__ . '/../../includes/document_builders.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../../classes/MailService.php';
require_once __DIR__ . '/../../classes/DocumentService.php';

auth_boot();
require_permission('finance.mail');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/finance/statement.php'); }
csrf_check();

$cid  = (int) ($_POST['customer'] ?? 0);
$from = trim((string) ($_POST['from'] ?? ''));
$to   = trim((string) ($_POST['to'] ?? ''));
$cust = $cid > 0 ? get_customer_by_id($cid) : null;
$back = 'modules/finance/statement.php?customer=' . $cid . '&from=' . urlencode($from) . '&to=' . urlencode($to);
if (!$cust) { flash('error', 'Müşteri bulunamadı.'); redirect('modules/finance/statement.php'); }

$toEmail = trim((string) ($_POST['to_email'] ?? ''));
if (!is_valid_email($toEmail)) { flash('error', 'Geçerli bir alıcı e-posta adresi girin.'); redirect($back); }

$stmt = fin_statement($cid, $from, $to);
$curr = !empty($stmt['rows']) ? (string) ($stmt['rows'][0]['currency'] ?? 'TRY') : 'TRY';

$attachments = []; $pdfRel = '';
if (!empty($_POST['attach_pdf'])) {
    try {
        $inner = statement_document_inner_html([
            'customer_name' => (string) $cust['company_name'], 'from' => $from, 'to' => $to, 'currency' => $curr, 'stmt' => $stmt,
        ]);
        $stored = DocumentService::pdfToStorage($inner, [
            'type' => 'cari-ekstre', 'number' => (string) $cust['company_name'], 'party' => '',
            'title' => 'Cari Ekstre', 'orientation' => 'landscape',
        ]);
        $attachments[] = ['path' => $stored['abs'], 'name' => $stored['friendly']];
        $pdfRel = $stored['rel'];
    } catch (Throwable $e) {
        log_error('statement pdf attach: ' . $e->getMessage());
        flash('error', 'PDF eki oluşturulamadı; e-posta gönderilmedi.');
        redirect($back);
    }
}

$message  = (string) preg_replace('/\{\{[a-zA-Z_]+\}\}/', '', (string) ($_POST['message'] ?? ''));
$htmlBody = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;line-height:1.5">' . nl2br(e($message)) . '</div>';

$res = MailService::sendDocument([
    'module_key'    => 'finance',
    'document_type' => 'statement',
    'document_id'   => $cid,
    'document_no'   => 'EKS-' . $cid,
    'to'            => $toEmail,
    'cc'            => trim((string) ($_POST['cc'] ?? '')),
    'bcc'           => trim((string) ($_POST['bcc'] ?? '')),
    'subject'       => trim((string) ($_POST['subject'] ?? '')) ?: ('Cari Ekstre · ' . (string) $cust['company_name']),
    'body'          => $htmlBody,
    'html'          => true,
    'attachments'   => $attachments,
    'pdf_rel'       => $pdfRel,
    'sender_user'   => mail_session_user(),
]);

if ($pdfRel !== '' && isset($stored)) {
    document_file_record('statement', $cid, $stored['rel'], $stored['friendly'], 'application/pdf',
        (int) @filesize($stored['abs']), current_user_id(), $res['log_id'] ?? null);
}

log_activity('finance_statement_mail', 'finance', $cid, 'EKS-' . $cid, $res['ok'] ? 'success' : 'failed', $res['ok'] ? 'Cari ekstre maili gönderildi' : ($res['error'] ?? 'Mail hatası'));
flash($res['ok'] ? 'success' : 'error', $res['msg']);
redirect($back);
