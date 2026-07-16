<?php
declare(strict_types=1);

/**
 * modules/maintenance/action.php
 * Bakım hatırlatma tekil işlem dağıtıcısı (durum, atama, erteleme, tarih, iptal,
 * tamamlama, müşteri cevabı). Tüm işlemler POST + CSRF + yetki + audit.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service_maintenance.php';
require_once __DIR__ . '/../../includes/service_maintenance_notifications.php';

auth_boot();
if (!can_maint_view()) { require_permission('maintenance.view'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/maintenance/index.php'); }
csrf_check();

$id     = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$uid    = current_user_id();

$back = 'modules/maintenance/view.php?id=' . $id;
$ret  = (string) ($_POST['return'] ?? '');
if ($ret !== '' && preg_match('#^modules/maintenance/[a-z0-9\-]+\.php(\?[a-z0-9=&_%\-\.]*)?$#i', $ret)) {
    $back = $ret;
}

$r = smaint_reminder_get($id);
if (!$r) { flash('error', 'Bakım kaydı bulunamadı.'); http_response_code(303); redirect($back); }

switch ($action) {
    case 'assign':
        if (!can_maint_assign()) { require_permission('maintenance.assign'); }
        $newUser = (int) ($_POST['assign_user'] ?? 0);
        smaint_assign_reminder($id, $newUser ?: null, $uid)
            ? flash('success', 'Personel atandı.')
            : flash('error', 'Atama yapılamadı.');
        break;

    case 'set_status':
        if (!can_maint_edit()) { require_permission('maintenance.edit'); }
        $ns = (string) ($_POST['new_status'] ?? '');
        $note = trim((string) ($_POST['note'] ?? ''));
        smaint_set_status($id, $ns, $uid, $note)
            ? flash('success', 'Durum güncellendi.')
            : flash('error', 'Durum güncellenemedi.');
        break;

    case 'mark_called':
        if (!can_maint_message()) { require_permission('maintenance.message'); }
        smaint_set_status($id, 'called', $uid, 'Müşteri arandı.');
        smaint_touch_contact($id);
        flash('success', 'Arandı olarak işaretlendi.');
        break;

    case 'mark_unreachable':
        if (!can_maint_message()) { require_permission('maintenance.message'); }
        smaint_set_status($id, 'unreachable', $uid, 'Müşteriye ulaşılamadı.');
        smaint_touch_contact($id);
        flash('success', 'Ulaşılamadı olarak işaretlendi.');
        break;

    case 'mark_awaiting':
        if (!can_maint_message()) { require_permission('maintenance.message'); }
        smaint_set_status($id, 'awaiting_customer', $uid, 'Müşteri dönüşü bekleniyor.');
        flash('success', 'Müşteri dönüşü bekleniyor olarak işaretlendi.');
        break;

    case 'postpone':
        if (!can_maint_edit()) { require_permission('maintenance.edit'); }
        $res = smaint_postpone_reminder($id, (string) ($_POST['new_contact_at'] ?? ''), trim((string) ($_POST['reason'] ?? '')), $uid);
        $res['ok'] ? flash('success', 'Erteleme kaydedildi.') : flash('error', $res['error']);
        break;

    case 'set_due':
        if (!can_maint_edit()) { require_permission('maintenance.edit'); }
        smaint_set_due_date($id, (string) ($_POST['new_due'] ?? ''), $uid, trim((string) ($_POST['reason'] ?? '')))
            ? flash('success', 'Bakım tarihi güncellendi.')
            : flash('error', 'Bakım tarihi güncellenemedi.');
        break;

    case 'complete':
        if (!can_maint_edit()) { require_permission('maintenance.edit'); }
        smaint_complete_reminder($id, $uid, trim((string) ($_POST['note'] ?? '')))
            ? flash('success', 'Bakım kaydı tamamlandı.')
            : flash('error', 'İşlem yapılamadı.');
        break;

    case 'not_interested':
        if (!can_maint_edit()) { require_permission('maintenance.edit'); }
        smaint_set_status($id, 'not_interested', $uid, trim((string) ($_POST['note'] ?? 'Müşteri ilgilenmiyor.')));
        if (function_exists('smaint_dismiss_notifications')) { smaint_dismiss_notifications($id); }
        flash('success', 'İlgilenmiyor olarak işaretlendi.');
        break;

    case 'cancel':
        if (!can_maint_cancel()) { require_permission('maintenance.cancel'); }
        smaint_cancel_reminder($id, $uid, trim((string) ($_POST['reason'] ?? '')))
            ? flash('success', 'Bakım kaydı iptal edildi.')
            : flash('error', 'İşlem yapılamadı.');
        break;

    default:
        flash('error', 'Bilinmeyen işlem.');
}

http_response_code(303);
redirect($back);
