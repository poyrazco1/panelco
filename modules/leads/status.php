<?php
declare(strict_types=1);
/** modules/leads/status.php — Lead durumu değiştir (takip gerektiren durumlarda zorunlu takip). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead_reminders.php';
auth_boot();
require_permission('leads.status');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/leads/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$l = get_lead($id);
if (!$l) { flash('error', 'Lead bulunamadı.'); redirect('modules/leads/index.php'); }
$r = lead_status_apply_with_followup($id, $status, [
    'status_note'           => $_POST['status_note'] ?? '',
    'reminder_type'         => $_POST['reminder_type'] ?? 'call',
    'reminder_date'         => $_POST['reminder_date'] ?? '',
    'reminder_time'         => $_POST['reminder_time'] ?? '',
    'priority'              => $_POST['priority'] ?? 'normal',
    'remind_before_minutes' => $_POST['remind_before_minutes'] ?? 0,
    'assigned_user_id'      => $_POST['assigned_user_id'] ?? 0,
    'note'                  => $_POST['followup_note'] ?? '',
], current_user_id());
if ($r['ok']) { log_activity('lead_status', 'lead', $id, null, 'success', 'Durum: ' . lead_status_label($status)); flash('success', 'Durum güncellendi.'); }
else { flash('error', $r['error']); }
http_response_code(303);
redirect('modules/leads/view.php?id=' . $id);
