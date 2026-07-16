<?php
declare(strict_types=1);

/**
 * modules/leads/lead-action.php — Lead detay etkileşimleri için tek POST işleyici.
 * action: call | wa | note | reminder | reminder_done | assign | worklist_add | worklist_remove | status
 */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads_crm.php';

auth_boot();
require_permission('leads.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/leads/index.php'); }
csrf_check();

$id     = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$uid    = current_user_id();
$lead   = get_lead($id);
if (!$lead) { flash('error', 'Lead bulunamadı.'); redirect('modules/leads/index.php'); }

$back = 'modules/leads/view.php?id=' . $id;
// İsteğe bağlı güvenli dönüş hedefi (yalnız leads modülü içi)
$ret = (string) ($_POST['return'] ?? '');
if ($ret !== '' && preg_match('#^modules/leads/[a-z0-9\-]+\.php(\?[a-z0-9=&_]*)?$#i', $ret)) { $back = $ret; }

switch ($action) {
    case 'call':
        require_action('leads', 'call');
        lead_call_log_add($id, [
            'result' => $_POST['result'] ?? '', 'contact_person' => $_POST['contact_person'] ?? '',
            'duration_sec' => $_POST['duration_sec'] ?? 0, 'note' => $_POST['note'] ?? '',
            'next_action' => $_POST['next_action'] ?? '', 'next_at' => $_POST['next_at'] ?? '',
        ], $uid);
        flash('success', 'Arama kaydı eklendi.');
        break;

    case 'wa':
        require_action('leads', 'whatsapp');
        if (lead_is_blacklisted($lead)) { flash('error', 'Kara listedeki lead için mesaj gönderilemez.'); break; }
        $msg = trim((string) ($_POST['message'] ?? ''));
        $phone = lead_normalize_phone(trim((string) ($lead['whatsapp'] ?? '')) ?: trim((string) ($lead['phone'] ?? '')));
        if ($msg === '' || $phone === '') { flash('error', 'Mesaj ve telefon gerekli.'); break; }
        lead_wa_log_add($id, $phone, $msg, (int) ($_POST['template_id'] ?? 0) ?: null, $uid);
        flash('success', 'WhatsApp gönderimi kaydedildi.');
        break;

    case 'note':
        require_action('leads', 'edit');
        if (lead_note_add($id, (string) ($_POST['note'] ?? ''), $uid) > 0) { flash('success', 'Not eklendi.'); }
        else { flash('warning', 'Boş not eklenmedi.'); }
        break;

    case 'reminder':
        if (!can_followup_create()) { require_permission('leads.followup_create'); }
        $r = lead_reminder_create($id, [
            'reminder_type' => $_POST['reminder_type'] ?? 'call', 'reminder_date' => $_POST['reminder_date'] ?? '',
            'reminder_time' => $_POST['reminder_time'] ?? '', 'priority' => $_POST['priority'] ?? 'normal',
            'remind_before_minutes' => $_POST['remind_before_minutes'] ?? 0, 'assigned_user_id' => $_POST['assigned_user_id'] ?? 0,
            'note' => $_POST['note'] ?? '',
        ], $uid);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Takip planlandı.' : $r['error']);
        break;

    case 'reminder_postpone':
        if (!can_followup_postpone()) { require_permission('leads.followup_postpone'); }
        $r = lead_reminder_postpone((int) ($_POST['reminder_id'] ?? 0), (string) ($_POST['postpone'] ?? ''),
            (string) ($_POST['custom_at'] ?? ''), (string) ($_POST['reason'] ?? ''), $uid);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Takip ertelendi.' : $r['error']);
        break;

    case 'reminder_complete':
        if (!can_followup_complete()) { require_permission('leads.followup_complete'); }
        $r = lead_reminder_complete((int) ($_POST['reminder_id'] ?? 0), [
            'completion_result' => $_POST['completion_result'] ?? '', 'completion_note' => $_POST['completion_note'] ?? '',
            'contact_person' => $_POST['contact_person'] ?? '', 'new_status' => $_POST['new_status'] ?? '',
            'need_followup' => isset($_POST['need_followup']) ? 1 : 0,
            'next_date' => $_POST['next_date'] ?? '', 'next_time' => $_POST['next_time'] ?? '',
            'next_type' => $_POST['next_type'] ?? '', 'next_priority' => $_POST['next_priority'] ?? '',
            'next_note' => $_POST['next_note'] ?? '',
        ], $uid);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Takip tamamlandı.' : $r['error']);
        break;

    case 'reminder_cancel':
        if (!can_followup_edit()) { require_permission('leads.followup_edit'); }
        lead_reminder_cancel((int) ($_POST['reminder_id'] ?? 0), $uid, (string) ($_POST['reason'] ?? ''));
        flash('success', 'Takip iptal edildi.');
        break;

    case 'reminder_reassign':
        if (!can_followup_reassign()) { require_permission('leads.followup_reassign'); }
        if (!lead_reminders_can_see_team()) { flash('error', 'Yeniden atama yetkiniz yok.'); break; }
        lead_reminder_reassign((int) ($_POST['reminder_id'] ?? 0), (int) ($_POST['new_user_id'] ?? 0) ?: 0, $uid);
        flash('success', 'Takip yeniden atandı.');
        break;

    case 'assign':
        require_action('leads', 'edit');
        lead_assign($id, (int) ($_POST['personnel_id'] ?? 0) ?: null, $uid);
        flash('success', 'Atama güncellendi.');
        break;

    case 'worklist_add':
        require_action('leads', 'edit');
        $type = (string) ($_POST['list_type'] ?? 'call');
        $pid = (int) ($_POST['personnel_id'] ?? 0) ?: current_personnel_id();
        if ($pid <= 0) { flash('error', 'Çalışma listesi için hesabınıza bağlı bir personel kaydı gerekli.'); break; }
        lead_worklist_add($id, $pid, $type);
        flash('success', ($type === 'whatsapp' ? 'WhatsApp' : 'Arama') . ' listeme eklendi.');
        break;

    case 'worklist_remove':
        require_action('leads', 'edit');
        lead_worklist_remove($id, (string) ($_POST['list_type'] ?? 'call'));
        flash('success', 'Listeden çıkarıldı.');
        break;

    case 'status':
        require_action('leads', 'status');
        $r = lead_status_apply_with_followup($id, (string) ($_POST['status'] ?? ''), [
            'status_note'           => $_POST['status_note'] ?? '',
            'reminder_type'         => $_POST['reminder_type'] ?? 'call',
            'reminder_date'         => $_POST['reminder_date'] ?? '',
            'reminder_time'         => $_POST['reminder_time'] ?? '',
            'priority'              => $_POST['priority'] ?? 'normal',
            'remind_before_minutes' => $_POST['remind_before_minutes'] ?? 0,
            'assigned_user_id'      => $_POST['assigned_user_id'] ?? 0,
            'note'                  => $_POST['followup_note'] ?? '',
        ], $uid);
        flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Durum güncellendi.' : $r['error']);
        break;

    default:
        flash('error', 'Geçersiz işlem.');
}

http_response_code(303);
redirect($back);
