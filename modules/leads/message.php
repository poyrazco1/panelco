<?php
declare(strict_types=1);
/** modules/leads/message.php — WhatsApp mesajı "gönderildi" işaretle (manuel). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';
auth_boot();
require_permission('leads.whatsapp');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/leads/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$l = get_lead($id);
if (!$l) { flash('error', 'Lead bulunamadı.'); redirect('modules/leads/index.php'); }
if (lead_is_blacklisted($l)) { flash('error', 'Kara listedeki lead için mesaj işaretlenemez.'); redirect('modules/leads/view.php?id=' . $id); }
lead_mark_messaged($id, current_user_id());
log_activity('lead_message', 'lead', $id, null, 'success', 'WhatsApp mesajı gönderildi olarak işaretlendi');
flash('success', 'Mesaj gönderildi olarak işaretlendi.');
redirect('modules/leads/view.php?id=' . $id);
