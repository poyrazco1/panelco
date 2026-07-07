<?php
declare(strict_types=1);

/** modules/reconciliation/send-mail.php — Mutabakatı müşteriye mail gönder. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/reconciliation.php';
require_once __DIR__ . '/../../includes/customers.php';
require_once __DIR__ . '/../../includes/mail.php';

auth_boot();
require_permission('reconciliation.mail');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/reconciliation/index.php'); }
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$r  = get_reconciliation($id);
if (!$r) { flash('error', 'Mutabakat bulunamadı.'); redirect('modules/reconciliation/index.php'); }

// Alıcı: müşteri kartındaki e-posta.
$to = '';
$cid = (int) ($r['customer_id'] ?? 0);
if ($cid > 0) {
    $c = get_customer_by_id($cid);
    $to = trim((string) ($c['email'] ?? ''));
}
if ($to === '') {
    flash('error', 'Bu mutabakat için müşteri e-postası bulunmuyor.');
    redirect('modules/reconciliation/view.php?id=' . $id);
}

$cfg = mail_config_status();
if (!$cfg['ok']) { flash('error', $cfg['msg']); redirect('modules/reconciliation/view.php?id=' . $id); }

$subject = $r['recon_no'] . ' — Cari Mutabakat';
$body = recon_text_summary($r);
$res = mail_send($to, (string) $r['customer_name'], $subject, $body, ['type' => 'reconciliation', 'sender_user' => mail_session_user()]);

log_activity('reconciliation_mail', 'reconciliation', $id, (string) $r['recon_no'], $res['ok'] ? 'success' : 'failed', $res['ok'] ? 'Mutabakat maili gönderildi' : ($res['error'] ?? 'Mail hatası'));
$res['ok'] ? flash('success', $res['msg']) : flash('error', $res['msg']);
redirect('modules/reconciliation/view.php?id=' . $id);
