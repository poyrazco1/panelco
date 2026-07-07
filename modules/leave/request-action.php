<?php
declare(strict_types=1);

/**
 * modules/leave/request-action.php
 * İzin talebi durum işlemleri: approve | reject | cancel | delete.
 * Yalnızca POST + CSRF. State değiştiren her işlem burada.
 */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leave.php';

auth_boot();
require_permission('leave');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    redirect('modules/leave/requests.php');
}
csrf_check();

$id     = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$uid    = (int) (current_user_id() ?? 0);

$req = $id > 0 ? get_leave_request($id) : null;
if (!$req) {
    flash('error', 'Kayıt bulunamadı.');
    http_response_code(303);
    redirect('modules/leave/requests.php');
}

switch ($action) {
    case 'approve':
        approve_leave_request($id, $uid) ? flash('success', 'İzin onaylandı.') : flash('error', 'Onaylanamadı.');
        break;
    case 'reject':
        reject_leave_request($id, $uid, trim((string) ($_POST['reason'] ?? ''))) ? flash('success', 'İzin reddedildi.') : flash('error', 'Reddedilemedi.');
        break;
    case 'cancel':
        cancel_leave_request($id, $uid) ? flash('success', 'İzin iptal edildi.') : flash('error', 'İptal edilemedi.');
        break;
    case 'delete':
        delete_leave_request($id, $uid);
        flash('success', 'Kayıt arşive alındı.');
        http_response_code(303);
        redirect('modules/leave/requests.php');
        break;
    default:
        flash('error', 'Geçersiz işlem.');
}

http_response_code(303);
redirect('modules/leave/request-view.php?id=' . $id);
