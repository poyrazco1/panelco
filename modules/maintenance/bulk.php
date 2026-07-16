<?php
declare(strict_types=1);

/**
 * modules/maintenance/bulk.php
 * Bakım Takipleri toplu işlemleri (§14). İletişim izni olmayan müşteriler
 * toplu gönderim (WhatsApp/e-posta) listelerine ALINMAZ.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service_maintenance.php';

auth_boot();
if (!can_maint_view()) { require_permission('maintenance.view'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/maintenance/index.php'); }
csrf_check();

$uid    = current_user_id();
$action = (string) ($_POST['bulk_action'] ?? '');
$ids    = array_values(array_unique(array_map('intval', (array) ($_POST['ids'] ?? []))));
$ids    = array_filter($ids, static fn($v) => $v > 0);

$back = 'modules/maintenance/index.php';
$ret  = (string) ($_POST['return'] ?? '');
if ($ret !== '' && preg_match('#^modules/maintenance/[a-z0-9\-]+\.php(\?[a-z0-9=&_%\-\.]*)?$#i', $ret)) {
    $back = $ret;
}

if (!$ids) { flash('error', 'Hiç kayıt seçilmedi.'); http_response_code(303); redirect($back); }
if ($action === '') { flash('error', 'İşlem seçilmedi.'); http_response_code(303); redirect($back); }

$done = 0; $skipped = 0;

switch ($action) {
    case 'assign':
        if (!can_maint_assign()) { require_permission('maintenance.assign'); }
        $u = (int) ($_POST['assign_user'] ?? 0);
        foreach ($ids as $id) { if (smaint_assign_reminder($id, $u ?: null, $uid)) { $done++; } }
        flash('success', $done . ' kayıt için personel atandı.');
        break;

    case 'set_status':
        if (!can_maint_edit()) { require_permission('maintenance.edit'); }
        $ns = (string) ($_POST['new_status'] ?? '');
        if (!isset(smaint_statuses()[$ns])) { flash('error', 'Geçersiz durum.'); break; }
        foreach ($ids as $id) { if (smaint_set_status($id, $ns, $uid, 'Toplu durum değişikliği')) { $done++; } }
        flash('success', $done . ' kaydın durumu güncellendi.');
        break;

    case 'set_due':
        if (!can_maint_edit()) { require_permission('maintenance.edit'); }
        $nd = (string) ($_POST['new_due'] ?? '');
        if (trim($nd) === '') { flash('error', 'Yeni bakım tarihi girin.'); break; }
        foreach ($ids as $id) { if (smaint_set_due_date($id, $nd, $uid, 'Toplu tarih değişikliği')) { $done++; } }
        flash('success', $done . ' kaydın bakım tarihi güncellendi.');
        break;

    case 'call_list':
        if (!can_maint_message()) { require_permission('maintenance.message'); }
        foreach ($ids as $id) {
            smaint_set_preferred_channel($id, 'phone');
            if (smaint_set_status($id, 'to_call', $uid, 'Arama listesine eklendi')) { $done++; }
        }
        flash('success', $done . ' kayıt arama listesine eklendi.');
        break;

    case 'wa_list':
        if (!can_maint_whatsapp()) { require_permission('maintenance.whatsapp'); }
        foreach ($ids as $id) {
            $r = smaint_reminder_get($id);
            if (!$r) { continue; }
            if ((int) ($r['whatsapp_consent'] ?? 0) !== 1) { $skipped++; continue; } // izin yok → alınmaz (§14)
            smaint_set_preferred_channel($id, 'whatsapp');
            smaint_set_status($id, 'wa_prepared', $uid, 'WhatsApp listesine eklendi');
            $done++;
        }
        flash($skipped > 0 ? 'info' : 'success',
            $done . ' kayıt WhatsApp listesine eklendi.' . ($skipped > 0 ? ' ' . $skipped . ' kayıt izin bulunmadığı için atlandı.' : ''));
        break;

    case 'email_queue':
        if (!can_maint_email()) { require_permission('maintenance.email'); }
        foreach ($ids as $id) {
            $r = smaint_reminder_get($id);
            if (!$r) { continue; }
            if ((int) ($r['email_consent'] ?? 0) !== 1) { $skipped++; continue; } // izin yok → alınmaz (§14)
            if (trim((string) ($r['email'] ?? '')) === '') { $skipped++; continue; }
            smaint_set_preferred_channel($id, 'email');
            $done++;
        }
        flash($skipped > 0 ? 'info' : 'success',
            $done . ' kayıt e-posta kuyruğuna eklendi.' . ($skipped > 0 ? ' ' . $skipped . ' kayıt izin/e-posta olmadığı için atlandı.' : ''));
        break;

    case 'cancel':
        if (!can_maint_cancel()) { require_permission('maintenance.cancel'); }
        foreach ($ids as $id) { if (smaint_cancel_reminder($id, $uid, 'Toplu iptal')) { $done++; } }
        flash('success', $done . ' kayıt iptal edildi.');
        break;

    default:
        flash('error', 'Bilinmeyen toplu işlem.');
}

log_activity('maintenance_bulk_' . $action, 'maintenance', null, null, 'success',
    'İşlenen: ' . $done . ($skipped > 0 ? ', atlanan: ' . $skipped : ''));

http_response_code(303);
redirect($back);
