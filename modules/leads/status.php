<?php
declare(strict_types=1);
/** modules/leads/status.php — Lead durumu değiştir. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/leads.php';
auth_boot();
require_permission('leads.edit');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/leads/index.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$l = get_lead($id);
if (!$l) { flash('error', 'Lead bulunamadı.'); redirect('modules/leads/index.php'); }
if (set_lead_status($id, $status, current_user_id())) { log_activity('lead_status', 'lead', $id, null, 'success', 'Durum: ' . lead_status_label($status)); flash('success', 'Durum güncellendi.'); }
else { flash('error', 'Durum güncellenemedi.'); }
redirect('modules/leads/view.php?id=' . $id);
