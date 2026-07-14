<?php
declare(strict_types=1);

/** modules/leads/places-scan-save.php — Seçilen tarama sonuçlarını lead'e dönüştürür. */

require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead_scan_places.php';

auth_boot();
require_permission('leads.create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    redirect('modules/leads/places-scan.php');
}
csrf_check();

$searchId = (int) ($_POST['search_id'] ?? 0);
$resultIds = (array) ($_POST['result_ids'] ?? []);

if ($searchId <= 0 || !$resultIds) {
    flash('warning', 'Kaydedilecek kayıt seçilmedi.');
    http_response_code(303);
    redirect('modules/leads/places-scan.php?search_id=' . $searchId);
}

$res = gp_scan_save_results($searchId, $resultIds, [
    'assigned_personnel_id' => $_POST['assigned_personnel_id'] ?? 0,
    'status'                => $_POST['status'] ?? 'new',
    'package'               => $_POST['package'] ?? '',
    'source'                => $_POST['source'] ?? 'google_places',
    'keyword'               => $_POST['keyword'] ?? '',
    'allow_duplicates'      => isset($_POST['allow_duplicates']) ? 1 : 0,
], current_user_id());

$parts = [];
$parts[] = $res['saved'] . ' lead kaydedildi';
if ($res['skipped_dup'] > 0)   { $parts[] = $res['skipped_dup'] . ' kopya atlandı'; }
if ($res['skipped_saved'] > 0) { $parts[] = $res['skipped_saved'] . ' zaten kayıtlı'; }
if ($res['failed'] > 0)        { $parts[] = $res['failed'] . ' başarısız'; }

log_activity('lead_scan_save', 'leads', null, (string) $searchId, 'success',
    'Google Places taramasından ' . $res['saved'] . ' lead kaydedildi');
flash($res['saved'] > 0 ? 'success' : 'warning', implode(' · ', $parts) . '.');

http_response_code(303);
redirect('modules/leads/places-scan.php?search_id=' . $searchId);
