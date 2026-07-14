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
        require_action('leads', 'edit');
        if (lead_reminder_add($id, [
            'type' => $_POST['type'] ?? 'call', 'remind_at' => $_POST['remind_at'] ?? '',
            'note' => $_POST['note'] ?? '', 'assigned_to' => $_POST['assigned_to'] ?? 0,
        ], $uid) > 0) { flash('success', 'Hatırlatma eklendi.'); }
        else { flash('error', 'Hatırlatma tarihi gerekli.'); }
        break;

    case 'reminder_done':
        require_action('leads', 'edit');
        lead_reminder_done((int) ($_POST['reminder_id'] ?? 0), $uid);
        flash('success', 'Hatırlatma tamamlandı.');
        break;

    case 'assign':
        require_action('leads', 'edit');
        lead_assign($id, (int) ($_POST['personnel_id'] ?? 0) ?: null, $uid);
        flash('success', 'Atama güncellendi.');
        break;

    case 'worklist_add':
        require_action('leads', 'edit');
        $type = (string) ($_POST['list_type'] ?? 'call');
        $pid = (int) ($_POST['personnel_id'] ?? 0) ?: (int) $uid;
        lead_worklist_add($id, $pid, $type);
        flash('success', ($type === 'whatsapp' ? 'WhatsApp' : 'Arama') . ' listeme eklendi.');
        break;

    case 'worklist_remove':
        require_action('leads', 'edit');
        lead_worklist_remove($id, (string) ($_POST['list_type'] ?? 'call'));
        flash('success', 'Listeden çıkarıldı.');
        break;

    case 'status':
        require_action('leads', 'edit');
        if (set_lead_status($id, (string) ($_POST['status'] ?? ''), $uid, trim((string) ($_POST['status_note'] ?? '')))) {
            flash('success', 'Durum güncellendi.');
        } else { flash('error', 'Durum güncellenemedi.'); }
        break;

    default:
        flash('error', 'Geçersiz işlem.');
}

http_response_code(303);
redirect($back);
