<?php
declare(strict_types=1);

/** modules/quotes/send-mail.php — Teklifi müşteriye mail gönder (merkezî mail servisi). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/quotes.php';
require_once __DIR__ . '/../../includes/mail.php';

auth_boot();
require_permission('quotes.mail');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/quotes/index.php'); }
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$q  = get_quote($id);
if (!$q) { flash('error', 'Teklif bulunamadı.'); redirect('modules/quotes/index.php'); }

$to = trim((string) ($q['email'] ?? ''));
if ($to === '') {
    flash('error', 'Bu teklif için kayıtlı müşteri e-postası bulunmuyor.');
    redirect('modules/quotes/view.php?id=' . $id);
}

$cfg = mail_config_status();
if (!$cfg['ok']) {
    flash('error', $cfg['msg']);
    redirect('modules/quotes/view.php?id=' . $id);
}

$subject = $q['quote_no'] . ' — Teklif';
$body = quote_text_summary($q);
$res = mail_send($to, (string) $q['customer_name'], $subject, $body, ['type' => 'quote', 'sender_user' => mail_session_user()]);

log_activity('quote_mail', 'quote', $id, (string) $q['quote_no'], $res['ok'] ? 'success' : 'failed', $res['ok'] ? 'Teklif maili gönderildi' : ($res['error'] ?? 'Mail hatası'));
$res['ok'] ? flash('success', $res['msg']) : flash('error', $res['msg']);
redirect('modules/quotes/view.php?id=' . $id);
