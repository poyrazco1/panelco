<?php
declare(strict_types=1);

/** modules/service/notify.php — Servis durum bilgilendirme e-postası (tekil, onaylı). */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/service.php';
require_once __DIR__ . '/../../includes/service-messages.php';

auth_boot();
require_permission('service.mail');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/service/index.php'); }
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$s  = get_service_record($id);
if (!$s) { flash('error', 'Servis kaydı bulunamadı.'); redirect('modules/service/index.php'); }

$res = service_status_send_mail($s);
log_activity('service_notify', 'service', $id, (string) ($s['reference_code'] ?? ''), $res['ok'] ? 'success' : 'failed', $res['ok'] ? 'Durum bilgilendirme maili gönderildi' : $res['msg']);
$res['ok'] ? flash('success', $res['msg']) : flash('error', $res['msg']);
redirect('modules/service/view.php?id=' . $id);
