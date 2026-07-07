<?php
declare(strict_types=1);

/** modules/quotes/status.php — Teklif durumu değiştir. POST + CSRF. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/quotes.php';

auth_boot();
require_permission('quotes.status');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); redirect('modules/quotes/index.php'); }
csrf_check();

$id = (int) ($_POST['id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$q = get_quote($id);
if (!$q) { flash('error', 'Teklif bulunamadı.'); redirect('modules/quotes/index.php'); }

if (set_quote_status($id, $status, current_user_id())) {
    log_activity('quote_status', 'quote', $id, (string) $q['quote_no'], 'success', 'Durum: ' . quote_status_label($status));
    flash('success', 'Teklif durumu güncellendi.');
} else {
    flash('error', 'Durum güncellenemedi.');
}
redirect('modules/quotes/view.php?id=' . $id);
