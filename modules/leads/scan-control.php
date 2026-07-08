<?php
declare(strict_types=1);
/** modules/leads/scan-control.php — Tarama durdur/devam/tamamla/sil. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead-scan.php';
auth_boot();
require_permission('leads.edit');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/leads/scans.php'); }
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$map = ['pause' => 'paused', 'resume' => 'running', 'done' => 'done', 'cancel' => 'cancelled'];
if ($action === 'delete') {
    lead_scan_delete($id, current_user_id());
    flash('success', 'Tarama silindi.');
    redirect('modules/leads/scans.php');
}
if (isset($map[$action])) {
    lead_scan_set_status($id, $map[$action], current_user_id());
    flash('success', 'Tarama durumu güncellendi: ' . lead_scan_status_label($map[$action]));
}
http_response_code(303);
redirect('modules/leads/scan-view.php?id=' . $id);
